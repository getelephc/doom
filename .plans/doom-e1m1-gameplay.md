# Playable DOOM E1M1

Original Elephc baseline: [`ba51897067`](https://github.com/illegalstudio/elephc/commit/ba51897067).
Historical Elephc Code Journal plan: `575c3f18`.

- [x] Add deterministic combat traces, damage, pistol, shotgun and fist fallback.
- [x] Add active E1M1 enemies, attack and death animations, drops and exploding barrels.
- [x] Draw first-person weapons and add keyboard controls.
- [x] Play WAD sound effects through a bounded PHP mixer and SDL2 queued audio.
- [x] Add death/restart and an exit intermission with level statistics and continuation.
- [x] Cover combat, collision, actors, audio and complete level flow with synthetic-WAD tests.
- [x] Validate the SDL build and real shareware WAD, document controls and limitations.

## Scope and design

The user approved E1M1 playable from its start through its exit. Keep the independently
written PHP engine and its MIT licensing approach. Use public format descriptions,
without reading or translating GPL engine implementations. Retain the fixed 35 Hz
simulation and every existing map/rendering feature. This milestone includes pistol,
shotgun, zombiemen, shotgun guys, imps and barrels; it does not claim vanilla demo
compatibility, full-episode combat, music, savegames or multiplayer.

Keep simulation independent of SDL. Share wall/opening traces between hitscan, sight,
projectiles and blast visibility. Store actor state in packed records and reuse finite
object slots for drops/projectiles. Cache sprite definitions outside rendering loops.
Use a separate combat random stream so rendering and HUD calls cannot alter combat.

Sound effects are mixed from WAD PCM into stereo samples in PHP and queued from the
main thread, with no native callback into generated code. A missing audio device must
leave the game playable. Synthetic WADs supply combat sprites, weapon patches and sound
samples so CI needs neither SDL nor proprietary assets. Real-WAD validation remains a
separate local smoke check.

An exit freezes simulation and displays kills, items, secrets and elapsed time. A fresh
confirm press continues to the next map; death waits for a fresh restart press instead
of immediately replacing the level.

## Validation

- The synthetic-WAD suite covers the complete keyboard-driven level flow, combat,
  sight/hearing, projectiles, explosions, drops, stereo PCM and existing rendering,
  pickups, specials and repeated map changes.
- A temporary automated route on the checksum-verified shareware 1.9 WAD traversed
  E1M1 from spawn, used its doors, collected the shotgun, killed all six skill-3
  enemies and activated the real exit linedef 330. It reached intermission with
  78 health after 1068 simulation tics, without invulnerability or teleporting.
  The route planner temporarily opened doors and hid actors only while finding a
  path; their original state was restored before the simulation started.
- SDL dummy video/audio opened successfully. Captured start and intermission
  frames were inspected; measured rendering was about 1.7 ms on this Linux host.
- Final synthetic suite: 44 tests, 579 assertions, all passing. The final SDL
  executable builds without warnings. Audio-device failure leaves the game
  running normally. Assembly emission succeeds for all five supported targets;
  iOS uses the library output mode. Only Linux was executed locally.
