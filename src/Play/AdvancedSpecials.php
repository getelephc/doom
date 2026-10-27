<?php

namespace Showcases\Doom\Play;

// Target calculations and advanced movers used by the complete linedef catalog.
final class AdvancedSpecials {
    public function start(Specials $s, int $line, int $sector, int $action, int $speed): bool {
        int $marker = $s->map->sectors[$sector]->mover;
        if ($action === 45 || $action === 47) {
            if ($marker === 0) { return false; }
            $s->movers[$marker - 1]->active = 2;
            return true;
        }
        if ($marker !== 0 && $action < 60) {
            if (($action === 43 || $action === 44 || $action === 46) && $s->movers[$marker - 1]->active === 2) { $s->movers[$marker - 1]->active = 1; return true; }
            return false;
        }
        int $floor = $s->map->sectors[$sector]->floor_height;
        int $target = $floor;
        int $texture = -1;
        int $model = $s->map->linedefs[$line]->front_sector;
        if ($action <= 4) {
            int $type = $action === 1 ? $s->doorNormal : ($action === 2 ? $s->doorOpen : ($action === 3 ? $s->doorClose : $s->doorCloseThenOpen));
            $s->startDoor($sector, $type);
            int $slot = $s->map->sectors[$sector]->mover - 1;
            if ($slot >= 0) { $s->movers[$slot]->speed = $speed; }
            return $slot >= 0;
        }
        if ($action === 10 || $action === 46) {
            int $slot = $s->newMover($sector, $s->kindLift);
            if ($slot < 0) { return false; }
            $s->movers[$slot]->bottom = $s->lowestFloorAround($sector);
            $s->movers[$slot]->top = $action === 46 ? $this->highestFloor($s, $sector) : $floor;
            $s->movers[$slot]->type = $action === 46 ? 1 : 0;
            $s->movers[$slot]->speed = $speed; $s->movers[$slot]->wait = 105;
            $s->movers[$slot]->direction = -1; $s->movers[$slot]->nextDirection = 1;
            return true;
        }
        if ($action === 30 || $action === 31) { $s->buildStairs($sector, $action === 31); return true; }
        if ($action >= 40 && $action <= 44) {
            int $slot = $s->newMover($sector, 4);
            if ($slot < 0) { return false; }
            int $ceiling = $s->map->sectors[$sector]->ceiling_height;
            $s->movers[$slot]->top = $action === 40 ? $this->highestCeiling($s, $sector) : $ceiling;
            $s->movers[$slot]->bottom = $floor + ($action === 41 ? 0 : 8);
            $s->movers[$slot]->direction = $action === 40 ? 1 : -1;
            $s->movers[$slot]->type = $action >= 43 ? 1 : 0;
            $s->movers[$slot]->crush = $action >= 42 ? 1 : 0;
            $s->movers[$slot]->speed = $speed;
            return true;
        }
        if ($action >= 60) {
            int $light = 0;
            if ($action === 65) { $light = 35; }
            else if ($action === 61) { $light = $this->highestLight($s, $sector); }
            else if ($action === 62) { $light = 255; }
            else if ($action === 63) { $light = $s->minLightAround($sector, $s->map->sectors[$sector]->light_level); }
            else if ($action === 64) { $s->addStrobe($sector, 35, false); return true; }
            $s->map->sectors[$sector]->light_level = $light;
            return true;
        }
        if ($action === 58) { return $this->donut($s, $sector); }
        if ($action === 20 || $action === 56) { $target = $s->lowestFloorAround($sector); }
        else if ($action === 21) { $target = $s->highestFloorAround($sector); if ($target !== $floor) { $target += 8; } }
        else if ($action === 22 || $action === 57) { $target = min($s->lowestCeilingAround($sector), $s->map->sectors[$sector]->ceiling_height) - ($action === 57 ? 8 : 0); }
        else if ($action === 23 || $action === 24) { $target = $s->nextHighestFloor($sector); }
        else if ($action === 50 || $action === 51) { $target = $floor + 24; }
        else if ($action === 52) { $target = $floor + 32; }
        else if ($action === 53) { $target = $floor + 512; }
        else if ($action === 54) { $target = $floor + $this->shortestTexture($s, $sector); }
        else if ($action === 55) { $target = $s->highestFloorAround($sector); }
        if ($action === 56) { $model = $this->floorModel($s, $sector, $target); }
        if (($action === 24 || $action === 51 || $action === 52 || $action === 56) && $model >= 0) { $texture = $s->map->sectors[$model]->floor_pic; }
        int $slot = $s->startFloor($sector, $target, $speed, $texture);
        if ($slot >= 0) {
            $s->movers[$slot]->crush = $action === 57 ? 1 : 0;
            if (($action === 51 || $action === 56) && $model >= 0) { $s->movers[$slot]->changeSpecial = $s->map->sectors[$model]->special; }
        }
        return $slot >= 0;
    }

