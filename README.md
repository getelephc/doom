<p align="center">
  <img src="https://raw.githubusercontent.com/illegalstudio/elephc/main/assets/logo-mark.png" alt="elephc logo" width="130">
</p>

<h1 align="center">DOOM</h1>

<p align="center">
  <em>Rendered in PHP. Compiled to a native binary.</em>
</p>

<p align="center">
  <a href="https://github.com/getelephc/doom/stargazers"><img src="https://img.shields.io/github/stars/getelephc/doom?style=flat-square&logo=github&logoColor=white&label=stars&color=B91C1C" alt="Stars"></a>
  <a href="https://github.com/illegalstudio/elephc"><img src="https://img.shields.io/badge/built%20with-elephc-B91C1C?style=flat-square" alt="Built with elephc"></a>
  <a href="LICENSE"><img src="https://img.shields.io/github/license/getelephc/doom?style=flat-square&color=B91C1C" alt="License: MIT"></a>
  <a href="https://x.com/nahime0"><img src="https://img.shields.io/badge/Follow-%40nahime0-B91C1C?style=flat-square&logo=x&logoColor=white" alt="Follow @nahime0 on X"></a>
</p>

<p align="center">
  <strong>Pure PHP &middot; 320x200 at 35 tics per second &middot; about 2 ms per frame &middot; only SDL2 at run time</strong>
</p>

<p align="center">
  A DOOM engine for the original shareware <code>DOOM1.WAD</code>, with complete single-player systems and support for registered and Ultimate DOOM WADs, written entirely in PHP and compiled to a standalone native binary by <a href="https://github.com/illegalstudio/elephc">elephc</a>. No interpreter, no VM, no runtime dependencies beyond SDL2.
</p>

<p align="center">
  <a href="https://elephc.dev"><strong>elephc Official Website</strong></a>
</p>

---

<p align="center">
  <img src="https://raw.githubusercontent.com/getelephc/static/main/assets/doom/0.27.6/doom-readme-fluid.gif" alt="DOOM played by the PHP engine compiled with elephc" width="512">
</p>

<p align="center">
  <a href="https://raw.githubusercontent.com/getelephc/static/main/assets/doom/0.27.6/doom-walkthrough.mp4">Download the walkthrough (MP4)</a>
</p>

The engine is written from the public descriptions of the WAD format and of how DOOM draws and moves (the Unofficial DOOM Specs and the DoomWiki), not translated from DOOM's source code.

> This repository is the source of truth for the DOOM engine. Open issues and pull requests here. See [CONTRIBUTING.md](CONTRIBUTING.md) for the development workflow.

## What it does

