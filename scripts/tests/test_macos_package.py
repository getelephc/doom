"""Check archive extraction through macOS-style temporary-directory aliases."""
import io
from pathlib import Path
import sys
import tarfile
import tempfile
import unittest

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from check_macos_package import extract


class ExtractionTests(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory(prefix="doom-extract-")
        self.addCleanup(self.temporary.cleanup)
        self.root = Path(self.temporary.name).resolve()
        self.destination = self.root / "private var"
        self.destination.mkdir()
        self.alias = self.root / "var"
        self.alias.symlink_to(self.destination, target_is_directory=True)
        self.archive = self.root / "package.tar.gz"

    def write_archive(self, name, kind=tarfile.REGTYPE):
        with tarfile.open(self.archive, "w:gz") as tar:
            entry = tarfile.TarInfo(name)
            entry.type = kind
            entry.mode = 0o755
            if kind == tarfile.REGTYPE:
                entry.size = 4
                tar.addfile(entry, io.BytesIO(b"doom"))
            else:
                entry.linkname = "outside"
                tar.addfile(entry)

    def test_extracts_under_a_symlinked_temporary_directory(self):
        self.write_archive("package/doom")
        root = extract(self.archive, self.alias)
        self.assertEqual(root, self.destination / "package")
        self.assertEqual((root / "doom").read_bytes(), b"doom")
        self.assertEqual((root / "doom").stat().st_mode & 0o777, 0o755)

    def test_rejects_traversal_through_a_symlinked_temporary_directory(self):
        self.write_archive("../outside")
        with self.assertRaisesRegex(ValueError, "Unsafe archive entry"):
            extract(self.archive, self.alias)
        self.assertFalse((self.root / "outside").exists())

    def test_rejects_archive_symlinks(self):
        self.write_archive("package/doom", tarfile.SYMTYPE)
        with self.assertRaisesRegex(ValueError, "Unsafe archive entry"):
            extract(self.archive, self.alias)
