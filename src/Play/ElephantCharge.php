<?php

namespace Showcases\Doom\Play;

use Showcases\Doom\Audio\Sounds;

// A telegraphed, straight dash. Existing saved actor fields hold the countdown,
// locked velocity and contact damage, keeping the save record schema unchanged.
final class ElephantCharge {
    public const WINDUP = 14;
    public const RUN_TICS = 24;
    public const RECOVERY = 105;
    public const DAMAGE = 16;

    public function tryStart(Combat $c, int $i, int $dx, int $dy, bool $visible): bool {
        $m = $c->things->mobjs;
        if (!$c->player->elephantMode || !$visible || $m[$i]->state !== 1
            || $m[$i]->target >= 0 || $m[$i]->health <= 0 || $m[$i]->type === 2035
            || $c->player->health <= 0 || $m[$i]->cooldown > 0) { return false; }
        int $distance = max(abs($dx), abs($dy));
        int $near = $m[$i]->radius + 16 + 64;
        if ($distance < $near || $distance > 640 || abs($m[$i]->z - $c->player->unitZ()) > 24) { return false; }
        // One 25% chance every two seconds, staggered between actors.
        if (($m[$i]->stateAge + $i * 11) % 70 !== 0 || $c->random() >= 64) { return false; }
        int $length = max(1, (int)sqrt($dx * $dx + $dy * $dy));
        $m[$i]->velX = intdiv($dx * 24, $length);
        $m[$i]->velY = intdiv($dy * 24, $length);
        $m[$i]->velZ = 0;
        $m[$i]->hitDamage = self::DAMAGE;
        $m[$i]->state = 8;
        $m[$i]->stateAge = 0;
        $m[$i]->stateTics = self::WINDUP + self::RUN_TICS;
        $m[$i]->cooldown = self::RECOVERY;
        $m[$i]->angle = $c->monsters->angle($dx, $dy);
        $c->frame($i, $c->rules->attackFrame($m[$i]->type));
        $c->soundAt(Sounds::DEMON_SIGHT, $m[$i]->x, $m[$i]->y);
        return true;
    }

    public function tick(Combat $c, int $i): void {
        $m = $c->things->mobjs;
        if (!$c->player->elephantMode || $c->player->health <= 0 || $m[$i]->health <= 0) {
            $this->stop($c, $i);
            return;
        }
        $m[$i]->stateAge++;
        if ($m[$i]->stateTics > self::RUN_TICS) {
            $m[$i]->stateTics--;
            return;
        }
        if ($m[$i]->stateTics <= 0) { $this->stop($c, $i); return; }
        if ($c->monsters->touchingTarget($c, $i)) { $this->hit($c, $i); return; }
        int $startX = $m[$i]->x; int $startY = $m[$i]->y;
        int $vx = $m[$i]->velX; int $vy = $m[$i]->velY;
        if (abs($vx) > 24 || abs($vy) > 24 || ($vx === 0 && $vy === 0)) { $this->stop($c, $i); return; }
        // Two-unit steps catch bodies and thin walls along the entire dash.
        int $steps = max(1, intdiv(max(abs($vx), abs($vy)) + 1, 2));
        int $end = $steps + 1;
        for ($step = 1; $step < $end; $step++) {
            int $x = $startX + intdiv($vx * $step, $steps);
            int $y = $startY + intdiv($vy * $step, $steps);
            if (!$c->collision->checkActorPosition($x, $y, $m[$i]->z, $i, $m[$i]->radius, $m[$i]->height)) {
                $this->stop($c, $i);
                return;
            }
            int $oldX = $m[$i]->x; int $oldY = $m[$i]->y;
            $m[$i]->x = $x; $m[$i]->y = $y;
            $m[$i]->sector = $c->collision->sectorAt($x, $y);
            if (!$c->rules->flying($m[$i]->type)) { $m[$i]->z = $c->collision->floorZ; }
            for ($t = 0; $t < $c->collision->touchedCount; $t++) {
                int $line = $c->collision->touched[$t];
                int $before = $c->collision->pointSide($line, $oldX, $oldY);
                int $after = $c->collision->pointSide($line, $x, $y);
                if ($before !== $after && $before !== 0) { $c->specials->monsterCross($line, $before, $i); }
                if ($m[$i]->x !== $x || $m[$i]->y !== $y) { $this->stop($c, $i); return; }
            }
            if ($c->monsters->touchingTarget($c, $i)) { $this->hit($c, $i); return; }
        }
        $m[$i]->stateTics--;
        if ($m[$i]->stateTics <= 0) { $this->stop($c, $i); }
    }

    public function hit(Combat $c, int $i): void {
        $c->monsters->damageTarget($c, $i, self::DAMAGE);
        $this->stop($c, $i);
    }

    public function stop(Combat $c, int $i): void {
        $m = $c->things->mobjs;
        $m[$i]->state = 1; $m[$i]->stateAge = 0; $m[$i]->stateTics = 0;
        $m[$i]->velX = 0; $m[$i]->velY = 0; $m[$i]->velZ = 0;
        $m[$i]->hitDamage = 0;
        $m[$i]->cooldown = self::RECOVERY;
        $c->frame($i, 0);
    }
}
