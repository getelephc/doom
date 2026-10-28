"""Stage relocatable macOS dylibs and notices, then sign the staged images."""
import os
from pathlib import Path
import re
import shutil
import subprocess


def output(*args):
    return subprocess.check_output(args, text=True)


def system_library(name):
    return name.startswith(("/System/Library/", "/usr/lib/"))


def dependencies(image):
    identities = {line.strip() for line in output("otool", "-D", str(image)).splitlines()[1:]}
    result = []
    for line in output("otool", "-L", str(image)).splitlines()[1:]:
        match = re.match(r"\s+(.+?)\s+\(compatibility version", line)
        if match and match[1] not in identities:
            result.append(match[1])
    return result


def rpaths(image):
    lines = output("otool", "-l", str(image)).splitlines()
    result = []
    for i, line in enumerate(lines):
        if line.strip() == "cmd LC_RPATH":
            for following in lines[i + 1:i + 4]:
                match = re.match(r"\s*path (.+) \(offset \d+\)", following)
                if match:
                    result.append(match[1])
    return result


def expand(name, loader, executable):
    if name.startswith("@loader_path/"):
        return loader.parent / name.removeprefix("@loader_path/")
    if name.startswith("@executable_path/"):
        return executable.parent / name.removeprefix("@executable_path/")
    return Path(name)


def resolve(name, loader, executable, search_paths):
    if name.startswith("@rpath/"):
        suffix = name.removeprefix("@rpath/")
        candidates = [expand(path, loader, executable) / suffix for path in search_paths]
    else:
        candidates = [expand(name, loader, executable)]
        if not name.startswith(("/", "@")):
            candidates.insert(0, loader.parent / name)
    for path in candidates:
        if path.is_file():
            return path.resolve()
    raise ValueError(f"Cannot resolve macOS dependency {name} from {loader}")


def copy_notices(library, directory):
    # Homebrew installs upstream notices in each keg alongside its receipt.
    keg = next((parent for parent in library.parents if (parent / "INSTALL_RECEIPT.json").is_file()), None)
    if keg is None:
        raise ValueError(f"No Homebrew license metadata found for {library}")
    notices = sorted(path for path in keg.iterdir()
                     if path.is_file() and path.name.upper().startswith(("LICENSE", "COPYING")))
    if not notices:
        raise ValueError(f"No dependency license notice found in {keg}")
    directory.mkdir(exist_ok=True)
    component = keg.parent.name
    for source in notices:
        shutil.copy2(source, directory / f"{component}-{source.name}")


def bundle(binary, destination):
    executable = binary.resolve()
    destination.mkdir(parents=True, exist_ok=True)
    staged_binary = destination / "doom"
    shutil.copy2(executable, staged_binary)
    lib = destination / "lib"
    lib.mkdir()
    staged = {executable: staged_binary}
    names = {}
    graph = {}
    pending = [(executable, [])]

    def add_library(source, name=None):
        if source in staged:
            return
        name = name or source.name
        if name in names and names[name] != source:
            raise ValueError(f"Conflicting bundled library names: {name}")
        names[name] = source
        target = lib / name
        shutil.copy2(source, target)
        target.chmod(0o755)
        staged[source] = target
        copy_notices(source, destination / "licenses")

    while pending:
        source, inherited = pending.pop()
        if source in graph:
            continue
        paths = rpaths(source) + inherited
        graph[source] = []
        for name in dependencies(source):
            if system_library(name):
                continue
            dependency = resolve(name, source, executable, paths)
            add_library(dependency)
            graph[source].append((name, dependency))
            # Resolve inherited loader-relative rpaths at the image declaring them.
            inherited_paths = [str(expand(path, source, executable)) for path in paths]
            pending.append((dependency, inherited_paths))
        # Homebrew's SDL2 formula can be sdl2-compat. SDL3 is dlopen'ed, so it
        # does not appear in otool -L. Its first macOS search is a sibling named
        # libSDL3.dylib (libsdl-org/sdl2-compat, LoadSDL3Library).
        if source != executable and "sdl2-compat" in source.parts:
            prefix = Path(output("brew", "--prefix", "sdl3").strip())
            dependency = (prefix / "lib/libSDL3.dylib").resolve(strict=True)
            if dependency not in staged:
                add_library(dependency, "libSDL3.dylib")
                pending.append((dependency, []))

    for source, target in staged.items():
        if source != executable:
            subprocess.run(["install_name_tool", "-id", f"@loader_path/{target.name}", str(target)], check=True)
        for old, dependency in graph[source]:
            relative = os.path.relpath(staged[dependency], target.parent)
            subprocess.run(["install_name_tool", "-change", old, f"@loader_path/{relative}", str(target)], check=True)
        for path in rpaths(target):
            if path.startswith("/") and not system_library(path):
                subprocess.run(["install_name_tool", "-delete_rpath", path, str(target)], check=True)

    # Audit all strong imports before signing. Every non-system image must be
    # located inside this archive, independently of the user's working directory.
    for target in staged.values():
        for name in dependencies(target):
            if system_library(name):
                continue
            if not name.startswith("@loader_path/"):
                raise ValueError(f"Non-relocatable dependency remains: {name}")
            resolved = expand(name, target, staged_binary).resolve(strict=True)
            if not resolved.is_relative_to(destination.resolve()):
                raise ValueError(f"Dependency escapes the archive: {name}")
    for image in [path for path in staged.values() if path != staged_binary] + [staged_binary]:
        subprocess.run(["codesign", "--force", "--sign", "-", str(image)], check=True)
        subprocess.run(["codesign", "--verify", "--strict", str(image)], check=True)
