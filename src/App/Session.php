<?php

namespace Showcases\Doom\App;

use Showcases\Doom\Bsp\BspWalker;
use Showcases\Doom\Graphics\Flats;
use Showcases\Doom\Graphics\Palette;
use Showcases\Doom\Graphics\ElephantArt;
use Showcases\Doom\Graphics\Sprites;
use Showcases\Doom\Graphics\Textures;
use Showcases\Doom\Map\MapData;
use Showcases\Doom\Map\MapLoader;
use Showcases\Doom\Play\Collision;
use Showcases\Doom\Play\Player;
use Showcases\Doom\Play\Specials;
use Showcases\Doom\Play\Things;
use Showcases\Doom\Play\Combat;
use Showcases\Doom\Play\Weapons;
use Showcases\Doom\Play\Cheats;
use Showcases\Doom\Audio\Sounds;
use Showcases\Doom\Render\Renderer;
use Showcases\Doom\SDL\Input;
use Showcases\Doom\Support\Trig;
use Showcases\Doom\Wad\Wad;

// One game: the WAD's pictures, the current map with its objects and specials,
// the marine, and the renderer that draws them into an 8-bit frame. Nothing here
// touches SDL, so the tests drive it directly; Game adds the window and the clock.
final class Session {
    public Config $config;
    public Trig $trig;
    public Wad $wad;
    public Palette $palette;
    public Textures $textures;
    public Flats $flats;
    public Sprites $sprites;
    public MapLoader $mapLoader;
    public MapData $map;
    public Things $things;
    public BspWalker $locator;
    public Collision $collision;
    public Specials $specials;
    public Player $player;
    // created by boot() once the WAD's pictures are loaded
    public Renderer $renderer;
    public string $mapName;
    public int $levelTime;
    public Sounds $sounds;
    public Combat $combat;
    public Weapons $weapons;
    public Cheats $cheats;
    public Menu $menu;
    public SaveGame $saves;
    public bool $combatReady;
    // 0 playing, 1 dead, 2 intermission. Confirmation always needs a fresh press.
    public int $phase;
    public int $phaseTics;
    public bool $confirmHeld;
    public string $pendingMap;

    public function __construct(Config $config) {
        $this->config = $config;
        $this->trig = new Trig();
        $this->wad = new Wad();
        $this->palette = new Palette();
        $this->textures = new Textures();
        $this->flats = new Flats();
        $this->sprites = new Sprites();
        $this->mapLoader = new MapLoader();
        // an empty map until the first loadMap() replaces (and releases) it
        $this->map = new MapData("", -1);
        $this->things = new Things();
        $this->locator = new BspWalker();
        $this->collision = new Collision($this->map, $this->locator, $this->things);
        $this->specials = new Specials($this->map, $this->collision, $this->things, $this->textures, $this->wad, $this->trig);
        $this->player = new Player();
        $this->mapName = "";
        $this->levelTime = 0;
        $this->sounds = new Sounds($this->wad);
        $this->weapons = new Weapons();
        $this->cheats = new Cheats();
        $this->menu = new Menu();
        $this->saves = new SaveGame();
        $this->combatReady = false;
        $this->phase = 0;
        $this->phaseTics = 0;
        $this->confirmHeld = false;
        $this->pendingMap = "";
    }

    // Loads the WAD and its pictures, builds the renderer and enters the startup map.
    public function boot(): bool {
        string $wadPath = $this->resolveWadPath();
        if ($wadPath === "") {
            $this->say("No WAD file at " . $this->config->wadPath);
            return false;
        }
        if (!$this->wad->load($wadPath)) {
            $this->say("Failed to load WAD: " . $wadPath);
            return false;
        }
        $this->say("Loaded WAD: " . $this->wad->kind . " | lumps: " . $this->wad->lumpCount);
        if (!$this->palette->load($this->wad)) {
            $this->say("The WAD lacks PLAYPAL, textures, flats or sprites");
            return false;
        }
        ElephantArt $art = new ElephantArt();
        $art->install($this->wad, $this->palette);
        if (!$this->textures->load($this->wad) || !$this->flats->load($this->wad) || !$this->sprites->load($this->wad)) {
            $this->say("The WAD lacks PLAYPAL, textures, flats or sprites");
            return false;
        }
        $this->say("Graphics: " . $this->textures->count . " textures, " . $this->flats->count . " flats, " . $this->sprites->count . " sprite frames");
        $this->renderer = new Renderer($this->wad, $this->palette, $this->textures, $this->flats, $this->sprites, $this->trig, $this->config->renderWidth, $this->config->renderHeight);
        $this->sounds->load();
        $this->player->availableWeapons = $this->renderer->weapons->available;
        return $this->loadMap($this->config->startupMap);
    }

