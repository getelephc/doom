<?php

namespace Showcases\Doom\App;

use Showcases\Doom\Play\Player;
use Showcases\Doom\Data\Mobj;
use Showcases\Doom\Data\Sector;
use Showcases\Doom\Data\Sidedef;
use Showcases\Doom\Data\Mover;
use Showcases\Doom\Data\LightEffect;
use Showcases\Doom\Data\Button;

// Explicit save schema. Add new simulation fields here when their owning record changes.
final class SaveRecords {
    // active, x, y, z, angle, type, kind, sector, first_definition, frames, tics, phase, bright, radius, height, solid, health, state, stateTics, stateAge, target, cooldown, spawnFlags, velX, velY, velZ, fixedX, fixedY, fixedZ, hitDamage, splashDamage, spawnX, spawnY, spawnZ, spawnAngle
    public function readMobj(buffer<Mobj> $data, int $i): array {
        return [$data[$i]->active, $data[$i]->x, $data[$i]->y, $data[$i]->z, $data[$i]->angle, $data[$i]->type, $data[$i]->kind, $data[$i]->sector, $data[$i]->first_definition, $data[$i]->frames, $data[$i]->tics, $data[$i]->phase, $data[$i]->bright, $data[$i]->radius, $data[$i]->height, $data[$i]->solid, $data[$i]->health, $data[$i]->state, $data[$i]->stateTics, $data[$i]->stateAge, $data[$i]->target, $data[$i]->cooldown, $data[$i]->spawnFlags, $data[$i]->velX, $data[$i]->velY, $data[$i]->velZ, $data[$i]->fixedX, $data[$i]->fixedY, $data[$i]->fixedZ, $data[$i]->hitDamage, $data[$i]->splashDamage, $data[$i]->spawnX, $data[$i]->spawnY, $data[$i]->spawnZ, $data[$i]->spawnAngle];
    }
    public function writeMobj(buffer<Mobj> $data, int $i, array $row): void {
        $data[$i]->active = (int)$row[0];
        $data[$i]->x = (int)$row[1];
        $data[$i]->y = (int)$row[2];
        $data[$i]->z = (int)$row[3];
        $data[$i]->angle = (int)$row[4];
        $data[$i]->type = (int)$row[5];
        $data[$i]->kind = (int)$row[6];
        $data[$i]->sector = (int)$row[7];
        $data[$i]->first_definition = (int)$row[8];
        $data[$i]->frames = (int)$row[9];
        $data[$i]->tics = (int)$row[10];
        $data[$i]->phase = (int)$row[11];
        $data[$i]->bright = (int)$row[12];
        $data[$i]->radius = (int)$row[13];
        $data[$i]->height = (int)$row[14];
        $data[$i]->solid = (int)$row[15];
        $data[$i]->health = (int)$row[16];
        $data[$i]->state = (int)$row[17];
        $data[$i]->stateTics = (int)$row[18];
        $data[$i]->stateAge = (int)$row[19];
        $data[$i]->target = (int)$row[20];
        $data[$i]->cooldown = (int)$row[21];
        $data[$i]->spawnFlags = (int)$row[22];
        $data[$i]->velX = (int)$row[23];
        $data[$i]->velY = (int)$row[24];
        $data[$i]->velZ = (int)$row[25];
        $data[$i]->fixedX = (int)$row[26];
        $data[$i]->fixedY = (int)$row[27];
        $data[$i]->fixedZ = (int)$row[28];
        $data[$i]->hitDamage = (int)$row[29];
        $data[$i]->splashDamage = (int)$row[30];
        $data[$i]->spawnX = (int)$row[31];
        $data[$i]->spawnY = (int)$row[32];
        $data[$i]->spawnZ = (int)$row[33];
        $data[$i]->spawnAngle = (int)$row[34];
    }

