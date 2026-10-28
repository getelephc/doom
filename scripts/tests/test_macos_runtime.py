"""Test relocation and SDL3's dlopen dependency without requiring macOS locally."""
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest
from unittest.mock import patch

SCRIPTS = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(SCRIPTS))
import macos_runtime as runtime


class RuntimeTests(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory(prefix="doom-dylibs-")
        self.addCleanup(self.temporary.cleanup)
        self.root = Path(self.temporary.name)
        self.binary = self.root / "main"
        self.binary.write_text("main")
        self.binary.chmod(0o755)
        self.sdl = self.keg("sdl2-compat", "libSDL2-2.0.0.dylib", "SDL2")
        self.helper = self.keg("support", "libsupport.dylib", "support")
        self.sdl3 = self.keg("sdl3", "libSDL3.0.dylib", "SDL3")
        (self.sdl3.parent / "libSDL3.dylib").symlink_to(self.sdl3.name)
        self.originals = {
            "main": {"id": [], "deps": [str(self.sdl), "/usr/lib/libSystem.B.dylib"], "rpaths": []},
            "SDL2": {"id": [str(self.sdl)], "deps": ["@rpath/libsupport.dylib"], "rpaths": [str(self.helper.parent)]},
            "support": {"id": [str(self.helper)], "deps": ["/usr/lib/libc++.1.dylib"], "rpaths": []},
            "SDL3": {"id": [str(self.sdl3)], "deps": ["/System/Library/Frameworks/Cocoa.framework/Versions/A/Cocoa"], "rpaths": []},
        }
        self.images = {}
        self.commands = []

    def keg(self, name, filename, marker):
        prefix = self.root / "Cellar" / name / "1.0"
        (prefix / "lib").mkdir(parents=True)
        (prefix / "INSTALL_RECEIPT.json").write_text("{}")
        (prefix / "LICENSE.txt").write_text(f"{name} license notice\n")
        library = prefix / "lib" / filename
        library.write_text(marker)
        return library

    def image(self, path):
        path = Path(path)
        if path not in self.images:
            original = self.originals[path.read_text()]
            self.images[path] = {key: list(value) for key, value in original.items()}
        return self.images[path]

    def output(self, *args):
        if args[0] == "brew":
            return str(self.sdl3.parent.parent) + "\n"
        image = self.image(args[-1])
        if args[1] == "-D":
            return "image:\n" + "\n".join(image["id"]) + "\n"
        if args[1] == "-L":
            return "image:\n" + "".join(f"\t{name} (compatibility version 1.0.0, current version 1.0.0)\n" for name in image["id"] + image["deps"])
        return "".join(f" cmd LC_RPATH\n cmdsize 48\n path {name} (offset 12)\n" for name in image["rpaths"])

    def run_command(self, args, **_kwargs):
        self.commands.append(args)
        if args[0] == "install_name_tool":
            image = self.image(args[-1])
            if args[1] == "-change":
                image["deps"] = [args[3] if name == args[2] else name for name in image["deps"]]
            elif args[1] == "-id":
                image["id"] = [args[2]]
            elif args[1] == "-delete_rpath":
                image["rpaths"].remove(args[2])
        return subprocess.CompletedProcess(args, 0)

    def test_transitive_libraries_dlopen_sdl3_notices_and_signatures(self):
        destination = self.root / "archive with spaces"
        with patch.object(runtime, "output", side_effect=self.output), patch.object(runtime.subprocess, "run", side_effect=self.run_command):
            runtime.bundle(self.binary, destination)
        self.assertEqual({path.name for path in (destination / "lib").iterdir()},
                         {"libSDL2-2.0.0.dylib", "libsupport.dylib", "libSDL3.dylib"})
        self.assertEqual(len(list((destination / "licenses").iterdir())), 3)
        self.assertEqual(self.image(destination / "doom")["deps"][0], "@loader_path/lib/libSDL2-2.0.0.dylib")
        self.assertEqual(self.image(destination / "lib/libSDL2-2.0.0.dylib")["deps"], ["@loader_path/libsupport.dylib"])
        self.assertEqual(self.image(destination / "lib/libSDL2-2.0.0.dylib")["rpaths"], [])
        self.assertEqual(self.image(self.sdl)["rpaths"], [str(self.helper.parent)])
        signed = [command[-1] for command in self.commands if command[:2] == ["codesign", "--force"]]
        self.assertEqual(signed[-1], str(destination / "doom"))
        self.assertEqual(len(signed), 4)
        self.assertNotIn(str(self.binary), signed)
        self.assertEqual(self.binary.read_text(), "main")

    def test_missing_license_fails_before_signing(self):
        (self.helper.parent.parent / "LICENSE.txt").unlink()
        with patch.object(runtime, "output", side_effect=self.output), patch.object(runtime.subprocess, "run", side_effect=self.run_command):
            with self.assertRaisesRegex(ValueError, "license notice"):
                runtime.bundle(self.binary, self.root / "missing notice")
        self.assertFalse(any(command[0] == "codesign" for command in self.commands))

    def test_unresolved_rpath_is_rejected(self):
        self.originals["SDL2"]["rpaths"] = []
        with patch.object(runtime, "output", side_effect=self.output), patch.object(runtime.subprocess, "run", side_effect=self.run_command):
            with self.assertRaisesRegex(ValueError, "Cannot resolve"):
                runtime.bundle(self.binary, self.root / "unresolved")
