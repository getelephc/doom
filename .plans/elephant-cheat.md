# Elephant cheat

- [x] Add embedded palette-matched trunk, elephant and impact patches.
- [x] Implement IDELEPHC as a reversible pistol mode with real projectiles.
- [x] Preserve mode and in-flight projectiles in saves and across maps.
- [x] Test firing, collisions, rendering, resets and legacy saves.
- [ ] Inspect real-WAD frames and run the native CI matrix.

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