    // Switches to map $name. Buffers are not reclaimed on their own, so the previous
    // map, its objects, collision data and specials are released here, once the
    // renderer and the new objects no longer read them.
    public function loadMap(string $name): bool {
        MapData $map = $this->mapLoader->load($this->wad, $this->textures, $this->flats, $name);
        if (!$map->isValid()) {
            $map->release();
            $this->say("Map " . $name . " not found or incomplete");
            return false;
        }
        MapData $previous = $this->map;
        if ($this->combatReady) {
            $this->combat->release();
        }
        $this->map = $map;
        $this->mapName = $name;
        $this->levelTime = 0;
        $this->renderer->useMap($map);
        $this->things->skill = $this->config->skill;
        $this->player->skill = $this->config->skill;
        $this->things->spawn($map, $this->sprites, $this->wad, $this->trig, $this->locator);
        $this->collision->release();
        $this->collision = new Collision($map, $this->locator, $this->things);
        $this->specials->release();
        $this->specials = new Specials($map, $this->collision, $this->things, $this->textures, $this->wad, $this->trig);
        $previous->release();
        int $angle = $this->trig->fromDegrees($map->playerStartAngle);
        int $floor = $this->floorAt($map->playerStartX, $map->playerStartY);
        $this->player->spawn($map->playerStartX, $map->playerStartY, $angle, $floor);
        $this->player->keys = 0;
        $this->player->invulnerable = 0; $this->player->invisible = 0;
        $this->player->suit = 0; $this->player->lightAmp = 0;
        $this->player->berserk = false; $this->player->fullMap = false;
        $this->player->damageCount = 0; $this->player->bonusCount = 0;
        $this->player->useHeld = true;
        $this->combat = new Combat($this->things, $this->player, $this->collision, $this->specials, $this->trig, $this->sounds, $this->sprites, $this->wad);
        $this->combat->skill = $this->config->skill;
        $this->combatReady = true;
        $this->sounds->stop();
        $this->sounds->music->stop();
        $this->startMapMusic();
        int $episode = (int)substr($name, 1, 1);
        int $sky = $this->textures->find($this->wad->key("SKY" . ($episode === 4 ? 4 : $episode)));
        if ($sky >= 0) { $this->renderer->world->skyTexture = $sky; }
        $this->phase = 0;
        $this->phaseTics = 0;
        $this->player->weaponCooldown = 0;
        $this->renderer->hud->say($name);
        $this->say($map->summary() . " | " . $this->things->count . " objects");
        return true;
    }

    // One game tic: the marine, the specials, pickups, then death, teleports and exits.
    public function runTic(Input $input, ptr $keys): void {
        if ($this->menu->handle($this, $input, $keys)) { return; }
        $this->player->angle = ($this->player->angle - $input->mouseX * 8) & 8191;
        $input->mouseX = 0;
        bool $confirm = $input->confirm($keys) !== 0 || $input->fire($keys) !== 0;
        if ($this->phase !== 0) {
            $this->phaseTics++;
            if ($this->phase === 1) {
                int $floorView = ($this->player->unitZ() + 8) << 16;
                $this->player->viewZ = max($floorView, $this->player->viewZ - 65536);
                $this->player->damageCount = max(0, $this->player->damageCount - 1);
                $this->player->bonusCount = max(0, $this->player->bonusCount - 1);
            }
            if ($confirm && !$this->confirmHeld && $this->phaseTics >= 35) {
                if ($this->phase === 1) {
                    $this->player->reset();
                    $this->loadMap($this->mapName);
                } else if ($this->phase === 2) {
                    $this->loadMap($this->pendingMap);
                } else {
                    $this->menu->page = 1;
                }
            }
            $this->confirmHeld = $confirm;
            return;
        }
        if ($this->player->health <= 0) {
            $this->die($confirm);
            return;
        }
        int $oldHealth = $this->player->health;
        int $oldSecrets = $this->combat->secrets;
        $this->specials->playerX = $this->player->unitX();
        $this->specials->playerY = $this->player->unitY();
        $this->specials->playerKeys = $this->player->keys;
        $this->player->tick($input, $keys, $this->collision, $this->specials, $this->trig, $this->levelTime);
        $this->specials->playerX = $this->player->unitX();
        $this->specials->playerY = $this->player->unitY();
        $this->specials->tick();
        $this->weapons->tick($this->player, $this->combat, $input, $keys);
        $this->combat->tick();
        if ($this->player->health > 0 && $this->things->touch($this->player) > 0) {
            $this->renderer->hud->say($this->player->message);
            $this->combat->items += $this->things->countedTaken;
            $this->sounds->play(Sounds::PICKUP);
        }
        if ($this->combat->secrets > $oldSecrets) {
            $this->renderer->hud->say("A SECRET IS REVEALED!");
        }
        if ($this->specials->soundEvent === 1) {
            $this->sounds->play(Sounds::DOOR);
        } else if ($this->specials->soundEvent === 2) {
            $this->sounds->play(Sounds::SWITCH);
        } else if ($this->specials->soundEvent === 3) { $this->sounds->play(Sounds::TELEPORT); }
        else if ($this->specials->soundEvent === 4) { $this->sounds->play(Sounds::STONE); }
        else if ($this->specials->soundEvent === 5) { $this->sounds->play(Sounds::STOP); }
        $this->specials->soundEvent = 0;
        if ($this->player->health < $oldHealth && $this->player->health > 0) {
            $this->sounds->play(Sounds::PLAYER_PAIN);
        }
        $this->renderer->hud->tick($this->levelTime, $this->specials->random());
        int $sector = $this->collision->sectorAt($this->player->unitX(), $this->player->unitY());
        if (substr($this->mapName, 3, 1) === "8" && $sector >= 0 && $this->map->sectors[$sector]->special === 11) {
            $this->player->godMode = false;
            if ($this->player->health <= 10) { $this->finishEpisode($confirm); return; }
        }
        if ($this->player->health <= 0) {
            $this->die($confirm);
            return;
        }
        if ($this->specials->teleportPending) {
            $this->specials->teleportPending = false;
            int $x = $this->specials->teleportX;
            int $y = $this->specials->teleportY;
            for ($i = 0; $i < $this->things->count; $i++) {
                int $radius = $this->things->mobjs[$i]->radius + 16;
                if ($this->things->mobjs[$i]->health > 0 && abs($this->things->mobjs[$i]->x - $x) < $radius && abs($this->things->mobjs[$i]->y - $y) < $radius) { $this->combat->hurt($i, 10000, -1); }
            }
            $this->player->teleport($x, $y, $this->specials->teleportAngle, $this->floorAt($x, $y));
        }
        $this->levelTime++;
        if ($this->specials->exitRequest !== 0) {
            if ($this->specials->exitRequest === 3 || substr($this->mapName, 3, 1) === "8") { $this->finishEpisode($confirm); return; }
            $this->pendingMap = $this->nextMap($this->specials->exitRequest === 2);
            $this->phase = 2;
            $this->phaseTics = 0;
            $this->confirmHeld = $confirm;
            $this->sounds->stop();
            $this->sounds->music->load("D_INTER");
        }
    }