    // floor_height, ceiling_height, floor_pic, ceiling_pic, light_level, special, tag, mover
    public function readSector(buffer<Sector> $data, int $i): array {
        return [$data[$i]->floor_height, $data[$i]->ceiling_height, $data[$i]->floor_pic, $data[$i]->ceiling_pic, $data[$i]->light_level, $data[$i]->special, $data[$i]->tag, $data[$i]->mover];
    }
    public function writeSector(buffer<Sector> $data, int $i, array $row): void {
        $data[$i]->floor_height = (int)$row[0];
        $data[$i]->ceiling_height = (int)$row[1];
        $data[$i]->floor_pic = (int)$row[2];
        $data[$i]->ceiling_pic = (int)$row[3];
        $data[$i]->light_level = (int)$row[4];
        $data[$i]->special = (int)$row[5];
        $data[$i]->tag = (int)$row[6];
        $data[$i]->mover = (int)$row[7];
    }

    // x_offset, y_offset, upper, lower, middle, sector
    public function readSidedef(buffer<Sidedef> $data, int $i): array {
        return [$data[$i]->x_offset, $data[$i]->y_offset, $data[$i]->upper, $data[$i]->lower, $data[$i]->middle, $data[$i]->sector];
    }
    public function writeSidedef(buffer<Sidedef> $data, int $i, array $row): void {
        $data[$i]->x_offset = (int)$row[0];
        $data[$i]->y_offset = (int)$row[1];
        $data[$i]->upper = (int)$row[2];
        $data[$i]->lower = (int)$row[3];
        $data[$i]->middle = (int)$row[4];
        $data[$i]->sector = (int)$row[5];
    }

    // active, sector, kind, type, direction, speed, top, bottom, wait, countdown, texture, cadence, elapsed, crush, changeSpecial, nextDirection
    public function readMover(buffer<Mover> $data, int $i): array {
        return [$data[$i]->active, $data[$i]->sector, $data[$i]->kind, $data[$i]->type, $data[$i]->direction, $data[$i]->speed, $data[$i]->top, $data[$i]->bottom, $data[$i]->wait, $data[$i]->countdown, $data[$i]->texture, $data[$i]->cadence, $data[$i]->elapsed, $data[$i]->crush, $data[$i]->changeSpecial, $data[$i]->nextDirection];
    }
    public function writeMover(buffer<Mover> $data, int $i, array $row): void {
        $data[$i]->active = (int)$row[0];
        $data[$i]->sector = (int)$row[1];
        $data[$i]->kind = (int)$row[2];
        $data[$i]->type = (int)$row[3];
        $data[$i]->direction = (int)$row[4];
        $data[$i]->speed = (int)$row[5];
        $data[$i]->top = (int)$row[6];
        $data[$i]->bottom = (int)$row[7];
        $data[$i]->wait = (int)$row[8];
        $data[$i]->countdown = (int)$row[9];
        $data[$i]->texture = (int)$row[10];
        $data[$i]->cadence = (int)$row[11];
        $data[$i]->elapsed = (int)$row[12];
        $data[$i]->crush = (int)$row[13];
        $data[$i]->changeSpecial = (int)$row[14];
        $data[$i]->nextDirection = (int)$row[15];
    }

    // sector, kind, min_light, max_light, count, dark_time, bright_time, direction
    public function readLightEffect(buffer<LightEffect> $data, int $i): array {
        return [$data[$i]->sector, $data[$i]->kind, $data[$i]->min_light, $data[$i]->max_light, $data[$i]->count, $data[$i]->dark_time, $data[$i]->bright_time, $data[$i]->direction];
    }
    public function writeLightEffect(buffer<LightEffect> $data, int $i, array $row): void {
        $data[$i]->sector = (int)$row[0];
        $data[$i]->kind = (int)$row[1];
        $data[$i]->min_light = (int)$row[2];
        $data[$i]->max_light = (int)$row[3];
        $data[$i]->count = (int)$row[4];
        $data[$i]->dark_time = (int)$row[5];
        $data[$i]->bright_time = (int)$row[6];
        $data[$i]->direction = (int)$row[7];
    }

    // active, sidedef, part, texture, countdown
    public function readButton(buffer<Button> $data, int $i): array {
        return [$data[$i]->active, $data[$i]->sidedef, $data[$i]->part, $data[$i]->texture, $data[$i]->countdown];
    }
    public function writeButton(buffer<Button> $data, int $i, array $row): void {
        $data[$i]->active = (int)$row[0];
        $data[$i]->sidedef = (int)$row[1];
        $data[$i]->part = (int)$row[2];
        $data[$i]->texture = (int)$row[3];
        $data[$i]->countdown = (int)$row[4];
    }

