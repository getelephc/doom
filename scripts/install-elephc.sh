#!/usr/bin/env bash
# Install the pinned compiler archive and verify its published SHA-256.
set -euo pipefail
root="$(cd "$(dirname "$0")/.." && pwd)"
version="$(cat "$root/.elephc-version")"
[[ "$version" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]] || { echo 'Invalid compiler version' >&2; exit 1; }
case "$(uname -s):$(uname -m)" in
  Linux:x86_64) target=x86_64-unknown-linux-gnu ;;
  Linux:aarch64|Linux:arm64) target=aarch64-unknown-linux-gnu ;;
  Darwin:arm64) target=aarch64-apple-darwin ;;
  *) echo 'Supported hosts: Linux x86_64/ARM64 and macOS ARM64' >&2; exit 1 ;;
esac
[[ $# == 1 && ! -e "$1" ]] || { echo 'Usage: install-elephc.sh NEW_DIRECTORY' >&2; exit 1; }
mkdir -p "$1"
cd "$1"
archive="elephc-${version}-${target}.tar.gz"
gh release download "$version" --repo illegalstudio/elephc --pattern "$archive" --pattern "$archive.sha256"
if command -v sha256sum >/dev/null 2>&1; then sha256sum -c "$archive.sha256"; else shasum -a 256 -c "$archive.sha256"; fi
tar xzf "$archive"
rm "$archive" "$archive.sha256"
test -x elephc
./elephc --version
