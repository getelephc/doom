# Elephant cheat

- [x] Add embedded palette-matched trunk, elephant and impact patches.
- [x] Implement IDELEPHC as a reversible pistol mode with real projectiles.
- [x] Preserve mode and in-flight projectiles in saves and across maps.
- [x] Test firing, collisions, rendering, resets and legacy saves.
- [x] Inspect real-WAD frames and run the native CI matrix.

The pistol slot keeps its bullet ammo and cadence. Other weapons keep their
behavior. Custom art is generated into memory, using the WAD's palette and the
existing sprite clipping, lighting and projectile collision paths. The WAD file
on disk is never modified. Save fingerprints cover only the original WAD bytes;
new sprite/type records are appended so existing save indices stay stable.

## Local validation

The pinned elephc 0.27.6 compiler passes all 98 gameplay tests (832 assertions),
including 10 new elephant regressions, and the 16 release-tooling tests. The
native SDL game builds successfully. Real shareware E1M1 frames were inspected
at rest and during firing, showing the trunk, recoil and flying elephants.

[Native CI run 40700243717](https://github.com/getelephc/doom/actions/runs/40700243717)
passed on Linux x86_64, Linux ARM64 and macOS ARM64 at
`6d84db9f99b195c9c4e16af4c0f70b299b81573a`. The macOS archive also passed its
startup and SDL initialization check after removing the Homebrew SDL kegs.
