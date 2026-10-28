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
elephc test tests --heap-size=134217728
elephc -l SDL2 --heap-size=134217728 main.php
./main
```

The tests generate synthetic WADs and need neither SDL2 nor proprietary assets.
SDL2 and a local WAD are needed to run the game. On macOS with Homebrew, add
`-L /opt/homebrew/lib` to the build command if needed.

## Changes and validation

Keep the simulation independent of SDL. Add focused synthetic-WAD regressions
for gameplay changes and preserve the fixed 35 Hz simulation. Run the suite and
`git diff --check` before submitting a pull request. CI uses the elephc nightly.

The engine is independently written from public format descriptions. Preserve
its MIT licensing approach. Load artwork and music from the player's WAD; do not
commit WADs, generated binaries or save files. The README and `.plans` describe
the supported single-player scope and validation limits.