    public function startMapMusic(): void {
        $this->sounds->music->load($this->mapMusic($this->mapName));
    }

    public function mapMusic(string $map): string {
        string $song = "D_" . $map;
        if (substr($map, 1, 1) === "4") {
            $tracks = ["D_E3M4", "D_E3M2", "D_E3M3", "D_E1M5", "D_E2M7", "D_E2M4", "D_E2M6", "D_E2M5", "D_E1M9"];
            $song = (string)$tracks[(int)substr($map, 3, 1) - 1];
        }
        return $song;
    }

    public function finishEpisode(bool $confirm): void {
        $this->phase = 3;
        $this->phaseTics = 0;
        $this->confirmHeld = $confirm;
        $this->sounds->stop();
        if (!$this->sounds->music->load("D_VICTOR")) { $this->sounds->music->load("D_INTER"); }
    }

    public function die(bool $confirm): void {
        $this->phase = 1;
        $this->phaseTics = 0;
        $this->confirmHeld = $confirm;
        $this->sounds->play(Sounds::PLAYER_DEATH);
        $this->renderer->showAutomap = false;
        $this->renderer->hud->say("YOU DIED - ENTER TO RESTART");
    }

    // Draws the current view (or the automap) and the status bar into the 8-bit frame.
    public function render(string $fps): void {
        if ($this->menu->page !== 0) {
            $this->renderer->render($this->map, $this->things, $this->player, $this->levelTime, "");
            $this->menu->draw($this);
            return;
        }
        if ($this->cheats->showPosition) {
            $fps = "X " . $this->player->unitX() . " Y " . $this->player->unitY() . " Z " . $this->player->unitZ();
        }
        if ($this->phase === 3) {
            $this->renderer->finale->draw($this->renderer->hud, (int)substr($this->mapName, 1, 1), $this->phaseTics);
            return;
        }
        if ($this->phase === 2) {
            $this->renderer->intermission->draw($this->renderer->hud, $this->combat, $this->mapName, $this->levelTime);
            return;
        }
        $this->renderer->render($this->map, $this->things, $this->player, $this->levelTime, $fps);
    }

    // E1M8 ends the episode, the secret exit leads to E1M9, and E1M9 returns to E1M4.
    public function nextMap(bool $secret): string {
        string $episode = substr($this->mapName, 1, 1);
        int $mission = (int)substr($this->mapName, 3, 1);
        int $next = $mission + 1;
        if ($secret) {
            $next = 9;
        } else if ($mission === 9) {
            $next = 4;
            if ($episode === "2") {
                $next = 6;
            } else if ($episode === "3") {
                $next = 7;
            } else if ($episode === "4") {
                $next = 3;
            }
        } else if ($mission === 8) {
            $next = 1;
        }
        string $name = "E" . $episode . "M" . $next;
        if ($this->wad->find($name) < 0) {
            return "E1M1";
        }
        return $name;
    }

    public function say(string $line): void {
        if ($this->config->verbose) {
            echo $line . "\n";
        }
    }

    public function floorAt(int $x, int $y): int {
        int $sector = $this->collision->sectorAt($x, $y);
        if ($sector < 0) {
            return 0;
        }
        return $this->map->sectors[$sector]->floor_height;
    }

    public function resolveWadPath(): string {
        if (file_exists($this->config->wadPath)) {
            return $this->config->wadPath;
        }
        return "";
    }
}