    public function floorModel(Specials $s, int $sector, int $height): int {
        int $start = $s->sectorLineStart[$sector];
        for ($i = 0; $i < $s->sectorLineCount[$sector]; $i++) {
            int $other = $s->neighbor($s->sectorLines[$start + $i], $sector);
            if ($other >= 0 && $s->map->sectors[$other]->floor_height === $height) { return $other; }
        }
        return -1;
    }

    public function donut(Specials $s, int $pillar): bool {
        int $line = $s->sectorLines[$s->sectorLineStart[$pillar]];
        int $ring = $s->neighbor($line, $pillar);
        if ($ring < 0) { return false; }
        int $start = $s->sectorLineStart[$ring];
        for ($i = 0; $i < $s->sectorLineCount[$ring]; $i++) {
            int $outside = $s->neighbor($s->sectorLines[$start + $i], $ring);
            if ($outside < 0 || $outside === $pillar) { continue; }
            int $height = $s->map->sectors[$outside]->floor_height;
            int $a = $s->startFloor($pillar, $height, 1, -1);
            int $b = $s->startFloor($ring, $height, 1, $s->map->sectors[$outside]->floor_pic);
            if ($a >= 0) { $s->movers[$a]->cadence = 2; }
            if ($b >= 0) { $s->movers[$b]->cadence = 2; $s->movers[$b]->changeSpecial = $s->map->sectors[$outside]->special; }
            return $a >= 0 && $b >= 0;
        }
        return false;
    }

    public function highestFloor(Specials $s, int $sector): int {
        int $height = $s->map->sectors[$sector]->floor_height;
        for ($l = 0; $l < $s->map->linedefCount; $l++) { int $n = $s->neighbor($l, $sector); if ($n >= 0) { $height = max($height, $s->map->sectors[$n]->floor_height); } }
        return $height;
    }
    public function highestCeiling(Specials $s, int $sector): int {
        int $height = $s->map->sectors[$sector]->ceiling_height;
        for ($l = 0; $l < $s->map->linedefCount; $l++) { int $n = $s->neighbor($l, $sector); if ($n >= 0) { $height = max($height, $s->map->sectors[$n]->ceiling_height); } }
        return $height;
    }
    public function highestLight(Specials $s, int $sector): int {
        int $light = 0;
        for ($l = 0; $l < $s->map->linedefCount; $l++) { int $n = $s->neighbor($l, $sector); if ($n >= 0) { $light = max($light, $s->map->sectors[$n]->light_level); } }
        return $light;
    }
    public function shortestTexture(Specials $s, int $sector): int {
        int $height = 32767;
        for ($l = 0; $l < $s->map->linedefCount; $l++) {
            if ($s->neighbor($l, $sector) < 0) { continue; }
            int $r = $s->map->linedefs[$l]->right_side; int $left = $s->map->linedefs[$l]->left_side;
            if ($r >= 0) { int $t = $s->map->sidedefs[$r]->lower; if ($t >= 0) { $height = min($height, $s->textures->height[$t]); } }
            if ($left >= 0) { int $t = $s->map->sidedefs[$left]->lower; if ($t >= 0) { $height = min($height, $s->textures->height[$t]); } }
        }
        return $height === 32767 ? 0 : $height;
    }

    public function blocked(Specials $s, int $sector, int $floor, int $ceiling): bool {
        if ($s->playerInSector($sector) && $ceiling - $floor < 56) { return true; }
        for ($i = 0; $i < $s->things->count; $i++) {
            if ($s->things->mobjs[$i]->active !== 0 && $s->things->mobjs[$i]->solid !== 0 && $s->things->mobjs[$i]->sector === $sector && $ceiling - $floor < $s->things->mobjs[$i]->height) { return true; }
        }
        return false;
    }

    public function tickCeiling(Specials $s, int $i): void {
        int $sector = $s->movers[$i]->sector;
        int $direction = $s->movers[$i]->direction;
        int $target = $direction > 0 ? $s->movers[$i]->top : $s->movers[$i]->bottom;
        int $height = $s->map->sectors[$sector]->ceiling_height + $s->movers[$i]->speed * $direction;
        if ($direction < 0 && $s->movers[$i]->crush === 0 && $this->blocked($s, $sector, $s->map->sectors[$sector]->floor_height, $height)) { return; }
        bool $arrived = $direction > 0 ? $height >= $target : $height <= $target;
        $s->map->sectors[$sector]->ceiling_height = $arrived ? $target : $height;
        if ($arrived) {
            if ($s->movers[$i]->type === 1) { $s->movers[$i]->direction = -$direction; }
            else { $s->finishMover($i); }
        }
        $s->settleThings($sector);
    }
}