    // x, y, z, momX, momY, momZ, angle, turnHeld, viewHeight, deltaViewHeight, viewZ, floorZ, ceilingZ, useHeld, keys, health, armor, armorType, bullets, shells, weapons, weapon, weaponAge, weaponCooldown, damageCount, bonusCount, skill, rockets, cells, maxBullets, maxShells, maxRockets, maxCells, backpack, godMode, noclip, berserk, fullMap, invulnerable, invisible, suit, lightAmp, availableWeapons, pendingWeapon, selectionHeld, sawClock, fireHeld
    public function readPlayer(Player $p): array {
        return [(int)$p->x, (int)$p->y, (int)$p->z, (int)$p->momX, (int)$p->momY, (int)$p->momZ, (int)$p->angle, (int)$p->turnHeld, (int)$p->viewHeight, (int)$p->deltaViewHeight, (int)$p->viewZ, (int)$p->floorZ, (int)$p->ceilingZ, (int)$p->useHeld, (int)$p->keys, (int)$p->health, (int)$p->armor, (int)$p->armorType, (int)$p->bullets, (int)$p->shells, (int)$p->weapons, (int)$p->weapon, (int)$p->weaponAge, (int)$p->weaponCooldown, (int)$p->damageCount, (int)$p->bonusCount, (int)$p->skill, (int)$p->rockets, (int)$p->cells, (int)$p->maxBullets, (int)$p->maxShells, (int)$p->maxRockets, (int)$p->maxCells, (int)$p->backpack, (int)$p->godMode, (int)$p->noclip, (int)$p->berserk, (int)$p->fullMap, (int)$p->invulnerable, (int)$p->invisible, (int)$p->suit, (int)$p->lightAmp, (int)$p->availableWeapons, (int)$p->pendingWeapon, (int)$p->selectionHeld, (int)$p->sawClock, (int)$p->fireHeld];
    }
    public function writePlayer(Player $p, array $row): void {
        $p->x = (int)$row[0];
        $p->y = (int)$row[1];
        $p->z = (int)$row[2];
        $p->momX = (int)$row[3];
        $p->momY = (int)$row[4];
        $p->momZ = (int)$row[5];
        $p->angle = (int)$row[6];
        $p->turnHeld = (int)$row[7];
        $p->viewHeight = (int)$row[8];
        $p->deltaViewHeight = (int)$row[9];
        $p->viewZ = (int)$row[10];
        $p->floorZ = (int)$row[11];
        $p->ceilingZ = (int)$row[12];
        $p->useHeld = (bool)$row[13];
        $p->keys = (int)$row[14];
        $p->health = (int)$row[15];
        $p->armor = (int)$row[16];
        $p->armorType = (int)$row[17];
        $p->bullets = (int)$row[18];
        $p->shells = (int)$row[19];
        $p->weapons = (int)$row[20];
        $p->weapon = (int)$row[21];
        $p->weaponAge = (int)$row[22];
        $p->weaponCooldown = (int)$row[23];
        $p->damageCount = (int)$row[24];
        $p->bonusCount = (int)$row[25];
        $p->skill = (int)$row[26];
        $p->rockets = (int)$row[27];
        $p->cells = (int)$row[28];
        $p->maxBullets = (int)$row[29];
        $p->maxShells = (int)$row[30];
        $p->maxRockets = (int)$row[31];
        $p->maxCells = (int)$row[32];
        $p->backpack = (bool)$row[33];
        $p->godMode = (bool)$row[34];
        $p->noclip = (bool)$row[35];
        $p->berserk = (bool)$row[36];
        $p->fullMap = (bool)$row[37];
        $p->invulnerable = (int)$row[38];
        $p->invisible = (int)$row[39];
        $p->suit = (int)$row[40];
        $p->lightAmp = (int)$row[41];
        $p->availableWeapons = (int)$row[42];
        $p->pendingWeapon = (int)$row[43];
        $p->selectionHeld = (int)$row[44];
        $p->sawClock = (int)$row[45];
        $p->fireHeld = (bool)$row[46];
    }
}
