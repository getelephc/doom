# Standalone CI and releases

- [x] Fast-forward local main to the complete single-player branch.
- [x] Add Make commands and a confirmed semantic-version tag publisher.
- [x] Configure builds and tests for the three desktop hosts with a pinned compiler.
- [x] Package binaries, provenance and checksums, then publish on version tags.
- [x] Verify scripts, packaging and native gameplay; document the maintainer flow.
- [ ] Commit and publish main, and verify its CI run without creating a release tag.

The release flow follows the local ggw Makefile and scripts/release.sh pattern.
`make release` prepares an annotated tag after checking clean, published main;
the tag-triggered workflow builds and publishes the assets. This task configures
the flow and publishes main, without choosing or publishing a first game version.

Pin elephc v0.27.6, the stable release that passed the existing gameplay suite.
Use Linux x86_64, Linux ARM64 and macOS ARM64 runners. These correspond to the
compiler's desktop targets and published compiler archives. GitHub documents
`ubuntu-24.04-arm` and `macos-15` as ARM64 runner labels:
https://docs.github.com/en/actions/how-tos/write-workflows/choose-where-workflows-run/choose-the-runner-for-a-job

Archives contain the executable, README, license and build provenance. WADs and
saves stay local. SDL2 remains a separately installed runtime dependency. macOS
archives use ad-hoc signing and do not require unavailable signing secrets.

## Local verification

- The official elephc v0.27.6 Linux archive passed its published SHA-256 check.
- Its compiler passed all 88 gameplay tests (775 assertions), built the native
  game and produced a checked archive containing only the four intended files.
- Release tooling tests exercise annotated tag creation/push in disposable bare
  repositories, cancellation, dirty/wrong/unpublished main, invalid versions,
  retained tags after push failure, archive contents and installer checksum failure.
- ShellCheck and actionlint pass. A finite SDL dummy-driver run verified the title
  and MUS music with the pinned release compiler.
