<?php

namespace Showcases\Doom\Graphics;

use Showcases\Doom\Wad\Wad;

// Original pixel art, generated once at boot as ordinary transparent WAD patches.
// The warm red/orange ramp follows elephc's elephant mark. No external assets or
// changes to the player's WAD are needed; normal sprite projection clips the art.
final class ElephantArt {
    public array $colors = [];

    public function install(Wad $wad, Palette $palette): void {
        $ramp = [0x391522, 0x80182D, 0xE52C46, 0xFF6928, 0xFFA22A, 0xFFDA69, 0xFFF1CB, 0x20131D];
        foreach ($ramp as $rgb) { $this->colors[] = $this->nearest($palette, (int)$rgb); }
        $names = []; $pictures = [];
        for ($frame = 0; $frame < 3; $frame++) {
            $names[] = "ELTG" . chr(65 + $frame) . "0";
            int $recoil = $frame === 1 ? 6 : ($frame === 2 ? 2 : 0);
            $pictures[] = $this->patch($this->trunk($frame), 64, 80, -128, -88 - $recoil);
        }
        for ($frame = 0; $frame < 5; $frame++) {
            $names[] = "ELPH" . chr(65 + $frame) . "0";
            $pictures[] = $this->patch($this->elephant($frame), 32, 24, 16, 12);
        }
        $wad->appendLumps($names, $pictures);
    }

    public function nearest(Palette $palette, int $rgb): int {
        int $best = 0; int $distance = 2147483647;
        for ($i = 0; $i < 256; $i++) {
            int $color = $palette->argb[$i];
            int $r = (($color >> 16) & 255) - (($rgb >> 16) & 255);
            int $g = (($color >> 8) & 255) - (($rgb >> 8) & 255);
            int $b = ($color & 255) - ($rgb & 255);
            int $d = $r * $r + $g * $g + $b * $b;
            if ($d < $distance) { $best = $i; $distance = $d; }
        }
        return $best;
    }

    public function trunk(int $frame): array {
        $rows = [];
        for ($y = 0; $y < 80; $y++) {
            string $row = "";
            for ($x = 0; $x < 64; $x++) {
                string $ink = ".";
                int $center = 30 + intdiv((80 - $y) * (80 - $y), 500);
                int $half = 10 + intdiv($y, 5);
                int $dx = $x - $center;
                if ($y >= 19 && abs($dx) <= $half) {
                    $ink = "0";
                    if (abs($dx) < $half - 2) {
                        $ink = $dx < -5 ? "1" : ($dx < 0 ? "2" : ($dx < intdiv($half, 2) ? "3" : "4"));
                        if ($dx === intdiv($half, 2) - 1) { $ink = "5"; }
                        // Wrinkles wrap around the trunk, with a lit upper edge.
                        int $ring = ($y + intdiv(abs($dx), 3)) % 12;
                        if ($y > 34 && $ring < 2) { $ink = $dx < 0 ? "0" : "2"; }
                        else if ($y > 34 && $ring === 2 && $dx > 0) { $ink = "5"; }
                    }
                }
                int $mx = $x - 39; int $my = $y - 19;
                int $rim = $mx * $mx * 81 + $my * $my * 225;
                if ($rim <= 18225) {
                    $ink = "0";
                    if ($rim < 14500) { $ink = $my < -2 ? "5" : "3"; }
                    if ($rim < 9000) { $ink = "1"; }
                    // Two dark nostrils make the muzzle read as a trunk.
                    if (($mx + 5) * ($mx + 5) + $my * $my * 2 < 25
                        || ($mx - 5) * ($mx - 5) + $my * $my * 2 < 25) { $ink = "7"; }
                    if ($frame === 1 && $rim >= 9000 && $rim < 14500) { $ink = "6"; }
                }
                $row .= $ink;
            }
            $rows[] = $row;
        }
        return $rows;
    }

    public function elephant(int $frame): array {
        $rows = [];
        for ($y = 0; $y < 24; $y++) {
            string $row = "";
            for ($x = 0; $x < 32; $x++) {
                string $ink = ".";
                if ($frame >= 2) {
                    int $dx = abs($x - 16); int $dy = abs($y - 12);
                    int $radius = 4 + ($frame - 2) * 3;
                    int $distance = $dx + $dy;
                    if ($distance < $radius && $distance >= max(0, $radius - 5)) {
                        $ink = $distance < $radius - 2 ? "5" : "3";
                    }
                    if ($dx === $dy && $dx >= $radius - 2 && $dx <= $radius) { $ink = "2"; }
                } else {
                    // Tail and alternating feet, then body, head and large ear.
                    if ($x >= 2 && $x <= 4 && $y >= 9 && $y <= 15) { $ink = "1"; }
                    int $step = $frame * 2;
                    if (($x >= 7 && $x <= 11 && $y >= 15 && $y <= 22 - $step)
                        || ($x >= 16 && $x <= 20 && $y >= 15 && $y <= 20 + $step)) { $ink = "2"; }
                    int $bx = $x - 13; int $by = $y - 12;
                    int $body = $bx * $bx * 49 + $by * $by * 100;
                    if ($body <= 4900) { $ink = $body > 3650 ? "0" : ($y < 10 ? "4" : "3"); }
                    int $hx = $x - 24; int $hy = $y - 10;
                    int $head = $hx * $hx + $hy * $hy;
                    if ($head <= 36) { $ink = $head > 26 ? "0" : ($y < 8 ? "5" : "3"); }
                    int $ex = $x - 20; int $ey = $y - 11 - $frame;
                    int $ear = $ex * $ex * 36 + $ey * $ey * 16;
                    if ($ear <= 576) { $ink = $ear > 360 ? "1" : ($x < 20 ? "2" : "3"); }
                    if ($x >= 27 && $x <= 30 && $y >= 11 && $y <= 20) { $ink = $x === 27 || $x === 30 ? "1" : "4"; }
                    if ($x >= 25 && $x <= 29 && $y >= 19 && $y <= 21) { $ink = $y === 21 ? "1" : "4"; }
                    if ($x >= 25 && $x <= 28 && $y === 14) { $ink = "6"; }
                    if ($x === 25 && $y === 8) { $ink = "7"; }
                }
                $row .= $ink;
            }
            $rows[] = $row;
        }
        return $rows;
    }

    // Encode column posts so built-in art uses the existing masked-patch paths.
    public function patch(array $rows, int $width, int $height, int $left, int $top): string {
        string $table = ""; string $posts = "";
        for ($x = 0; $x < $width; $x++) {
            int $offset = 8 + $width * 4 + strlen($posts);
            $table .= $this->dword($offset);
            int $y = 0;
            while ($y < $height) {
                string $row = (string)$rows[$y];
                if ($row[$x] === ".") { $y++; continue; }
                int $start = $y;
                string $pixels = "";
                while ($y < $height) {
                    $row = (string)$rows[$y];
                    if ($row[$x] === ".") { break; }
                    int $shade = ord($row[$x]) - 48;
                    $pixels .= chr((int)$this->colors[$shade]);
                    $y++;
                }
                $posts .= chr($start) . chr($y - $start) . chr(0) . $pixels . chr(0);
            }
            $posts .= chr(255);
        }
        return $this->word($width) . $this->word($height) . $this->word($left) . $this->word($top) . $table . $posts;
    }

    public function word(int $value): string { return chr($value & 255) . chr(($value >> 8) & 255); }
    public function dword(int $value): string { return $this->word($value) . $this->word($value >> 16); }
}
