<?php

namespace Showcases\Doom\Play;

use Showcases\Doom\Audio\Sounds;

// Swept projectiles for both sides, with typed direct/splash damage and distinct
// impact art. Object slots are reused after impacts finish, never while in flight.
final class Projectiles {
    public function spawn(Combat $c, int $source, int $tx, int $ty, int $tz): void {
        $m = $c->things->mobjs;
        $this->launch($c, $source, $m[$source]->x, $m[$source]->y, $m[$source]->z + 32, $tx, $ty, $tz, 6000);
    }

    public function launch(Combat $c, int $source, int $x, int $y, int $z, int $tx, int $ty, int $tz, int $type): void {
        int $slot = $c->things->freeSlot();
        int $kind = $c->things->types->find($type);
        if ($slot < 0 || $kind < 0) { return; }
        $m = $c->things->mobjs;
        int $dx = $tx - $x;
        int $dy = $ty - $y;
        int $dz = $tz - $z;
        int $distance = max(1, (int)sqrt($dx * $dx + $dy * $dy));
        int $speed = 10;
        int $damage = 3;
        int $splash = 0;
        if ($type === 6002) { $speed = 20; $damage = 20; $splash = 128; }
        else if ($type === 6003) { $speed = 25; $damage = 5; }
        else if ($type === 6004) { $speed = 25; $damage = 100; }
        else if ($type === 6005) { $speed = 15; $damage = 8; }
        else if ($type === 6006) { $damage = 5; }
        $m[$slot]->active = 1;
        $m[$slot]->type = $type;
        $m[$slot]->kind = $kind;
        $m[$slot]->x = $x;
        $m[$slot]->y = $y;
        $m[$slot]->z = $z;
        $m[$slot]->fixedX = $x << 16;
        $m[$slot]->fixedY = $y << 16;
        $m[$slot]->fixedZ = $z << 16;
        $m[$slot]->sector = $c->collision->sectorAt($x, $y);
        $m[$slot]->velX = intdiv($dx * $speed * 65536, $distance);
        $m[$slot]->velY = intdiv($dy * $speed * 65536, $distance);
        $m[$slot]->velZ = intdiv($dz * $speed * 65536, $distance);
        $m[$slot]->hitDamage = $damage;
        $m[$slot]->splashDamage = $splash;
        $m[$slot]->health = 0;
        $m[$slot]->frames = 1; $m[$slot]->tics = 0; $m[$slot]->phase = 0;
        $m[$slot]->solid = 0;
        $m[$slot]->radius = $type === 6002 ? 11 : 6;
        $m[$slot]->height = 8;
        $m[$slot]->target = $source;
        $m[$slot]->state = 6;
        $m[$slot]->stateAge = 0;
        $m[$slot]->angle = $c->monsters->angle($dx, $dy);
        $c->frame($slot, 0, 1);
        if ($source >= 0) { $c->soundAt(Sounds::FIREBALL, $x, $y); }
    }

