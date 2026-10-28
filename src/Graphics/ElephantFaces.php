<?php

namespace Showcases\Doom\Graphics;

// A 32x32 elephant portrait in the same indexed ramp as the trunk. All poses are
// generated at boot; the HUD only selects a cached patch from current game state.
final class ElephantFaces {
    public const HURT = 15;
    public const FIRE = 20;
    public const GRIN = 25;
    public const GOD = 30;
    public const DEAD = 31;
    public const COUNT = 32;

    public static function name(int $slot): string {
        return "ELFACE" . ($slot < 10 ? "0" : "") . $slot;
    }

    public function picture(int $slot): array {
        int $pain = 0; int $expression = 0; int $look = 0;
        if ($slot < self::HURT) {
            $pain = intdiv($slot, 3);
            $look = $slot % 3 - 1;
        } else if ($slot < self::GOD) {
            $pain = ($slot - self::HURT) % 5;
            $expression = 1 + intdiv($slot - self::HURT, 5);
        } else { $expression = $slot === self::GOD ? 4 : 5; }
        $rows = [];
        for ($y = 0; $y < 32; $y++) {
            string $row = "";
            for ($x = 0; $x < 32; $x++) {
                $row .= $this->pixel($x, $y, $pain, $expression, $look);
            }
            $rows[] = $row;
        }
        return $rows;
    }

    public function pixel(int $x, int $y, int $pain, int $expression, int $look): string {
        string $ink = ".";
        int $droop = intdiv($pain, 2);
        if ($expression === 1) { $droop = -1; }
        if ($expression === 5) { $droop = 3; }
        int $earY = $y - 15 - $droop;
        int $earX = $x < 16 ? $x - 6 : $x - 25;
        int $ear = $earX * $earX * 81 + $earY * $earY * 36;
        if ($ear <= 2916) {
            $ink = "0";
            if ($ear < 2300) { $ink = $earY < -3 ? "3" : "1"; }
            if ($ear < 1300) { $ink = "2"; }
        }
        int $headX = $x - 16;
        int $headY = $y - 13 - ($expression === 5 ? 3 : 0);
        int $head = $headX * $headX * 144 + $headY * $headY * 81;
        if ($head <= 11664) {
            $ink = $head > 9800 ? "0" : ($x < 12 ? "2" : ($x > 21 ? "3" : "4"));
            if ($headY < -5 && abs($headX) < 4) { $ink = "5"; }
            if ($pain >= 1 && $x === 23 && $y >= 14 && $y <= 16) { $ink = "1"; }
            if ($pain >= 2 && $x >= 10 && $x <= 13 && $y >= 15 && $y <= 17) { $ink = "1"; }
            if ($pain >= 4 && $x >= 18 && $x <= 23 && $y === 7) { $ink = "6"; }
        }
        // A downturned, curling trunk and two small ivory tusks frame the mouth.
        if ($y >= 15 && $y <= 29) {
            int $curl = $y > 23 ? intdiv(($y - 23) * ($y - 23), 8) : 0;
            int $center = 16 + $curl;
            int $radius = $y < 20 ? 4 : 3;
            int $distance = abs($x - $center);
            if ($distance <= $radius) {
                $ink = $distance === $radius ? "1" : ($x < $center ? "3" : "4");
                if ($y === 20 || $y === 24) { $ink = $x < $center ? "1" : "2"; }
            }
            if ($y >= 27 && $y <= 28 && ($x === $center - 1 || $x === $center + 1)) { $ink = "7"; }
        }
        if ($y >= 19 && $y <= 23) {
            int $tip = intdiv($y - 19, 2);
            if ($x === 11 - $tip || $x === 21 + $tip) { $ink = "6"; }
            if ($y < 22 && ($x === 12 - $tip || $x === 20 + $tip)) { $ink = "5"; }
        }
        if ($expression === 3 && $y >= 19 && $y <= 21 && (($x >= 9 && $x <= 11) || ($x >= 21 && $x <= 23))) {
            $ink = $y === 21 ? "0" : "6";
        }
        // Eyes track left/center/right. Damage opens them, firing lowers the
        // brows, pickups squint happily, god mode wears shades, death has X eyes.
        for ($eye = 0; $eye < 2; $eye++) {
            int $cx = $eye === 0 ? 11 : 21;
            int $dx = $x - $cx;
            int $ey = $y - 11;
            if ($expression === 5) {
                $ey = $y - 15;
                if (abs($dx) <= 2 && abs($dx) === abs($ey)) { $ink = "7"; }
            } else if ($expression === 4) {
                if (abs($dx) <= 4 && $ey >= -2 && $ey <= 2) { $ink = $ey === -2 ? "5" : "7"; }
                if (abs($dx) <= 2 && $ey === -1) { $ink = "3"; }
                if ($x >= 15 && $x <= 17 && $y === 10) { $ink = "7"; }
            } else if ($expression === 3) {
                if (abs($dx) <= 2 && $ey === -1 + intdiv(abs($dx), 2)) { $ink = "7"; }
            } else {
                int $height = $expression === 1 ? 2 : 1;
                if (abs($dx) <= 2 && abs($ey) <= $height) { $ink = "6"; }
                if ($dx === $look && abs($ey) <= 1) { $ink = "7"; }
                int $brow = -3;
                if ($expression === 2) { $brow = -3 + ($eye === 0 ? intdiv($dx + 2, 2) : intdiv(2 - $dx, 2)); }
                else if ($pain >= 3) { $brow = -2; }
                if (abs($dx) <= 3 && $ey === $brow) { $ink = "0"; }
            }
        }
        return $ink;
    }
}