- Reads the whole WAD into one byte buffer: lump directory, PLAYPAL palettes, COLORMAP light tables, PNAMES and TEXTURE1/TEXTURE2 wall textures composed from their patches, flats, sprites and status bar pictures
- Draws DOOM's own 320x200 8-bit frame and shows it at 4:3 in a 960x720 window
- Renders the BSP front to back one screen column at a time, with DOOM-style vertical clipping: every pixel of the view is written once
- Wall textures with sidedef offsets and upper/lower unpegging, floors and ceilings textured by per-pixel floor casting, the sky where the ceiling is `F_SKY1`
- See-through middle textures on two-sided lines (grates), painted with the sprites from far to near so each covers what lies behind it
- DOOM's light model: sector light bands, fake contrast on axis-aligned walls, darkening with distance through COLORMAP
- Animated flats and walls (nukage, water, lava, dripping slime), scrolling walls, blinking, strobing, glowing and flickering sector lights
- Map objects as sprites with eight rotations and mirroring, clipped by the walls in front of them, with difficulty-based spawning
- Fists, chainsaw, pistol, shotgun, chaingun, rocket launcher, plasma rifle and BFG9000, with distinct ammo, cadence, first-person animations, muzzle flashes and projectile damage
- All original monsters: zombiemen, shotgun guys, imps, demons, spectres, lost souls, cacodemons, barons, cyberdemons and spider masterminds, including flying attacks, infighting and episode boss actions
- Five difficulty settings, reduced damage and doubled ammo on the easiest skill, fast monsters and respawning on Nightmare
- Explosive barrels with delayed chain reactions and damage blocked by walls and closed doors
- Original WAD sound effects mixed into eight stereo voices, plus MUS music with a 32-voice PHP synthesizer, queued through SDL2; the game also works without an audio device
- DOOM's movement at 35 tics per second: thrust, friction and momentum, running, slow-start turning, view bob, smooth steps, gravity
- Collision against the BLOCKMAP: walls, steps over 24 units, openings under 56 units, solid objects
- Original linedef actions: manual/keyed/blazing/timed doors, lifts, floors, slow and turbo stairs, donut floors, crushers, light controls, switches, gun triggers and player/monster teleporters; objects ride moving surfaces
- Health, armor, all ammo and keys, backpacks, berserk, invulnerability, invisibility, radiation suits, light amplification and computer maps; damaging floors and palette flashes
- The status bar with the WAD's own digits, weapon panel, keys and the marine's face
- The automap (Tab), discovered lines, computer maps and cheat-controlled full-map/object views
- Title, pause, episode/difficulty, options, help and six save/load slots, with quicksave and quickload
- Mouse turning and firing, adjustable sound/music volume and always-run
- Classic typed cheats, including god mode, weapons/ammo/keys, noclip, powers, map warp and music selection
- Exits show the original WAD's intermission art with kills, items, secrets and elapsed time; confirm to continue to the next map (all available episodes, with their secret-map return routes and episode endings)
- Death lowers the view and waits for a fresh confirmation before restarting with a new marine
- Frame rate and render time per frame on screen, in the window title and on stdout

## elephc features used

- `buffer<u8>` for the WAD file, the 8-bit frame and the composed textures; `buffer<u32>` for the ARGB frame handed to `SDL_UpdateTexture` through `buffer_ptr()`
- `packed class` records in `buffer<T>` for map geometry, objects, movers and light effects
- `extern "SDL2" { ... }` for the window, the texture and the keyboard; `file_get_contents()` and `ptr_write_string()` copy the WAD into its byte buffer
- `buffer_free()` when a map is left: buffers are manually managed, so each map's records, objects and specials are released on the next map change
- `elephc test` for the showcase's own test suite
- Typed integer locals and buffers keep the pixel-writing loops away from boxed `mixed` values

## Build

