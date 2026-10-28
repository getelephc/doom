#!/usr/bin/env python3
"""Exercise an extracted macOS archive after CI removes its Homebrew SDL kegs."""
import ctypes
import os
from pathlib import Path
import subprocess
import sys
import tarfile
import tempfile


def extract(archive, directory):
    # macOS temporary paths can use /var, a symlink to /private/var.
    directory = directory.resolve()
    with tarfile.open(archive) as tar:
        for member in tar.getmembers():
            path = directory / member.name
            if not path.resolve().is_relative_to(directory) or not member.isfile():
                raise ValueError(f"Unsafe archive entry: {member.name}")
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_bytes(tar.extractfile(member).read())
            path.chmod(member.mode)
    roots = list(directory.iterdir())
    if len(roots) != 1:
        raise ValueError("Expected one archive directory")
    return roots[0]


def check(archive):
    with tempfile.TemporaryDirectory(prefix="doom clean mac ") as directory:
        temporary = Path(directory).resolve()
        root = extract(archive, temporary)
        env = {key: value for key, value in os.environ.items() if not key.startswith("DYLD_")}
        env.update(SDL_VIDEODRIVER="dummy", SDL_AUDIODRIVER="dummy")
        result = subprocess.run([str(root / "doom"), str(temporary / "missing.wad")],
                                cwd=temporary, env=env, check=True, capture_output=True, text=True, timeout=15)
        if "No WAD file at" not in result.stdout:
            raise ValueError("Packaged executable did not reach its normal WAD check")
        for key in list(os.environ):
            if key.startswith("DYLD_"):
                os.environ.pop(key)
        os.environ["SDL_VIDEODRIVER"] = "dummy"
        os.environ["SDL_AUDIODRIVER"] = "dummy"
        library = ctypes.CDLL(str(root / "lib/libSDL2-2.0.0.dylib"))
        library.SDL_Init.argtypes, library.SDL_Init.restype = [ctypes.c_uint32], ctypes.c_int
        library.SDL_CreateWindow.argtypes = [ctypes.c_char_p, ctypes.c_int, ctypes.c_int, ctypes.c_int, ctypes.c_int, ctypes.c_uint32]
        library.SDL_CreateWindow.restype = ctypes.c_void_p
        library.SDL_DestroyWindow.argtypes = [ctypes.c_void_p]
        library.SDL_DestroyWindow.restype = None
        library.SDL_Quit.argtypes, library.SDL_Quit.restype = [], None
        library.SDL_GetError.argtypes, library.SDL_GetError.restype = [], ctypes.c_char_p
        if library.SDL_Init(48) != 0:
            raise ValueError(f"Bundled SDL video/audio initialization failed: {library.SDL_GetError()!r}")
        try:
            window = library.SDL_CreateWindow(b"Archive smoke", 0, 0, 320, 200, 0)
            if not window:
                raise ValueError("Bundled SDL could not create a dummy window")
            library.SDL_DestroyWindow(window)
        finally:
            library.SDL_Quit()
        print("Packaged executable and bundled SDL video/audio work without Homebrew SDL")


if __name__ == "__main__":
    check(Path(sys.argv[1]).resolve())
