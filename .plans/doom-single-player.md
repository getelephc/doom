# Complete DOOM single player

- [x] Implement the full original weapon set, ammo, projectiles and first-person art.
- [x] Add classic typed cheats, including god mode, noclip, weapons/ammo/keys and warp.
- [x] Complete power-ups, backpack limits, automap and lighting effects.
- [x] Implement all original DOOM monsters, difficulty rules and boss progression.
- [x] Complete line/sector specials and episode endings.
- [x] Add title/pause/new-game/options menus, mouse controls and save/load slots.
- [x] Play WAD MUS music through the PHP audio mixer.
- [x] Add focused synthetic-WAD regressions and verify real shareware maps and SDL.
- [x] Document controls, asset availability and compatibility limits; build the runnable game.

## Accepted scope

The user explicitly selected complete single player. Support the original DOOM
weapon and monster catalog, the nine-map shareware episode, and registered/Ultimate
DOOM episodes when the user supplies their WAD. Multiplayer and DOOM II additions
are outside this scope. Preserve the independently written PHP engine and MIT
approach; do not read or translate GPL engine implementations.

Keep simulation SDL-free. Input text is supplied through the event adapter and is
also directly injectable in tests. Cheats use the same damage/inventory/movement
paths as ordinary gameplay. Saves are versioned, bound to the loaded WAD and fully
validated before restoring live state. Music reads the original MUS event stream
and uses an independent PHP software synthesizer; do not claim bit-exact OPL audio,
vanilla demo compatibility or binary compatibility with original save files.

All original weapon mechanics must work in synthetic tests. Grant/select only
weapons whose art is present in the loaded WAD, with a clear message for unavailable
registered content in shareware. Registered assets are never downloaded or bundled.

The user explicitly waived queue waits for this task on 2026-10-27: run this
agent's tests directly. Other tasks retain their existing build-queue policy. Keep the existing
44 E1M1 tests passing while extending their fixture vocabulary. Validate source on all
supported emitters and execute locally on Linux; leave the wider host matrix to CI.

## Validation completed on 2026-10-27

- `elephc test tests --heap-size=134217728`: 88 tests,
  775 assertions, no failures. CI uses the same explicit heap budget.
- Native Linux SDL smoke: queued stereo audio, quit event polling, typed-event
  decoding into `IDDQD`, and a finite three-second game loop with music at 35 Hz.
- Shareware WAD SHA-256:
  `1d7d43be501e67d927e415e0b8f3e29c3bf33075e859721816f652a526cac771`.
  All nine E1 maps loaded, rendered, played music, advanced 70 tics and restored
  their snapshots; their active linedef specials all had handlers. Snapshots
  ranged from 24,819 to 91,766 bytes.
- Automated episode progression exercised exits/intermissions through E1M1,
  E1M2, E1M3, E1M9, E1M4, E1M5, E1M6, E1M7 and E1M8, then killed the last baron
  and exercised the end sector into the finale. This was a scripted progression
  check, not a claim of a manual combat playthrough on every map.
- Main-menu and gameplay frames were visually inspected. A headless SDL run
  measured 324-354 FPS with music, with a 2.22-2.43 ms render time on this host.
- Assembly emission passed for linux-x86_64, linux-aarch64, macos-aarch64,
  ios-arm64 and ios-sim-arm64. The runnable Linux executable was rebuilt.
- Registered/Ultimate assets were unavailable locally. Their weapon, monster and
  boss mechanics have synthetic-fixture coverage; commercial map playthroughs
  remain a validation boundary, as documented in the showcase README.

The installed SDL2 compatibility layer crashes when a synthetic SDL2_TEXTINPUT
is passed to SDL_PushEvent, because it forwards a null conversion to SDL3. Native
smoke therefore uses the same SDL-free event decoder directly for text and polls
an actual queued quit event. Normal event polling does not take that injection
path. No system libraries were changed.
