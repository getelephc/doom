<?php

namespace Showcases\Doom\Graphics;

// Original front-facing herd art: walk, chew, tip over and sleep. Sleep letters
// belong to the patch, so walls and masked textures clip them with the elephant.
final class ElephantHerd {
    public function picture(int $frame): array {
        $rows = [];
        for ($y = 0; $y < 72; $y++) {
            string $row = "";
            for ($x = 0; $x < 64; $x++) {
                int $px = $x; int $py = $y;
                // Two intermediate poses rotate the standing elephant onto its side.
                if ($frame === 5) {
                    $px = 32 + intdiv(($x - 32) * 4 + ($y - 44) * 3, 5);
                    $py = 44 + intdiv(($y - 44) * 4 - ($x - 32) * 3, 5);
                } else if ($frame === 6) {
                    $px = 32 + ($y - 42);
                    $py = 44 - ($x - 32);
                }
                if ($frame >= 9) { $py = $y - 3; }
                if ($frame >= 7 && $frame < 9) { $row .= $this->sleeping($x, $y, $frame); }
                else { $row .= $this->standing($px, $py, $frame); }
            }
            $rows[] = $row;
        }
        return $rows;
    }

    public function standing(int $x, int $y, int $frame): string {
        string $ink = ".";
        int $step = $frame < 4 ? ($frame & 1) * 2 : 0;
        if ($frame >= 9) { $step = ($frame - 9) * 2; }
        int $bx = $x - 32; int $by = $y - 47;
        int $body = $bx * $bx * 361 + $by * $by * 400;
        if ($body < 144400) { $ink = $body > 119000 ? "0" : ($x < 26 ? "2" : "3"); }
        if (($x >= 18 && $x < 28 && $y >= 55 && $y < 71 - $step)
            || ($x >= 36 && $x < 46 && $y >= 55 && $y < 69 + $step)) {
            $ink = $y > 65 ? "1" : "3";
            if ($y > 67 && $x % 4 === 0) { $ink = "5"; }
        }
        int $side = $x < 32 ? $x : 63 - $x;
        int $earX = $frame >= 9 ? 11 : 13;
        int $ex = $side - $earX; int $ey = $y - 34;
        int $flap = $frame === 1 || $frame === 3 ? 2 : 0;
        int $ear = $ex * $ex * 289 + $ey * $ey * (100 + $flap * 12);
        if ($ear < 28900) { $ink = $ear > 23000 ? "0" : ($side < 10 ? "2" : "4"); }
        int $hx = $x - 32; int $hy = $y - 31;
        int $head = $hx * $hx * 256 + $hy * $hy * 225;
        if ($head < 57600) { $ink = $head > 47500 ? "0" : ($y < 28 ? "4" : "3"); }
        if ($y >= 42 && $y <= 47 && (($x >= 21 && $x <= 25) || ($x >= 39 && $x <= 43))) { $ink = "6"; }
        int $curl = $frame === 4 ? 3 : 0;
        if ($frame >= 9) { $curl = $frame === 9 ? 8 : 5; }
        if ($x >= 28 && $x <= 36 && $y >= 37 && $y <= 59 - $curl) { $ink = $x < 30 ? "1" : "4"; }
        if ($x >= 34 && $x <= 42 && $y >= 54 - $curl && $y <= 59 - $curl) { $ink = $y > 57 - $curl ? "1" : "4"; }
        if (($x >= 23 && $x <= 26 || $x >= 38 && $x <= 41) && $y >= 30 && $y <= 34) {
            $ink = "7";
            if ($y === 30 && ($x === 24 || $x === 39)) { $ink = "6"; }
        }
        // Chewing is a happy, closed-eye expression, never a pain pose.
        if ($frame === 4 && $y >= 30 && $y <= 34 && (($x >= 23 && $x <= 26) || ($x >= 38 && $x <= 41))) {
            $ink = $y === 32 ? "7" : "3";
        }
        if ($frame >= 9 && $y === 29 && (($x >= 22 && $x <= 27) || ($x >= 37 && $x <= 42))) { $ink = "0"; }
        return $ink;
    }

    public function sleeping(int $x, int $y, int $frame): string {
        string $ink = ".";
        int $breath = $frame - 7;
        int $bx = $x - 28; int $by = $y - 57 + $breath;
        int $body = $bx * $bx * 144 + $by * $by * 484;
        if ($body < 69696) { $ink = $body > 56000 ? "0" : ($y < 55 ? "4" : "3"); }
        if ($x >= 13 && $x <= 23 && $y >= 65 && $y <= 70) { $ink = "2"; }
        int $hx = $x - 46; int $hy = $y - 58;
        int $head = $hx * $hx + $hy * $hy;
        if ($head < 121) { $ink = $head > 90 ? "0" : "3"; }
        int $ex = $x - 38; int $ey = $y - 56;
        int $ear = $ex * $ex + $ey * $ey;
        if ($ear < 64) { $ink = $ear > 44 ? "1" : "4"; }
        if ($x >= 51 && $x <= 61 && $y >= 62 && $y <= 67) { $ink = $y > 65 ? "1" : "4"; }
        if ($x >= 47 && $x <= 52 && $y === 57) { $ink = "7"; }
        if ($x >= 49 && $x <= 55 && $y === 61) { $ink = "6"; }
        // Alternating small and large letters read zZzZ above the full belly.
        for ($letter = 0; $letter < 4; $letter++) {
            int $size = ($letter & 1) === 0 ? 4 : 6;
            int $lx = $x - (8 + $letter * 12);
            int $ly = $y - (30 - $letter * 5 - $breath * 2);
            if ($lx >= 0 && $lx < $size && $ly >= 0 && $ly < $size) {
                if ($ly === 0 || $ly === $size - 1 || $lx === $size - 1 - $ly) { $ink = "6"; }
            }
        }
        return $ink;
    }
}
