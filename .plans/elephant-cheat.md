# Elephant cheat

- [x] Add embedded palette-matched rear-head, elephant and impact patches.
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

The initial implementation passed 98 gameplay tests (832 assertions), including
10 elephant regressions, and the 16 release-tooling tests with elephc 0.27.6.

[Native CI run 40700243717](https://github.com/getelephc/doom/actions/runs/40700243717)
passed on Linux x86_64, Linux ARM64 and macOS ARM64 at
`6d84db9f99b195c9c4e16af4c0f70b299b81573a`. The macOS archive also passed its
startup and SDL initialization check after removing the Homebrew SDL kegs.

## Visual revision

- [x] Show a small rear-view elephant head low in the view, with broad flapping ears.
- [x] Add an elephant HUD portrait while IDELEPHC is active.
- [x] Cover glances, five health bands, firing, damage, pickups, god mode and death.
- [x] Check portrait bounds, toggle restoration, saves and real E1M1 frames.

The portrait patches are appended after the original custom weapon/projectile
patches and use names excluded from the sprite-frame catalog. This keeps existing
saved projectile definitions stable. Expressions use the player's existing state;
no new save fields or random stream are needed. Five new HUD regressions cover
the expression priorities, health bands, frame bounds, save restoration and the
weapon's low silhouette. Real E1M1 idle/firing frames and a portrait sheet were
inspected. The two elephant test classes pass locally (15 tests, 93 assertions),
and the native SDL executable was rebuilt with this revision.

The weapon shows only the back of a rounded head and its ears. Its existing lump
names and frame order are preserved so saved sprite references remain stable.
The placement regression checks both ears and leaves the upper view clear.

## Feeding mode

- [x] Replace all monster families with walking and chewing elephant art.
- [x] Replace the slot 2 elephant projectiles with leafy carrots and food crumbs.
- [x] Animate full elephants tipping over and sleeping with breathing and `zZzZ`.
- [x] Settle full flying elephants onto the floor and suppress pain/death sounds.
- [x] Preserve drops, statistics, boss exits, Nightmare waking and reversible toggles.
- [x] Keep the original actor and sprite indices in saves, appending new art last.
- [x] Run feeding regressions and the complete suite, then inspect real-WAD frames.

Feeding reuses the combat health budget as remaining appetite and the existing
pain/death states as chewing/flopping/sleeping. Only displayed monster frames are
substituted, so toggling the cheat restores original art immediately and legacy
saves retain their definitions. The existing ELPH frames now contain carrots and
crumbs. New ELEN frames are appended after all existing built-in patches. Sleep
letters are part of the actor patch and share its wall clipping and lighting.
Other weapons and difficulty rules retain their mechanics. The contact attack
revision below replaces monster ranged attacks while the mode is active.

Local validation with elephc 0.27.6 passed all 108 gameplay tests (910 assertions),
including the five new feeding regressions, all 16 release-tooling tests and the
native SDL build. Real shareware E1M1 standing, chewing and sleeping frames were
rendered, and the standing/sleeping views and art sheet were visually inspected.
This revision has not been run through the remote native CI matrix.

## Human feeder

- [x] Replace the rear head with a human hand gripping a carrot.
- [x] Animate an empty-hand throwing pose and recovery with the next carrot.
- [x] Replace the player portrait with an original human wearing an unmarked cap.
- [x] Preserve expression priorities, health bands, portrait bounds and saved indices.
- [x] Validate the revised silhouettes, portrait expressions and native real-WAD frames.

ELTG and ELFACE patch names and ordering stay stable, while their pixels now show
the human player. FeederFaces supplies all 32 portrait slots. The first-person
hand uses the existing three animation frames and keeps the upper view clear.
The palette adds skin, hair and clothing shades after the existing herd/food ramp.

The revised gameplay suite passes locally with 109 tests and 913 assertions using
elephc 0.27.6. The native SDL executable was rebuilt, and real shareware E1M1
ready, throwing and recovery views plus the human portrait/hand sheet were
rendered. The ready view and complete portrait/hand sheet were visually checked.

## Contact attacks

- [x] Make all elephant families chase and attack only at body contact.
- [x] Check body radii, vertical overlap and line of sight before dealing damage.
- [x] Cancel pending ranged windups and flying charges when the mode is active.
- [x] Retire hostile projectiles before direct or splash damage, preserving food.
- [x] Shorten the final chase step so large, fast actors can reach contact.
- [x] Validate all families, escaped targets, walls, heights, toggles and native build.

Contact attacks deal 4 to 12 damage after a short windup, followed by a cooldown.
Nightmare retains its faster timing. Target positions are checked again when
the hit lands, allowing the player to escape. The original monster attacks return
when IDELEPHC is disabled, and the existing save schema is unchanged.

Local validation passed all 115 gameplay tests (1001 assertions), including six
new contact regressions, all 16 tooling tests and the native SDL build with
elephc 0.27.6. Normal monster attacks and the existing feeding/portrait/save tests
remain covered by the same complete suite.

## Occasional charges

- [x] Give each chasing elephant a staggered chance to charge occasionally.
- [x] Show a 14-tic stationary warning with raised trunk art and a sound cue.
- [x] Lock the dash direction, sweep movement in two-unit steps, and stop on obstacles.
- [x] Deal one 16-point contact hit, then recover for three seconds.
- [x] Interrupt charges when fed, when full, or when the mode is disabled.
- [x] Reuse saved actor state, countdown, damage and velocity fields without a schema change.
- [x] Validate warning timing, dodges, collision, damage, all families and save continuation.
- [x] Inspect native warning/dash art and rebuild the SDL executable.

Each eligible actor gets a 25% chance every 70 tics, staggered by object index,
when its target is visible, outside contact range and within 640 map units.
The windup lasts 14 tics; the locked dash lasts at most 24 tics at 24 units per
tic. There is no steering during the windup or dash. Walls, actors and map
openings use normal collision, while crossed monster specials still apply.
Food cancels the charge through the existing pain/fullness transition.

State 8 with hitDamage 16 distinguishes the feeding-mode dash from legacy lost
soul charges. Existing saved velocity/countdown fields preserve both phases.
New warning pictures follow the original nine ELEN frames, preserving saved
sprite definitions. Original-mode lost soul charges retain their behavior.

Local validation with elephc 0.27.6 passed all 122 gameplay tests (1092 assertions),
including seven charge regressions, all 16 tooling tests and the native SDL build.
Native real shareware E1M1 warning and dash frames and the complete herd art sheet
were rendered and visually inspected. No remote native CI run was requested.
