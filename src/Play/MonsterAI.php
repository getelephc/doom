<?php

namespace Showcases\Doom\Play;

use Showcases\Doom\Audio\Sounds;

// Original DOOM actors: sight and hearing wake idle monsters; chase, attack, pain and death
// progress at 35 Hz. A damage source becomes the target, allowing infighting.
final class MonsterAI {
    public function tick(Combat $combat, int $i): void {
        $mobjs = $combat->things->mobjs;
        int $type = $mobjs[$i]->type;
        int $state = $mobjs[$i]->state;
        if ($state === 5) {
            $mobjs[$i]->stateAge++;
            if ($combat->skill === 5 && $type !== 3006 && $mobjs[$i]->stateAge > 420 && ($mobjs[$i]->stateAge & 31) === 0 && $combat->random() < 8) {
                int $x = $mobjs[$i]->spawnX; int $y = $mobjs[$i]->spawnY;
                if (max(abs($x - $combat->player->unitX()), abs($y - $combat->player->unitY())) > 64
                    && $combat->collision->checkActorPosition($x, $y, $mobjs[$i]->spawnZ, $i, $mobjs[$i]->radius, $mobjs[$i]->height)) {
                    $mobjs[$i]->x = $x; $mobjs[$i]->y = $y; $mobjs[$i]->z = $mobjs[$i]->spawnZ;
                    $mobjs[$i]->sector = $combat->collision->sectorAt($x, $y);
                    $mobjs[$i]->health = $combat->rules->health($type); $mobjs[$i]->solid = 1;
                    $mobjs[$i]->angle = $mobjs[$i]->spawnAngle; $mobjs[$i]->state = 0; $mobjs[$i]->stateAge = 0;
                    $combat->frame($i, 0);
                    $combat->soundAt(Sounds::TELEPORT, $x, $y);
                }
            }
            return;
        }
        if ($state === 8) { $this->charge($combat, $i); return; }
        $mobjs[$i]->stateAge++;
        if ($state === 4) {
            int $first = $combat->rules->deathFirst($type);
            int $last = $combat->rules->deathFrames($type) - 1;
            int $frame = min($last, intdiv($mobjs[$i]->stateAge, 5));
            $combat->frame($i, $first + $frame);
            if ($frame === $last) { $mobjs[$i]->state = 5; $mobjs[$i]->stateAge = 0; }
            return;
        }
        if ($state === 3) {
            $mobjs[$i]->stateTics--;
            if ($mobjs[$i]->stateTics <= 0) {
                $mobjs[$i]->state = 1;
            }
            return;
        }
        if ($mobjs[$i]->target >= 0) {
            int $target = $mobjs[$i]->target;
            if ($target >= $combat->things->count || $mobjs[$target]->health <= 0 || $mobjs[$target]->active === 0) {
                $mobjs[$i]->target = -1;
            }
        }
        int $tx = $combat->player->unitX();
        int $ty = $combat->player->unitY();
        int $tz = $combat->player->unitZ() + 28;
        int $target = $mobjs[$i]->target;
        if ($target >= 0) {
            $tx = $mobjs[$target]->x;
            $ty = $mobjs[$target]->y;
            $tz = $mobjs[$target]->z + ($mobjs[$target]->height >> 1);
        } else if ($combat->player->health <= 0) {
            return;
        }
        int $dx = $tx - $mobjs[$i]->x;
        int $dy = $ty - $mobjs[$i]->y;
        int $distance = max(abs($dx), abs($dy));
        int $z = $mobjs[$i]->z + 36;
        bool $visible = $combat->trace->visible($mobjs[$i]->x, $mobjs[$i]->y, $z, $tx, $ty, $tz);
        if ($state === 0) {
            $combat->frame($i, intdiv($mobjs[$i]->stateAge, 10) % 2);
            int $facing = $dx * $combat->trig->cos($mobjs[$i]->angle) + $dy * $combat->trig->sin($mobjs[$i]->angle);
            if (!$visible || ($facing < 0 && $distance > 64)) {
                return;
            }
            $mobjs[$i]->state = 1;
            $mobjs[$i]->cooldown = 12;
            int $sound = $combat->rules->sightSound($type);
            $combat->soundAt($sound, $mobjs[$i]->x, $mobjs[$i]->y);
        }
        int $angle = $this->angle($dx, $dy);
        $mobjs[$i]->angle = $angle;
        if ($state === 2) {
            $mobjs[$i]->stateTics--;
            if ($mobjs[$i]->stateTics === 6) {
                $combat->frame($i, $combat->rules->attackFrame($type) + 1);
                if ($type === 16 && $visible) { $this->attack($combat, $i, $tx, $ty, $tz, $distance); }
            }
            if ($mobjs[$i]->stateTics <= 0) {
                if ($visible) {
                    $this->attack($combat, $i, $tx, $ty, $tz, $distance);
                }
                if ($mobjs[$i]->state !== 8) { $mobjs[$i]->state = 1; }
                $mobjs[$i]->cooldown = $type === 7 ? 4 : 15 + $combat->random() % 25;
                if ($combat->skill === 5) { $mobjs[$i]->cooldown = 4; }
            }
            return;
        }
        if ($mobjs[$i]->cooldown > 0) {
            $mobjs[$i]->cooldown--;
        }
        if ($visible && $distance < 2000 && $mobjs[$i]->cooldown <= 0 && (!$combat->rules->meleeOnly($type) || $distance < 64)) {
            $mobjs[$i]->state = 2;
            $mobjs[$i]->stateTics = $combat->skill === 5 ? 6 : 12;
            $combat->frame($i, $combat->rules->attackFrame($type));
            if ($type === 16) { $this->attack($combat, $i, $tx, $ty, $tz, $distance); }
            return;
        }
        int $walk = intdiv($mobjs[$i]->stateAge, 4) % 4;
        if ($type === 3005 || $type === 3006) { $walk %= 2; }
        $combat->frame($i, $walk);
        // Eight map units per three tics, with diagonal speed normalized.
        if ($combat->rules->flying($type)) {
            int $sector = $mobjs[$i]->sector;
            int $bottom = $combat->collision->map->sectors[$sector]->floor_height;
            int $top = $combat->collision->map->sectors[$sector]->ceiling_height - $mobjs[$i]->height;
            int $desired = $tz - ($mobjs[$i]->height >> 1);
            int $change = max(-4, min(4, $desired - $mobjs[$i]->z));
            $mobjs[$i]->z = max($bottom, min($top, $mobjs[$i]->z + $change));
        }
        if ($combat->skill === 5 || $mobjs[$i]->stateAge % 3 === 0) {
            $this->move($combat, $i, $dx, $dy);
        }
    }

