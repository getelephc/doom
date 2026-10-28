"""Exercise release side effects in disposable repositories, never on GitHub."""
import hashlib
import importlib.util
import os
from pathlib import Path
import shutil
import subprocess
import tarfile
import tempfile
import unittest
from unittest.mock import patch

SOURCE = Path(__file__).resolve().parents[2]
SPEC = importlib.util.spec_from_file_location("doom_package", SOURCE / "scripts/package.py")
PACKAGER = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(PACKAGER)


class ReleaseTests(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory(prefix="doom-release-")
        self.addCleanup(self.temporary.cleanup)
        self.base = Path(self.temporary.name)
        self.repo = self.base / "repo"
        self.repo.mkdir()
        self.remote = self.base / "origin.git"
        self.git("init", "-b", "main")
        self.git("config", "user.name", "Release test")
        self.git("config", "user.email", "test@example.invalid")
        self.git("config", "commit.gpgsign", "false")
        self.git("config", "tag.gpgsign", "false")
        (self.repo / "fixture").write_text("fixture\n")
        self.git("add", ".")
        self.git("commit", "-m", "test: seed release fixture")
        subprocess.run(["git", "init", "--bare", str(self.remote)], check=True, capture_output=True)
        self.git("remote", "add", "origin", "https://github.com/acme/doom.git")
        self.git("config", f"url.{self.remote}.insteadOf", "https://github.com/acme/doom.git")
        self.git("push", "origin", "main")
        self.head = self.git("rev-parse", "HEAD").stdout.strip()
        tools = self.base / "bin"
        tools.mkdir()
        gh = tools / "gh"
        gh.write_text('''#!/usr/bin/env bash
set -eu
case "$1" in
 repo) echo 'acme/doom' ;;
 api)
  case "$2" in
   */heads/main) printf '%s\\n' "$TEST_REMOTE_HEAD" ;;
   */tags/v) printf '%s' "${TEST_TAGS:-}" ;;
   *) exit 90 ;;
  esac ;;
 *) exit 91 ;;
esac
''')
        gh.chmod(0o755)
        self.env = os.environ.copy()
        self.env.update(PATH=str(tools) + os.pathsep + self.env["PATH"], TEST_REMOTE_HEAD=self.head)
        self.env.pop("VERSION", None)
        self.env.pop("TEST_TAGS", None)

    def git(self, *args):
        return subprocess.run(["git", *args], cwd=self.repo, check=True, capture_output=True, text=True)

    def release(self, version=None, dry=False, answer="\ny\n"):
        env = self.env.copy()
        if version is not None:
            env["VERSION"] = version
        return subprocess.run(["bash", str(SOURCE / "scripts/release.sh"), *(["--dry-run"] if dry else [])],
                              cwd=self.repo, env=env, input=answer, capture_output=True, text=True)

    def test_default_release_creates_and_pushes_an_annotated_tag(self):
        result = self.release()
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual(self.git("cat-file", "-t", "v0.1.0").stdout.strip(), "tag")
        remote = subprocess.check_output(["git", "--git-dir", str(self.remote), "rev-parse", "v0.1.0^{commit}"], text=True)
        self.assertEqual(remote.strip(), self.head)

    def test_dry_run_proposes_next_patch_without_changing_refs(self):
        self.env["TEST_TAGS"] = "refs/tags/v1.9.9\nrefs/tags/v1.10.2\nrefs/tags/v9.0.0-rc1\n"
        result = self.release(dry=True)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertIn("v1.10.3", result.stdout)
        self.assertEqual(self.git("tag", "--list").stdout, "")

    def test_invalid_older_and_duplicate_versions_do_not_tag(self):
        self.env["TEST_TAGS"] = "refs/tags/v1.2.3\n"
        for version in ["1.02.4", "1.2.3", "1.2.2", "bad", "1.2.4;touch bad"]:
            with self.subTest(version=version):
                self.assertNotEqual(self.release(version=version).returncode, 0)
        self.assertEqual(self.git("tag", "--list").stdout, "")

    def test_dirty_worktree_and_wrong_branch_are_rejected(self):
        (self.repo / "untracked").write_text("dirty")
        self.assertNotEqual(self.release(dry=True).returncode, 0)
        (self.repo / "untracked").unlink()
        self.git("switch", "-c", "feature")
        self.assertNotEqual(self.release(dry=True).returncode, 0)

    def test_unpublished_main_is_rejected(self):
        self.env["TEST_REMOTE_HEAD"] = "0" * 40
        self.assertNotEqual(self.release(dry=True).returncode, 0)

    def test_cancellation_and_existing_local_tag_are_safe(self):
        result = self.release(version="0.1.0", answer="n\n")
        self.assertEqual(result.returncode, 0)
        self.assertEqual(self.git("tag", "--list").stdout, "")
        self.git("tag", "v0.1.0")
        self.assertNotEqual(self.release(version="0.1.0", dry=True).returncode, 0)

    def test_failed_push_preserves_the_tag_and_reports_a_retry(self):
        self.git("config", "--remove-section", f"url.{self.remote}")
        self.git("config", f"url.{self.base / 'missing.git'}.insteadOf", "https://github.com/acme/doom.git")
        result = self.release(version="0.1.0", answer="y\n")
        self.assertNotEqual(result.returncode, 0)
        self.assertIn("retry with: git push origin refs/tags/v0.1.0", result.stderr)
        self.assertEqual(self.git("cat-file", "-t", "v0.1.0").stdout.strip(), "tag")


class PackageTests(unittest.TestCase):
    def test_supported_hosts_and_unsupported_targets(self):
        self.assertEqual(PACKAGER.desktop_target("Linux", "x86_64"), "linux_x86_64")
        self.assertEqual(PACKAGER.desktop_target("Linux", "aarch64"), "linux_aarch64")
        self.assertEqual(PACKAGER.desktop_target("Darwin", "arm64"), "macos_aarch64")
        with self.assertRaises(ValueError):
            PACKAGER.desktop_target("Windows", "AMD64")

    def test_archive_manifest_permissions_provenance_and_checksum(self):
        with tempfile.TemporaryDirectory(prefix="doom-package-") as directory:
            root = Path(directory)
            (root / "main").write_bytes(b"native executable fixture\n")
            (root / "main").chmod(0o755)
            for name in ["README.md", "LICENSE", "DOOM1.WAD"]:
                (root / name).write_text(name)
            (root / "saves").mkdir()
            (root / "saves/slot0.json").write_text("private save")
            def command(args, **_kwargs):
                if args[0] == "fixture-elephc":
                    return "elephc 0.27.6\n"
                if args[1] == "rev-parse":
                    return "a" * 40 + "\n"
                return b"1700000000\n"
            with patch.object(PACKAGER.platform, "system", return_value="Linux"), \
                 patch.object(PACKAGER.platform, "machine", return_value="x86_64"), \
                 patch.object(PACKAGER.subprocess, "check_output", side_effect=command):
                archive = PACKAGER.package(root, root / "dist", "v1.2.3", "fixture-elephc")
            prefix = "doom_1.2.3_linux_x86_64"
            with tarfile.open(archive) as tar:
                self.assertEqual(set(tar.getnames()), {f"{prefix}/{name}" for name in ["doom", "README.md", "LICENSE", "BUILD-INFO.txt"]})
                self.assertEqual(tar.getmember(f"{prefix}/doom").mode, 0o755)
                self.assertIn(b"elephc 0.27.6", tar.extractfile(f"{prefix}/BUILD-INFO.txt").read())
            checksum = archive.with_name(archive.name + ".sha256").read_text().split()[0]
            self.assertEqual(checksum, hashlib.sha256(archive.read_bytes()).hexdigest())
            self.assertFalse(list((root / "dist").glob(".doom-*")))


class InstallerTests(unittest.TestCase):
    def test_archive_checksum_is_checked_before_compiler_execution(self):
        targets = {("Linux", "x86_64"): "x86_64-unknown-linux-gnu",
                   ("Linux", "aarch64"): "aarch64-unknown-linux-gnu",
                   ("Linux", "arm64"): "aarch64-unknown-linux-gnu",
                   ("Darwin", "arm64"): "aarch64-apple-darwin"}
        import platform
        for valid in [True, False]:
            with self.subTest(valid_checksum=valid), tempfile.TemporaryDirectory(prefix="doom-installer-") as directory:
                root = Path(directory)
                (root / "scripts").mkdir()
                shutil.copy2(SOURCE / "scripts/install-elephc.sh", root / "scripts/install-elephc.sh")
                (root / ".elephc-version").write_text("v0.27.6\n")
                archive = root / f"elephc-v0.27.6-{targets[(platform.system(), platform.machine())]}.tar.gz"
                program = root / "elephc"
                program.write_text("#!/bin/sh\necho 'elephc 0.27.6'\n")
                program.chmod(0o755)
                with tarfile.open(archive, "w:gz") as tar:
                    tar.add(program, arcname="elephc")
                digest = hashlib.sha256(archive.read_bytes()).hexdigest() if valid else "0" * 64
                checksum = archive.with_name(archive.name + ".sha256")
                checksum.write_text(f"{digest}  {archive.name}\n")
                (root / "bin").mkdir()
                gh = root / "bin/gh"
                gh.write_text('#!/bin/sh\ncp "$TEST_ARCHIVE" "$TEST_CHECKSUM" .\n')
                gh.chmod(0o755)
                env = os.environ.copy()
                env.update(PATH=str(root / "bin") + os.pathsep + env["PATH"],
                           TEST_ARCHIVE=str(archive), TEST_CHECKSUM=str(checksum))
                result = subprocess.run(["bash", str(root / "scripts/install-elephc.sh"), str(root / "installed")],
                                        env=env, capture_output=True, text=True)
                self.assertEqual(result.returncode == 0, valid, result.stderr)
                self.assertEqual((root / "installed/elephc").exists(), valid)
                if valid:
                    self.assertFalse((root / "installed" / archive.name).exists())
