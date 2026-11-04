<?php

namespace Showcases\Doom\Graphics;

// Original 32x32 human portraits with a plain cap and short beard. Expressions
// use existing player state and keep the legacy patch names and slot ordering.
final class FeederFaces {
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
        // Neck, shoulders, ears and a tapered human jaw.
        if ($y >= 26 && $x >= 12 && $x <= 21) { $ink = $x < 16 ? ";" : "<"; }
        if ($y >= 30 && $x >= 5 && $x <= 28) { $ink = $x < 17 ? "A" : "D"; }
        if ($y >= 15 && $y <= 21 && (($x >= 5 && $x <= 8) || ($x >= 25 && $x <= 28))) {
            $ink = $y === 18 ? ";" : "<";
        }
        int $hx = $x - 16; int $hy = $y - 17;
        int $head = $hx * $hx * 169 + $hy * $hy * 100;
        if ($head < 16900) {
            $ink = $x < 11 ? ";" : ($x > 21 ? "<" : "=");
            if ($y >= 23) { $ink = ($x + $y) % 3 === 0 ? "?" : "<"; }
            if ($y >= 27) { $ink = $x < 16 ? ":" : ";"; }
        }
        // Brown sideburns and a little side-swept hair under the cap.
        if (($x >= 7 && $x <= 9 && $y >= 10 && $y <= 19)
            || ($x >= 23 && $x <= 25 && $y >= 10 && $y <= 17)) { $ink = "?"; }
        if ($x >= 9 && $x <= 17 && $y >= 9 && $y <= 12 && $x + $y < 26) { $ink = "?"; }
        // Short moustache, nose, and smile. No lettering or logo on the cap.
        if ($y >= 18 && $y <= 21 && $x >= 15 && $x <= 18) { $ink = $x === 15 ? "<" : ">"; }
        if ($y === 22 && $x >= 12 && $x <= 21) { $ink = ":"; }
        if ($x >= 12 && $x <= 21 && $y >= 23 && $y <= 25) {
            $ink = $y === 24 ? "6" : ";";
            if ($expression === 1) { $ink = $x >= 15 && $x <= 18 ? "@" : "<"; }
            else if ($expression === 2 || $pain >= 3) { $ink = $y === 24 ? ":" : "<"; }
            else if ($expression === 3) { $ink = $y === 25 ? ":" : "6"; }
            else if ($expression === 5) { $ink = $y === 25 ? ":" : "<"; }
        }
        for ($eye = 0; $eye < 2; $eye++) {
            int $cx = $eye === 0 ? 12 : 21;
            int $dx = $x - $cx; int $ey = $y - 16;
            if ($expression === 5) {
                if (abs($dx) <= 2 && abs($dx) === abs($ey)) { $ink = "@"; }
            } else if ($expression === 4) {
                if (abs($dx) <= 3 && abs($ey) <= 1) { $ink = "@"; }
                if ($dx === -1 && $ey === -1) { $ink = "C"; }
                if ($x >= 15 && $x <= 18 && $y === 16) { $ink = "@"; }
            } else if ($expression === 3) {
                if (abs($dx) <= 2 && $ey === -1 + intdiv(abs($dx), 2)) { $ink = "@"; }
            } else {
                int $height = $expression === 1 ? 2 : 1;
                if (abs($dx) <= 2 && abs($ey) <= $height) { $ink = "6"; }
                if ($dx === $look && abs($ey) <= 1) { $ink = "@"; }
                int $brow = -3;
                if ($expression === 2) { $brow = -3 + ($eye === 0 ? intdiv($dx + 2, 2) : intdiv(2 - $dx, 2)); }
                if (abs($dx) <= 2 && $ey === $brow) { $ink = "?"; }
            }
        }
        // Increasing tiredness uses cheek marks, leaving the cap readable.
        if ($pain > 0 && $x === 11 && $y >= 20 && $y < 20 + $pain) { $ink = ";"; }
        if ($pain > 1 && $x === 23 && $y === 20 + $pain) { $ink = ":"; }
        int $capX = $x - 16; int $capY = $y - 9;
        if ($y <= 10 && $capX * $capX * 49 + $capY * $capY * 121 < 5929) {
            $ink = $x < 12 ? "A" : "B";
            if ($x === 16 && $y >= 3 && $y <= 7) { $ink = "C"; }
        }
        if ($y >= 9 && $y <= 11 && $x >= 7 && $x <= 29) {
            $ink = $y === 11 ? "@" : "A";
            if ($y === 9 && $x > 19) { $ink = "B"; }
        }
        return $ink;
    }
}
