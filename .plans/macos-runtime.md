# Portable macOS runtime

- [x] Confirm the published archive's non-system load paths.
- [x] Bundle SDL2 and any non-system dylib dependencies with relocatable names.
- [x] Include dependency license notices and sign the staged libraries/executable.
- [x] Add regression tests and run the extracted archive after uninstalling SDL2 in CI.
- [x] Update download instructions and validate all three native builds.

The v0.1.0 macOS archive links SDL2 from the Homebrew build host. The previous
CI checked architecture and packaging but kept SDL2 installed, so it did not
exercise a clean user's runtime environment.

Use @loader_path-relative load names inside the archive. Keep system frameworks
and /usr/lib dependencies supplied by macOS. Stage copies before changing load
commands or signatures. Third-party notices travel with bundled dylibs. Linux
keeps its documented system SDL2 dependency in this change.

## Reproduction and implementation

The downloaded v0.1.0 archive passed its checksum, and llvm-otool confirmed the
only non-system direct import was
`/opt/homebrew/opt/sdl2-compat/lib/libSDL2-2.0.0.dylib`.
Homebrew marks SDL3 as a no-linkage dependency; sdl2-compat's macOS loader first
tries `@loader_path/libSDL3.dylib`, so the runtime stages that sibling explicitly.

Local tooling validation passes 16 regressions, including transitive dylibs,
@rpath resolution, SDL3's dynamic dependency, notices, signatures and preservation
of the original build outputs. CI removes installed SDL2/SDL3 kegs, runs the
extracted binary from another directory, and initializes bundled SDL video/audio.

## Validation

[CI run 40688932965](https://github.com/getelephc/doom/actions/runs/40688932965)
passed on macOS ARM64, Linux ARM64 and Linux x86_64 at
`4b7d387fd420854f063c756df37fe507eeeae23d`. Each host passed 88 gameplay
tests (775 assertions) and 16 tooling tests. The macOS smoke test passed after
uninstalling both sdl2-compat and SDL3. The downloaded artifact's SHA-256 matched
its checksum; all non-system Mach-O imports resolve inside the archive, which
contains both SDL libraries and their license notices.

The initial smoke test exposed macOS's `/var` to `/private/var` alias. Extraction
now resolves its destination before containment checks, with regressions for
aliased directories, traversal and archive symlinks.

The corrected development archive is available from that CI run. The published
v0.1.0 release predates this fix and has not been replaced.
