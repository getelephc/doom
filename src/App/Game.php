<?php

namespace Showcases\Doom\App;

use Showcases\Doom\Bsp\BspWalker;
use Showcases\Doom\Graphics\Flats;
use Showcases\Doom\Graphics\Palette;
use Showcases\Doom\Graphics\Sprites;
use Showcases\Doom\Graphics\Textures;
use Showcases\Doom\Map\MapData;
use Showcases\Doom\Map\MapLoader;
use Showcases\Doom\Play\Collision;
use Showcases\Doom\Play\Player;
use Showcases\Doom\Play\Specials;
use Showcases\Doom\Play\Things;
use Showcases\Doom\Render\Renderer;
use Showcases\Doom\SDL\Input;
use Showcases\Doom\SDL\SDL;
use Showcases\Doom\Support\Trig;
use Showcases\Doom\Wad\Wad;

final class Game {
    public Config $config;
    public SDL $sdl;
    public Input $input;
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
    public Renderer $renderer;
    public GameState $state;
    public string $mapName;
    public bool $vsyncToggleHeld;
    public bool $mapToggleHeld;
    public string $fpsText;
    public int $tic;
    public int $levelTime;
    public int $statStartTicks;
    public int $statFrames;
    public int $statRenderNanos;

    public function __construct(Config $config) {
        $this->config = $config;
        $this->sdl = new SDL();
        $this->input = new Input();
        $this->trig = new Trig();
        $this->wad = new Wad();
        $this->palette = new Palette();
        $this->textures = new Textures();
        $this->flats = new Flats();
        $this->sprites = new Sprites();
        $this->mapLoader = new MapLoader();
        $this->map = new MapData("", -1);
        $this->things = new Things();
        $this->locator = new BspWalker();
        $this->collision = new Collision($this->map, $this->locator, $this->things);
        $this->specials = new Specials($this->map, $this->collision, $this->things, $this->textures, $this->wad, $this->trig);
        $this->player = new Player();
        $this->renderer = new Renderer($this->wad, $this->palette, $this->textures, $this->flats, $this->sprites, $this->trig, $config->renderWidth, $config->renderHeight);
        $this->state = GameState::Booting;
        $this->mapName = "";
        $this->vsyncToggleHeld = false;
        $this->mapToggleHeld = false;
        $this->fpsText = "";
        $this->tic = 0;
        $this->levelTime = 0;
        $this->statStartTicks = 0;
        $this->statFrames = 0;
        $this->statRenderNanos = 0;
    }

    public function run(): void {
        if (!$this->bootData()) {
            return;
        }
        if (!$this->sdl->boot($this->config)) {
            echo "SDL boot failed: " . $this->sdl->lastError() . "\n";
            return;
        }

        $this->state = GameState::Running;
        echo "DOOM showcase running\n";
        echo "WASD/arrows move, Shift runs, Space/E uses, Tab shows the map, V toggles vsync, ESC quits\n";

        int $start = $this->sdl->ticks();
        $this->statStartTicks = $start;
        while ($this->state === GameState::Running) {
            $this->sdl->pumpEvents();
            $keys = $this->sdl->keyboardState();
            if ($this->input->shouldQuit($keys) !== 0) {
                $this->state = GameState::Stopped;
            }
            $this->handleVsyncToggle($keys);
            $this->handleMapToggle($keys);

            int $now = $this->sdl->ticks();
            if ($this->config->bootDurationMs > 0 && $now - $start >= $this->config->bootDurationMs) {
                $this->state = GameState::Stopped;
            }

            // fixed-rate game tics: run every tic that is due, at most 5 per frame
            int $elapsed = $now - $start;
            int $tickTime = $elapsed * $this->config->tickRate;
            int $dueTics = intdiv($tickTime, 1000);
            int $behind = $dueTics - $this->tic;
            if ($behind > 5) {
                int $skipTo = $dueTics - 5;
                $this->tic = $skipTo;
            }
            while ($this->tic < $dueTics) {
                $this->runTic($keys);
                $this->tic++;
            }

            int $renderStart = hrtime(true);
            $this->renderer->render($this->map, $this->things, $this->player, $this->levelTime, $this->fpsText);
            $this->renderer->screen->toRgb($this->palette, $this->player->palette());
            int $renderNanos = hrtime(true) - $renderStart;
            $this->sdl->present($this->renderer->screen);
            $this->reportStats($now, $renderNanos);
        }

        $this->sdl->shutdown();
    }

