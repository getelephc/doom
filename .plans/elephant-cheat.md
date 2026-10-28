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

## Visual revision

- [x] Redraw the weapon as a low, curved trunk aimed forward from the lower right.
- [x] Add an elephant HUD portrait while IDELEPHC is active.
- [x] Cover glances, five health bands, firing, damage, pickups, god mode and death.
- [x] Check portrait bounds, toggle restoration, saves and real E1M1 frames.

The portrait patches are appended after the original custom weapon/projectile
patches and use names excluded from the sprite-frame catalog. This keeps existing
saved projectile definitions stable. Expressions use the player's existing state;
no new save fields or random stream are needed. Five new HUD regressions cover
the expression priorities, health bands, frame bounds, save restoration and the
trunk's low silhouette. E1M1 idle/firing frames and a portrait sheet were inspected.
The two elephant test classes pass locally (15 tests, 88 assertions), and the
native SDL executable was rebuilt with this revision.
