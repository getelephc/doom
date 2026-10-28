#!/usr/bin/env python3
"""Package the native game and provenance without local WADs or save files."""
import gzip
import hashlib
import io
import os
from pathlib import Path
import platform
import re
import subprocess
import tarfile
import tempfile

from macos_runtime import bundle as bundle_macos


def desktop_target(system, machine):
    targets = {
        ("Linux", "x86_64"): "linux_x86_64",
        ("Linux", "aarch64"): "linux_aarch64",
        ("Linux", "arm64"): "linux_aarch64",
        ("Darwin", "arm64"): "macos_aarch64",
    }
    try:
        return targets[(system, machine)]
    except KeyError as exc:
        raise ValueError(f"Unsupported desktop host: {system}/{machine}") from exc


def package(root, output, version, compiler):
    version = version.removeprefix("v")
    if version != "dev" and not re.fullmatch(r"(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)", version):
        raise ValueError("Package version must be dev or MAJOR.MINOR.PATCH")
    target = desktop_target(platform.system(), platform.machine())
    binary = root / "main"
    if not binary.is_file() or not os.access(binary, os.X_OK):
        raise ValueError("Build the game before packaging")
    commit = subprocess.check_output(["git", "rev-parse", "HEAD"], cwd=root, text=True).strip()
    epoch = int(subprocess.check_output(["git", "show", "-s", "--format=%ct", "HEAD"], cwd=root))
    compiler_version = subprocess.check_output([compiler, "--version"], text=True).strip()
    prefix = f"doom_{version}_{target}"
    provenance = f"DOOM: {version}\nCommit: {commit}\nTarget: {target}\nCompiler: {compiler_version}\n"
    entries = {
        "doom": (binary.read_bytes(), 0o755),
        "README.md": ((root / "README.md").read_bytes(), 0o644),
        "LICENSE": ((root / "LICENSE").read_bytes(), 0o644),
        "BUILD-INFO.txt": (provenance.encode(), 0o644),
    }
    output.mkdir(parents=True, exist_ok=True)
    if platform.system() == "Darwin":
        with tempfile.TemporaryDirectory(dir=output, prefix=".doom-runtime-") as directory:
            stage = Path(directory)
            bundle_macos(binary, stage)
            for path in stage.rglob("*"):
                if path.is_file():
                    relative = path.relative_to(stage).as_posix()
                    entries[relative] = (path.read_bytes(), 0o644 if relative.startswith("licenses/") else 0o755)
    archive = output / f"{prefix}.tar.gz"
    temporary = None
    try:
        with tempfile.NamedTemporaryFile(dir=output, prefix=".doom-", delete=False) as raw:
            temporary = Path(raw.name)
            with gzip.GzipFile(fileobj=raw, mode="wb", filename="", mtime=epoch) as compressed:
                with tarfile.open(fileobj=compressed, mode="w") as tar:
                    for name, (data, mode) in entries.items():
                        info = tarfile.TarInfo(f"{prefix}/{name}")
                        info.size, info.mode, info.mtime = len(data), mode, epoch
                        tar.addfile(info, io.BytesIO(data))
        os.replace(temporary, archive)
        temporary = None
        digest = hashlib.sha256(archive.read_bytes()).hexdigest()
        archive.with_name(archive.name + ".sha256").write_text(f"{digest}  {archive.name}\n")
    finally:
        if temporary is not None:
            temporary.unlink(missing_ok=True)
    return archive


if __name__ == "__main__":
    project = Path(__file__).resolve().parent.parent
    print(package(project, Path(os.environ.get("DIST_DIR", "dist")).resolve(),
                  os.environ.get("VERSION", "dev"), os.environ.get("ELEPHC", "elephc")))
