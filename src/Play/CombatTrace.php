<?php

namespace Showcases\Doom\Play;

// Segment tests shared by sight, bullets, fireballs and blast visibility. Heights
// are evaluated at every crossed opening, including raised floors and low ceilings.
final class CombatTrace {
    public Collision $collision;
    public int $wallLine = -1;

    public function __construct(Collision $collision) {
        $this->collision = $collision;
    }

    // First solid wall along a segment, or 65537 if the whole segment is clear.
    public function wallFraction(int $x, int $y, int $z, int $dx, int $dy, int $dz): int {
        $map = $this->collision->map;
        int $nearest = 65537;
        $this->wallLine = -1;
        for ($l = 0; $l < $map->linedefCount; $l++) {
            int $t = $this->collision->traceHit($l, $x, $y, $dx, $dy);
            if ($t <= 0 || $t >= $nearest) {
                continue;
            }
            int $front = $map->linedefs[$l]->front_sector;
            int $back = $map->linedefs[$l]->back_sector;
            bool $blocked = $front < 0 || $back < 0 || ($map->linedefs[$l]->flags & 4) === 0;
            if (!$blocked) {
                int $bottom = max($map->sectors[$front]->floor_height, $map->sectors[$back]->floor_height);
                int $top = min($map->sectors[$front]->ceiling_height, $map->sectors[$back]->ceiling_height);
                int $rise = $dz * $t;
                int $height = $z + ($rise >> 16);
                $blocked = $height <= $bottom || $height >= $top;
            }
            if ($blocked) {
                $nearest = $t;
                $this->wallLine = $l;
            }
        }
        return $nearest;
    }

    public function visible(int $x, int $y, int $z, int $tx, int $ty, int $tz): bool {
        int $dx = $tx - $x;
        int $dy = $ty - $y;
        int $dz = $tz - $z;
        return $this->wallFraction($x, $y, $z, $dx, $dy, $dz) > 65536;
    }

    // Entry into an object's horizontal box, in 16.16 segment coordinates.
    public function boxFraction(int $x, int $y, int $dx, int $dy, int $tx, int $ty, int $radius): int {
        int $enter = 0;
        int $leave = 65536;
        for ($axis = 0; $axis < 2; $axis++) {
            int $origin = $x;
            int $delta = $dx;
            int $center = $tx;
            if ($axis === 1) {
                $origin = $y;
                $delta = $dy;
                $center = $ty;
            }
            int $low = $center - $radius;
            int $high = $center + $radius;
            if ($delta === 0) {
                if ($origin < $low || $origin > $high) {
                    return -1;
                }
                continue;
            }
            int $lowDistance = $low - $origin;
            int $highDistance = $high - $origin;
            int $scaledLow = $lowDistance * 65536;
            int $scaledHigh = $highDistance * 65536;
            int $first = intdiv($scaledLow, $delta);
            int $last = intdiv($scaledHigh, $delta);
            if ($first > $last) {
                int $swap = $first;
                $first = $last;
                $last = $swap;
            }
            $enter = max($enter, $first);
            $leave = min($leave, $last);
            if ($enter > $leave) {
                return -1;
            }
        }
        return $enter;
    }

    // Autoaim selects the nearest shootable object in the horizontal ray. The
    // aimed height must fit a limited vertical cone and pass all wall openings.
    public function aim(int $x, int $y, int $z, int $dx, int $dy, int $ignore): int {
        $things = $this->collision->things;
        int $best = -1;
        int $bestT = 65537;
        for ($i = 0; $i < $things->count; $i++) {
            if ($i === $ignore || $things->mobjs[$i]->active === 0 || $things->mobjs[$i]->health <= 0) {
                continue;
            }
            int $t = $this->boxFraction($x, $y, $dx, $dy, $things->mobjs[$i]->x, $things->mobjs[$i]->y, $things->mobjs[$i]->radius);
            if ($t < 0 || $t >= $bestT) {
                continue;
            }
            int $height = $things->mobjs[$i]->height >> 1;
            int $targetZ = $things->mobjs[$i]->z + $height;
            int $dz = $targetZ - $z;
            int $travelX = $dx * $t;
            int $travelY = $dy * $t;
            int $hitX = $travelX >> 16;
            int $hitY = $travelY >> 16;
            int $distance = max(abs($hitX), abs($hitY));
            if (abs($dz) > $distance + 32) {
                continue;
            }
            int $tx = $x + $hitX;
            int $ty = $y + $hitY;
            if (!$this->visible($x, $y, $z, $tx, $ty, $targetZ)) {
                continue;
            }
            $best = $i;
            $bestT = $t;
        }
        return $best;
    }
}