    public function angle(int $dx, int $dy): int {
        float $angle = atan2($dy, $dx) * 8192.0 / (2.0 * M_PI);
        return ((int)round($angle)) & 8191;
    }

    public function attack(Combat $combat, int $i, int $tx, int $ty, int $tz, int $distance): void {
        $mobjs = $combat->things->mobjs;
        int $type = $mobjs[$i]->type;
        if ($combat->player->invisible > 0 && $mobjs[$i]->target < 0) {
            $tx += ($combat->random() - $combat->random()) * 2;
            $ty += ($combat->random() - $combat->random()) * 2;
        }
        if ($type === 3006) {
            int $dx = $tx - $mobjs[$i]->x; int $dy = $ty - $mobjs[$i]->y;
            int $distance = max(1, (int)sqrt($dx * $dx + $dy * $dy));
            $mobjs[$i]->velX = intdiv($dx * 20, $distance); $mobjs[$i]->velY = intdiv($dy * 20, $distance);
            $mobjs[$i]->velZ = intdiv(($tz - $mobjs[$i]->z - 28) * 20, $distance);
            $mobjs[$i]->state = 8; $mobjs[$i]->stateTics = 70;
            $combat->frame($i, 2, 1);
            $combat->soundAt(Sounds::SKULL_ATTACK, $mobjs[$i]->x, $mobjs[$i]->y);
            return;
        }
        if ($combat->rules->meleeOnly($type)) {
            if ($distance < 64) { $this->damageTarget($combat, $i, ($combat->random() % 10 + 1) * 4); }
            $combat->frame($i, 6);
            return;
        }
        if ($type === 3001 || $type === 3003 || $type === 3005 || $type === 16) {
            $combat->frame($i, $type === 16 ? 5 : $combat->rules->attackFrame($type) + 2);
            if ($distance < 64 && $type !== 16) {
                int $multiplier = $type === 3003 ? 10 : ($type === 3005 ? 5 : 3);
                int $damage = ($combat->random() % 8 + 1) * $multiplier;
                $this->damageTarget($combat, $i, $damage);
            } else {
                int $projectile = $type === 16 ? 6002 : ($type === 3003 ? 6005 : ($type === 3005 ? 6006 : 6000));
                $combat->projectiles->launch($combat, $i, $mobjs[$i]->x, $mobjs[$i]->y, $mobjs[$i]->z + 32, $tx, $ty, $tz, $projectile);
            }
            return;
        }
        int $pellets = 1;
        if ($type === 9) {
            $pellets = 3;
        }
        for ($p = 0; $p < $pellets; $p++) {
            int $spread = ($combat->random() - $combat->random()) * 4;
            if ($combat->player->invisible > 0 && $mobjs[$i]->target < 0) { $spread += ($combat->random() - $combat->random()) * 8; }
            int $angle = ($mobjs[$i]->angle + $spread) & 8191;
            int $damage = ($combat->random() % 5 + 1) * 3;
            $this->bullet($combat, $i, $angle, $damage);
        }
        int $sound = Sounds::PISTOL;
        if ($type === 9) {
            $sound = Sounds::SHOTGUN;
        }
        $combat->soundAt($sound, $mobjs[$i]->x, $mobjs[$i]->y);
        $combat->frame($i, 5, 1);
    }