Requires [elephc](https://github.com/illegalstudio/elephc#install), SDL2 and a local copy of `DOOM1.WAD` (shareware), or your registered/Ultimate `DOOM.WAD`. From this directory:

```bash
# compile
elephc -l SDL2 --heap-size=134217728 main.php

# run
./main
```

On macOS with Homebrew, add `-L /opt/homebrew/lib` if SDL2 is outside the default library search path. Build and run from this repository's root.

```bash
./main /path/to/DOOM.WAD       # title and episode menu
./main /path/to/DOOM.WAD E2M1  # start directly on a map
```

Shareware contains the nine maps of episode 1 and omits the plasma/BFG weapon art. Those weapons are available when the loaded WAD supplies their art. Original commercial assets are not bundled or downloaded by the game.

Download the shareware WAD: search for "DOOM1.WAD shareware" or extract it from the [original shareware release](https://doomwiki.org/wiki/DOOM1.WAD). Place it in this directory.

## Tests

```bash
elephc test tests --heap-size=134217728
```

The suite needs neither SDL nor `DOOM1.WAD`: it writes a small WAD of its own (two rooms joined by a grate, enemies, barrels, weapon pictures, PCM samples and light tables that keep every color). It checks every weapon and monster family, cheats, power-ups, difficulty, boss actions, sound propagation, projectiles, explosions, stereo mixing, MUS parsing, map changes, stairs, donut floors, crushers, teleporters, pickups, save validation, menu pause and rendered frames. The larger synthetic full-asset fixture requires the explicit 128 MiB heap above. A complete level-flow test fires the weapon, walks across the map, uses the exit and confirms the intermission. This repository's CI runs it against the elephc nightly on pushes and pull requests.

## Controls

| Key | Action |
|-----|--------|
| W / Up | Move forward |
| S / Down | Move backward |
| A | Strafe left |
| D | Strafe right |
| Left / Right arrow | Turn |
| Shift | Run |
| Ctrl / Left mouse | Fire |
| Mouse | Turn |
| 1 | Fists / chainsaw (press again to switch) |
| 2 / 3 / 4 / 5 / 6 / 7 | Pistol / shotgun / chaingun / rockets / plasma / BFG (when owned) |
| Space / E | Use (doors, switches) |
| Enter / Space / Ctrl | Continue after the intermission or restart after death (release and press again) |
| Tab | Automap |
| V | Toggle vsync (off shows the uncapped frame rate) |
| Esc | Pause / menu / back |
| F1 | Help |
| F2 / F3 | Save / load menu |
| F6 / F9 | Quicksave / quickload (last slot, initially slot 1) |
| Arrows / Enter | Navigate / select menu items |

## Cheats and saves

Type cheat codes during play, without a console, then release movement keys as needed.

| Code | Effect |
|------|--------|
| `IDDQD` | Toggle god mode |
| `IDFA` / `IDKFA` | Available weapons, full ammo and armor / also all keys |
| `IDCHOPPERS` | Chainsaw |
| `IDSPISPOPD` / `IDCLIP` | Toggle noclip |
| `IDBEHOLDV/S/I/R/A/L` | Toggle invulnerability / berserk / invisibility / suit / computer map / light |
| `IDDT` | Cycle automap: discovered / full / full with objects |
| `IDCLEVxy` | Warp to episode x, mission y, if present in the WAD |
| `IDMUSxy` | Select an available episode/mission music track |
| `IDMYPOS` | Toggle position display |

Saves live in `saves/slot0.json` through `slot5.json`, relative to the launch directory.
They preserve player inventory and powers, enemies/projectiles, map changes, movers,
statistics, elapsed time and combat randomness. A version and WAD SHA-256 reject
incompatible saves before changing the active game. These are this engine's own
save files. Quicksave uses the last successfully saved/loaded slot.

## Architecture

```
main.php                 Entry point
src/
  bootstrap.php          Module loader: the engine plus SDL and the window
  engine.php             Module loader for everything but SDL (what the tests load)
  App/
    Application.php      Top-level shell
    Config.php           Window and frame size, WAD path, tic rate, vsync
    Session.php          WAD, current map, objects, specials, player and renderer; map changes and game tics
    Game.php             Window, game loop at 35 tics per second, stats
    Menu.php             Title, pause, episode/skill/options and save/load navigation
    SaveGame.php         WAD-bound versioned saves, validation and atomic writes
    SaveRecords.php      Explicit packed-record snapshot schema
    GameState.php        Enum: Booting, Running, Stopped
  SDL/
    extern.php           SDL2 FFI declarations
    SDL.php              Window, renderer, streaming texture, vsync
    Input.php            Keyboard and relative mouse state
    Events.php           SDL-free event record and text decoding
    Audio.php            SDL2 queued audio output, with no callback into PHP
  Audio/
    Sounds.php           WAD PCM loading, resampling and bounded stereo mixing
    Music.php            MUS event decoder and independent polyphonic synthesizer
  Wad/
    Wad.php              Whole-file byte buffer, lump directory, little-endian reads
  Graphics/
    Palette.php          PLAYPAL palettes and COLORMAP
    Textures.php         Wall textures composed from patches
    Flats.php            Floor and ceiling flats
    Sprites.php          Sprite frames and rotations
    Animations.php       Cycling textures and flats
  Map/
    MapData.php          One map's records and BLOCKMAP
    MapLoader.php        Lump parsing and resolution of textures, flats and sectors
  Bsp/
    BspWalker.php        Front-to-back traversal and point-in-subsector lookup
  Render/
    Renderer.php         One frame: 3D view or automap, status bar, overlay
    BspRenderer.php      Clipped column renderer for walls, flats, sky and see-through middle textures
    SpriteRenderer.php   Scaled, rotated and clipped sprites
    LightTables.php      Light bands and distance fading
    Screen.php           The 8-bit frame, patches, lines, palette conversion
    Hud.php              Status bar, face, messages, FPS text
    Automap.php          The map view
    WeaponRenderer.php   First-person weapon patches and muzzle flashes
    Intermission.php     Finished-level artwork and statistics
    Finale.php           Episode ending artwork and captions
  Play/
    Player.php           Movement physics, use, damage and power timers
    Inventory.php        Weapons, ammo limits and pickup/power-up effects
    Cheats.php           Incremental typed cheat recognition
    Collision.php        BLOCKMAP collision and use traces
    Specials.php         Doors, lifts, floors, stairs, switches, teleporters, lights
    LineActions.php      Original linedef trigger/action catalog
    AdvancedSpecials.php Target calculations, donut floors and ceilings
    Things.php           Spawned objects and pickups
    ThingTypes.php       Sprite, size and behavior per thing type
    Combat.php           Damage, drops, hearing, statistics and combat RNG
    CombatTrace.php      Wall openings, sight and hitscan targeting
    Weapons.php          Weapon selection, ammo and firing cadence
    MonsterAI.php        Sight, chase, attacks, pain, death and respawning
    MonsterRules.php     Monster stats, animation frames and sound profiles
    Projectiles.php      Swept projectiles, splash damage and BFG spray
  Data/                  packed classes: map records, Mobj, Mover, LightEffect, Button
  Support/
    Trig.php             Fine-angle sine and cosine tables
tests/
  Fixture/TestWad.php    The WAD the tests write: two rooms, a grate, sprites, identity light tables
  Fixture/SessionCase.php  Boots a Session on that WAD
  *Test.php              Map changes, specials, pickups, rendering
```

## Performance notes

A frame takes about 2 ms on a desktop x86_64 CPU, mostly spent writing floor, wall and sprite pixels. The per-frame code keeps to the shapes elephc compiles to plain machine integers; loading code is written freely:

- Loops count with `$i++` from a local, against a bound held in an `int` local or written as `$n - 1`; `$i += 1`, `$i = $i + 1`, `<=` and `$n + 1` bounds keep the counter boxed, so inclusive ranges use an `int $end = $last + 1;` bound, and a loop starting at `$start + 1` declares that start as an `int` local first.
- Integer arithmetic is written one operation per typed declaration (`int $p = $a * $b;`), and locals are reassigned only from other locals, constants or call results. A nested operation, an arithmetic call argument or an arithmetic reassignment may overflow to float, so elephc boxes it.
- Hot calls pass only ints and buffers: an object argument costs about 20 ns per call, ten times an int-only call.

## Compatibility

This is an independently implemented single-player DOOM engine. It supports the
original weapon/monster catalog and E1M1 through E4M9 when supplied by the WAD.
DOOM II content, multiplayer, vanilla demos and original binary save files are
outside its scope. The music follows the WAD's MUS notes and timing with a small
software synthesizer; its instrument sound differs from the original OPL hardware.
Movement, animation, AI and rendering are independently implemented approximations,
so this is not a bit-exact replacement for the original executable.

Regression tests use synthetic assets for the entire catalog. Real-WAD checks use
the shareware episode; registered and Ultimate commercial WADs require further
playthrough validation with the user's own files.
