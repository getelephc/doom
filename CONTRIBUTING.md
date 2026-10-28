# Contributing to DOOM

Develop the game in this repository and open issues and pull requests against
[getelephc/doom](https://github.com/getelephc/doom). Compiler and runtime changes
belong in [illegalstudio/elephc](https://github.com/illegalstudio/elephc).

## Local development

Use a dedicated branch for each change and keep commits focused. Commit messages
use lowercase Conventional Commits, for example `fix(combat): block shots through
closed doors`. Do not add co-author trailers.

Install elephc and SDL2, then run from the repository root:

```bash
make check
make test
make
./main
```

The tests generate synthetic WADs and need neither SDL2 nor proprietary assets.
SDL2 and a local WAD are needed to run the game. Make detects Homebrew SDL2 on macOS; use `SDL_LIB_DIR` for a custom library path.

## Changes and validation

Keep the simulation independent of SDL. Add focused synthetic-WAD regressions
for gameplay changes and preserve the fixed 35 Hz simulation. Run the suite and
`git diff --check` before submitting a pull request. CI and releases use the compiler pinned in `.elephc-version`.

The engine is independently written from public format descriptions. Preserve
its MIT licensing approach. Load artwork and music from the player's WAD; do not
commit WADs, generated binaries or save files. The README and `.plans` describe
the supported single-player scope and validation limits.

## Releases

Fast-forward the complete work to `main` and publish it before running
`make release`. The helper validates clean main against GitHub, proposes a
semantic version and asks before creating and pushing an annotated tag. Use
`make release-dry-run` to inspect the operation and `make package VERSION=0.1.0`
to inspect an archive locally. The Release workflow tests all desktop hosts
before publishing archives and checksums. Do not commit WADs or release archives.

macOS packaging stages SDL2 and any non-system dylibs, includes their license
notices and rewrites their load paths before ad-hoc signing. When Homebrew uses
sdl2-compat, SDL3 is bundled explicitly because it is loaded with dlopen. CI
uninstalls the SDL kegs before testing the extracted archive. Linux continues to
use system SDL2. Keep packaging independent of the player's WAD and saves.