    // The nearest body intercepts a monster's bullet, including the player.
    public function bullet(Combat $combat, int $i, int $angle, int $damage): void {
        $mobjs = $combat->things->mobjs;
        int $x = $mobjs[$i]->x;
        int $y = $mobjs[$i]->y;
        int $z = $mobjs[$i]->z + 36;
        int $dx = ($combat->trig->cos($angle) * 2048) >> 16;
        int $dy = ($combat->trig->sin($angle) * 2048) >> 16;
        int $hit = $combat->trace->aim($x, $y, $z, $dx, $dy, $i);
        int $actorT = 65537;
        if ($hit >= 0) {
            $actorT = $combat->trace->boxFraction($x, $y, $dx, $dy, $mobjs[$hit]->x, $mobjs[$hit]->y, $mobjs[$hit]->radius);
        }
        int $playerT = $combat->trace->boxFraction($x, $y, $dx, $dy, $combat->player->unitX(), $combat->player->unitY(), 16);
        if ($playerT >= 0 && $playerT < $actorT && $combat->trace->visible($x, $y, $z, $combat->player->unitX(), $combat->player->unitY(), $combat->player->unitZ() + 28)) {
            $combat->player->damage($damage);
        } else if ($hit >= 0) {
            $combat->hurt($hit, $damage, $i);
        }
    }

    public function damageTarget(Combat $combat, int $i, int $damage): void {
        int $target = $combat->things->mobjs[$i]->target;
        if ($target < 0) {
            $combat->player->damage($damage);
        } else {
            $combat->hurt($target, $damage, $i);
        }
    }

