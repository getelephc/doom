<?php

namespace Showcases\Doom\Play;

use Showcases\Doom\Bsp\BspWalker;
use Showcases\Doom\Data\Mobj;
use Showcases\Doom\Data\Thing;
use Showcases\Doom\Graphics\Sprites;
use Showcases\Doom\Map\MapData;
use Showcases\Doom\Support\Trig;
use Showcases\Doom\Wad\Wad;

// Single-player map objects filtered by the selected difficulty.
final class Things {
    public int $count;
    public buffer<Mobj> $mobjs;
    public buffer<int> $frameDefinition;
    public ThingTypes $types;
    public int $countedTaken;
    public int $skill = 3;

    public function __construct() {
        $this->count = 0;
        $this->mobjs = buffer_new<Mobj>(1);
        $this->frameDefinition = buffer_new<int>(4);
        $this->types = new ThingTypes();
        $this->countedTaken = 0;
    }

    public function spawn(MapData $map, Sprites $sprites, Wad $wad, Trig $trig, BspWalker $locator): void {
        // the previous map's objects go
        buffer<Mobj> $previous = $this->mobjs;
        buffer_free($previous);
        buffer<int> $previousDefinitions = $this->frameDefinition;
        buffer_free($previousDefinitions);
        int $capacity = $map->thingCount * 2 + 64;
        $this->mobjs = buffer_new<Mobj>($capacity);
        $this->frameDefinition = buffer_new<int>($capacity * 4);
        buffer<Mobj> $mobjs = $this->mobjs;
        buffer<Thing> $things = $map->things;
        int $count = 0;
        for ($i = 0; $i < $map->thingCount; $i++) {
            int $flags = $things[$i]->flags;
            int $skillBit = $this->skill <= 2 ? 1 : ($this->skill === 3 ? 2 : 4);
            int $mediumSkill = $flags & $skillBit;
            int $multiplayer = $flags & 16;
            if ($mediumSkill === 0 || $multiplayer !== 0) {
                continue;
            }
            int $kind = $this->types->find($things[$i]->type);
            if ($kind < 0) {
                continue;
            }
            int $spriteKey = $wad->key($this->types->spriteName($kind));
            int $firstFrame = $this->types->firstFrame[$kind];
            int $definition = $sprites->find($spriteKey, $firstFrame);
            if ($definition < 0) {
                continue;
            }
            int $definitionBase = $count * 4;
            $this->frameDefinition[$definitionBase] = $definition;
            int $frames = 1;
            for ($k = 1; $k < $this->types->frames[$kind]; $k++) {
                int $nextFrame = $firstFrame + $k;
                int $next = $sprites->find($spriteKey, $nextFrame);
                if ($next < 0) {
                    break;
                }
                int $slot = $definitionBase + $k;
                $this->frameDefinition[$slot] = $next;
                $frames++;
            }

            int $x = $things[$i]->x;
            int $y = $things[$i]->y;
            int $subSector = $locator->subSectorAt($map, $x, $y);
            int $sector = 0;
            if ($subSector >= 0) {
                $sector = $map->subSectors[$subSector]->sector;
            }
            int $z = $map->sectors[$sector]->floor_height;
            if ($this->types->hanging[$kind] !== 0) {
                int $hungFrom = $map->sectors[$sector]->ceiling_height - $this->types->height[$kind];
                $z = $hungFrom;
            }
            $mobjs[$count]->active = 1;
            $mobjs[$count]->x = $x;
            $mobjs[$count]->y = $y;
            $mobjs[$count]->z = $z;
            $mobjs[$count]->angle = $trig->fromDegrees($things[$i]->angle);
            $mobjs[$count]->type = $things[$i]->type;
            $mobjs[$count]->kind = $kind;
            $mobjs[$count]->sector = $sector;
            $mobjs[$count]->first_definition = $definition;
            $mobjs[$count]->frames = $frames;
            $mobjs[$count]->tics = $this->types->tics[$kind];
            $mobjs[$count]->phase = $i * 7;
            $mobjs[$count]->bright = $this->types->bright[$kind];
            $mobjs[$count]->radius = $this->types->radius[$kind];
            $mobjs[$count]->height = $this->types->height[$kind];
            $mobjs[$count]->solid = $this->types->solid[$kind];
            $mobjs[$count]->health = 0;
            $mobjs[$count]->state = 0;
            $mobjs[$count]->stateTics = 0;
            $mobjs[$count]->stateAge = 0;
            $mobjs[$count]->target = -1;
            $mobjs[$count]->cooldown = 0;
            $mobjs[$count]->spawnFlags = $flags;
            $mobjs[$count]->spawnX = $x;
            $mobjs[$count]->spawnY = $y;
            $mobjs[$count]->spawnZ = $z;
            $mobjs[$count]->spawnAngle = $mobjs[$count]->angle;
            $count++;
        }
        $this->count = $count;
    }

    // Items the player's 16-unit box touches; returns how many were taken. Like DOOM,
    // an item counts only from 8 units below the player's feet up to the top of the
    // player's 56-unit body, so nothing is collected from a ledge overhead or below.
    public function touch(Player $player): int {
        $this->countedTaken = 0;
        buffer<Mobj> $mobjs = $this->mobjs;
        int $x = $player->unitX();
        int $y = $player->unitY();
        int $z = $player->unitZ();
        int $taken = 0;
        for ($i = 0; $i < $this->count; $i++) {
            if ($mobjs[$i]->active === 0 || $mobjs[$i]->solid !== 0) {
                continue;
            }
            int $reach = $mobjs[$i]->radius + 16;
            int $dx = $mobjs[$i]->x - $x;
            int $dy = $mobjs[$i]->y - $y;
            if ($dx >= $reach || $dx <= -$reach || $dy >= $reach || $dy <= -$reach) {
                continue;
            }
            int $dz = $mobjs[$i]->z - $z;
            if ($dz > 56 || $dz < -8) {
                continue;
            }
            bool $dropped = ($mobjs[$i]->spawnFlags & 256) !== 0;
            if ($player->pickup($mobjs[$i]->type, $dropped)) {
                if ($this->types->countedItem($mobjs[$i]->type)) {
                    $this->countedTaken++;
                }
                $mobjs[$i]->active = 0;
                $taken++;
            }
        }
        return $taken;
    }

    // Sprite frame definition shown for object $index at this tic.
    public function definitionAt(int $index, int $tic): int {
        if ($this->mobjs[$index]->health !== 0 || $this->mobjs[$index]->state !== 0) {
            return $this->mobjs[$index]->first_definition;
        }
        int $frames = $this->mobjs[$index]->frames;
        int $tics = $this->mobjs[$index]->tics;
        int $base = $index * 4;
        if ($frames <= 1 || $tics <= 0) {
            return $this->frameDefinition[$base];
        }
        int $step = intdiv($tic, $tics);
        int $shifted = $step + $this->mobjs[$index]->phase;
        int $frame = $shifted % $frames;
        int $slot = $base + $frame;
        return $this->frameDefinition[$slot];
    }

    // Reuse consumed pickups and expired projectiles before extending the live range.
    public function freeSlot(): int {
        for ($i = 0; $i < $this->count; $i++) {
            if ($this->mobjs[$i]->active === 0) {
                return $i;
            }
        }
        if ($this->count >= buffer_len($this->mobjs)) {
            return -1;
        }
        int $slot = $this->count;
        $this->count++;
        return $slot;
    }
}