    public function runTic(ptr $keys): void {
        $this->specials->playerX = $this->player->unitX();
        $this->specials->playerY = $this->player->unitY();
        $this->specials->playerKeys = $this->player->keys;
        $this->player->tick($this->input, $keys, $this->collision, $this->specials, $this->trig, $this->levelTime);
        $this->specials->playerX = $this->player->unitX();
        $this->specials->playerY = $this->player->unitY();
        $this->specials->tick();
        if ($this->things->touch($this->player) > 0) {
            $this->renderer->hud->say($this->player->message);
        }
        $this->renderer->hud->tick($this->levelTime, $this->specials->random());
        if ($this->player->health <= 0) {
            // dead: start the level over with a fresh marine
            $this->player->reset();
            $this->loadMap($this->mapName);
            $this->renderer->hud->say("You died");
            return;
        }
        if ($this->specials->teleportPending) {
            $this->specials->teleportPending = false;
            int $x = $this->specials->teleportX;
            int $y = $this->specials->teleportY;
            $this->player->teleport($x, $y, $this->specials->teleportAngle, $this->floorAt($x, $y));
        }
        $this->levelTime++;
        if ($this->specials->exitRequest !== 0) {
            $this->loadMap($this->nextMap($this->specials->exitRequest === 2));
        }
    }

    public function bootData(): bool {
        string $wadPath = $this->resolveWadPath();
        if ($wadPath === "") {
            echo "No WAD file at " . $this->config->wadPath . "\n";
            return false;
        }
        if (!$this->wad->load($wadPath)) {
            echo "Failed to load WAD: " . $wadPath . "\n";
            return false;
        }
        echo "Loaded WAD: " . $this->wad->kind . " | lumps: " . $this->wad->lumpCount . "\n";
        if (!$this->palette->load($this->wad) || !$this->textures->load($this->wad) || !$this->flats->load($this->wad) || !$this->sprites->load($this->wad)) {
            echo "The WAD lacks PLAYPAL, textures, flats or sprites\n";
            return false;
        }
        echo "Graphics: " . $this->textures->count . " textures, " . $this->flats->count . " flats, " . $this->sprites->count . " sprite frames\n";
        $this->renderer = new Renderer($this->wad, $this->palette, $this->textures, $this->flats, $this->sprites, $this->trig, $this->config->renderWidth, $this->config->renderHeight);
        return $this->loadMap($this->config->startupMap);
    }

    public function loadMap(string $name): bool {
        MapData $map = $this->mapLoader->load($this->wad, $this->textures, $this->flats, $name);
        if (!$map->isValid()) {
            echo "Map " . $name . " not found or incomplete\n";
            return false;
        }
        $this->map = $map;
        $this->mapName = $name;
        $this->levelTime = 0;
        $this->renderer->useMap($map);
        $this->things->spawn($map, $this->sprites, $this->wad, $this->trig, $this->locator);
        $this->collision = new Collision($map, $this->locator, $this->things);
        $this->specials = new Specials($map, $this->collision, $this->things, $this->textures, $this->wad, $this->trig);
        int $angle = $this->trig->fromDegrees($map->playerStartAngle);
        int $floor = $this->floorAt($map->playerStartX, $map->playerStartY);
        $this->player->spawn($map->playerStartX, $map->playerStartY, $angle, $floor);
        $this->player->keys = 0;
        $this->renderer->hud->say($name);
        echo $map->summary() . " | " . $this->things->count . " objects\n";
        return true;
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
        string $repoRelative = "showcases/doom/" . $this->config->wadPath;
        if (file_exists($repoRelative)) {
            return $repoRelative;
        }
        return "";
    }

    // Once per second: frames shown and the average time spent drawing one frame.
    public function reportStats(int $now, int $renderNanos): void {
        $this->statFrames++;
        int $total = $this->statRenderNanos + $renderNanos;
        $this->statRenderNanos = $total;
        int $elapsed = $now - $this->statStartTicks;
        if ($elapsed < 1000) {
            return;
        }
        int $frameTime = $this->statFrames * 1000;
        int $fps = intdiv($frameTime, $elapsed);
        int $nanosPerFrame = intdiv($this->statRenderNanos, $this->statFrames);
        int $hundredths = intdiv($nanosPerFrame, 10000);
        int $whole = intdiv($hundredths, 100);
        int $fraction = $hundredths % 100;
        string $padding = "";
        if ($fraction < 10) {
            $padding = "0";
        }
        string $vsync = "off";
        if ($this->sdl->vsync) {
            $vsync = "on";
        }
        string $stats = $this->mapName . " | " . $fps . " fps | render " . $whole . "." . $padding . $fraction . " ms | vsync " . $vsync;
        $this->fpsText = $fps . " FPS " . $whole . "." . $padding . $fraction . " MS";
        $this->sdl->setTitle($this->config->windowTitle . " | " . $stats);
        echo $stats . "\n";
        $this->statStartTicks = $now;
        $this->statFrames = 0;
        $this->statRenderNanos = 0;
    }

    public function handleMapToggle(ptr $keys): void {
        if ($this->input->toggleMap($keys) === 0) {
            $this->mapToggleHeld = false;
            return;
        }
        if (!$this->mapToggleHeld) {
            $this->renderer->showAutomap = !$this->renderer->showAutomap;
        }
        $this->mapToggleHeld = true;
    }

    public function handleVsyncToggle(ptr $keys): void {
        if ($this->input->toggleVsync($keys) === 0) {
            $this->vsyncToggleHeld = false;
            return;
        }
        if (!$this->vsyncToggleHeld) {
            $this->sdl->setVsync(!$this->sdl->vsync);
        }
        $this->vsyncToggleHeld = true;
    }
}