    public function move(Combat $combat, int $i, int $dx, int $dy): void {
        int $direction = ($this->angle($dx, $dy) + 512) >> 10;
        int $speed = $combat->rules->speed($combat->things->mobjs[$i]->type);
        for ($attempt = 0; $attempt < 8; $attempt++) {
            int $offset = intdiv($attempt + 1, 2);
            if ($attempt % 2 === 0) {
                $offset = -$offset;
            }
            int $angle = (($direction + $offset) & 7) * 1024;
            int $stepX = ($combat->trig->cos($angle) * $speed) >> 16;
            int $stepY = ($combat->trig->sin($angle) * $speed) >> 16;
            $mobjs = $combat->things->mobjs;
            int $oldX = $mobjs[$i]->x; int $oldY = $mobjs[$i]->y;
            int $x = $oldX + $stepX;
            int $y = $oldY + $stepY;
            int $reach = $mobjs[$i]->radius + 16;
            if (abs($x - $combat->player->unitX()) < $reach && abs($y - $combat->player->unitY()) < $reach) {
                continue;
            }
            if ($combat->collision->checkActorPosition($x, $y, $mobjs[$i]->z, $i, $mobjs[$i]->radius, $mobjs[$i]->height)) {
                $mobjs[$i]->x = $x;
                $mobjs[$i]->y = $y;
                if (!$combat->rules->flying($mobjs[$i]->type)) { $mobjs[$i]->z = $combat->collision->floorZ; }
                $mobjs[$i]->sector = $combat->collision->sectorAt($x, $y);
                for ($t = 0; $t < $combat->collision->touchedCount; $t++) {
                    int $line = $combat->collision->touched[$t];
                    int $before = $combat->collision->pointSide($line, $oldX, $oldY);
                    int $after = $combat->collision->pointSide($line, $x, $y);
                    if ($before !== $after && $before !== 0) { $combat->specials->monsterCross($line, $before, $i); }
                    if ($mobjs[$i]->x !== $x || $mobjs[$i]->y !== $y) { break; }
                }
                return;
            }
            for ($t = 0; $t < $combat->collision->touchedCount; $t++) {
                int $line = $combat->collision->touched[$t];
                int $special = $combat->collision->map->linedefs[$line]->special;
                int $sector = $combat->collision->map->linedefs[$line]->back_sector;
                if (($special === 1 || $special === 31) && $sector >= 0) {
                    $combat->specials->startDoor($sector, $combat->specials->doorNormal);
                }
            }
        }
    }
    // A lost soul's charge ends on a wall or body, leaving it available to chase again.
    public function charge(Combat $c, int $i): void {
        $m = $c->things->mobjs;
        $m[$i]->stateTics--;
        int $x = $m[$i]->x + $m[$i]->velX; int $y = $m[$i]->y + $m[$i]->velY;
        int $target = $m[$i]->target;
        int $tx = $c->player->unitX(); int $ty = $c->player->unitY(); int $radius = 16;
        if ($target >= 0) { $tx = $m[$target]->x; $ty = $m[$target]->y; $radius = $m[$target]->radius; }
        if (abs($x - $tx) < $radius + 16 && abs($y - $ty) < $radius + 16) {
            $this->damageTarget($c, $i, ($c->random() % 8 + 1) * 3);
            $m[$i]->state = 1; $m[$i]->cooldown = 15;
            return;
        }
        if ($m[$i]->stateTics <= 0 || !$c->collision->checkActorPosition($x, $y, $m[$i]->z, $i, 16, 56)) {
            $m[$i]->state = 1; $m[$i]->cooldown = 15;
            return;
        }
        $m[$i]->x = $x; $m[$i]->y = $y;
        $m[$i]->sector = $c->collision->sectorAt($x, $y);
        int $sector = $m[$i]->sector;
        $m[$i]->z = max($c->collision->map->sectors[$sector]->floor_height,
            min($c->collision->map->sectors[$sector]->ceiling_height - 56, $m[$i]->z + $m[$i]->velZ));
        $c->frame($i, 2 + ($m[$i]->stateTics & 1), 1);
    }

}