    public function tick(Combat $c, int $i): void {
        $m = $c->things->mobjs;
        $m[$i]->stateAge++;
        int $age = $m[$i]->stateAge;
        int $type = $m[$i]->type;
        if ($m[$i]->state === 7) {
            int $frames = 3;
            int $first = 2;
            if ($type === 2035) {
                $frames = 5;
                $first = 0;
                if ($age === 10) { $c->blast($i); }
            } else if ($type === 6002) {
                $first = 1;
                if ($age === 1) { $c->radiusDamage($m[$i]->x, $m[$i]->y, $m[$i]->z, 128, $m[$i]->target); }
            } else if ($type === 6003) {
                $frames = 5;
                $first = 0;
            } else if ($type === 6004) {
                $frames = 6;
                $first = 0;
                if ($age === 1) { $this->bfgSpray($c, $m[$i]->target); }
            }
            int $frame = intdiv($age, 5);
            if ($frame >= $frames) { $m[$i]->active = 0; }
            else { $c->frame($i, $first + $frame, 1); }
            return;
        }
        if ($age > 350) { $m[$i]->active = 0; return; }
        int $newX = $m[$i]->fixedX + $m[$i]->velX;
        int $newY = $m[$i]->fixedY + $m[$i]->velY;
        int $newZ = $m[$i]->fixedZ + $m[$i]->velZ;
        int $x = $m[$i]->x;
        int $y = $m[$i]->y;
        int $z = $m[$i]->z;
        int $dx = ($newX >> 16) - $x;
        int $dy = ($newY >> 16) - $y;
        int $dz = ($newZ >> 16) - $z;
        int $nearest = $c->trace->wallFraction($x, $y, $z, $dx, $dy, $dz);
        int $hit = -2;
        for ($target = -1; $target < $c->things->count; $target++) {
            if ($target === $i || $target === $m[$i]->target) { continue; }
            int $tx = $c->player->unitX();
            int $ty = $c->player->unitY();
            int $tz = $c->player->unitZ();
            int $radius = 16 + $m[$i]->radius;
            int $height = 56;
            if ($target >= 0) {
                if ($m[$target]->active === 0 || $m[$target]->health <= 0) { continue; }
                // Fireballs pass through monsters of their own species, except the shooter.
                int $owner = $m[$i]->target;
                if ($owner >= 0 && $m[$owner]->type === $m[$target]->type && $type !== 6002) { continue; }
                $tx = $m[$target]->x;
                $ty = $m[$target]->y;
                $tz = $m[$target]->z;
                $radius = $m[$target]->radius + $m[$i]->radius;
                $height = $m[$target]->height;
            }
            int $t = $c->trace->boxFraction($x, $y, $dx, $dy, $tx, $ty, $radius);
            int $hitZ = $z + (($dz * max(0, $t)) >> 16);
            if ($t >= 0 && $t < $nearest && $hitZ >= $tz - 6 && $hitZ <= $tz + $height + 6) { $nearest = $t; $hit = $target; }
        }
        int $sector = $c->collision->sectorAt($newX >> 16, $newY >> 16);
        bool $vertical = $sector < 0;
        if ($sector >= 0) { $vertical = ($newZ >> 16) <= $c->collision->map->sectors[$sector]->floor_height || ($newZ >> 16) >= $c->collision->map->sectors[$sector]->ceiling_height; }
        if ($nearest <= 65536 || $vertical) {
            int $damage = ($c->random() % 8 + 1) * $m[$i]->hitDamage;
            if ($hit === -1) { $c->player->damage($damage); }
            else if ($hit >= 0) { $c->hurt($hit, $damage, $m[$i]->target); }
            int $fraction = min(65536, $nearest);
            $m[$i]->x = $x + (($dx * $fraction) >> 16);
            $m[$i]->y = $y + (($dy * $fraction) >> 16);
            $m[$i]->z = $z + (($dz * $fraction) >> 16);
            $m[$i]->state = 7;
            $m[$i]->stateAge = 0;
            if ($type === 6003) { $m[$i]->kind = $c->things->types->find(6007); }
            else if ($type === 6004) { $m[$i]->kind = $c->things->types->find(6008); }
            $c->frame($i, $type === 6002 ? 1 : ($type === 6003 || $type === 6004 ? 0 : 2), 1);
            $c->soundAt($type === 6002 ? Sounds::BARREL : Sounds::EXPLODE, $x, $y);
            return;
        }
        $m[$i]->fixedX = $newX; $m[$i]->fixedY = $newY; $m[$i]->fixedZ = $newZ;
        $m[$i]->x = $newX >> 16; $m[$i]->y = $newY >> 16; $m[$i]->z = $newZ >> 16;
        $m[$i]->sector = $sector;
        $c->frame($i, $type === 6002 ? 0 : intdiv($age, 4) % 2, 1);
    }

    public function bfgSpray(Combat $c, int $owner): void {
        int $x = $c->player->unitX(); int $y = $c->player->unitY(); int $z = $c->player->unitZ() + 32;
        int $angle = $c->player->angle;
        if ($owner >= 0) { $x = $c->things->mobjs[$owner]->x; $y = $c->things->mobjs[$owner]->y; $z = $c->things->mobjs[$owner]->z + 32; $angle = $c->things->mobjs[$owner]->angle; }
        for ($ray = 0; $ray < 40; $ray++) {
            int $direction = ($angle - 1024 + intdiv($ray * 2048, 40)) & 8191;
            int $dx = ($c->trig->cos($direction) * 1024) >> 16;
            int $dy = ($c->trig->sin($direction) * 1024) >> 16;
            int $hit = $c->trace->aim($x, $y, $z, $dx, $dy, $owner);
            if ($hit < 0) { continue; }
            int $damage = 0;
            for ($roll = 0; $roll < 15; $roll++) { $damage += ($c->random() & 7) + 1; }
            $c->hurt($hit, $damage, $owner);
        }
    }
}
