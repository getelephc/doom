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
            // Preserve the existing lump names and frame order for saved indices.
            $names[] = "ELTG" . chr(65 + $frame) . "0";
            int $recoil = $frame === 1 ? 3 : ($frame === 2 ? 1 : 0);
            $pictures[] = $this->patch($this->rearHead($frame), 88, 64, -116, -110 - $recoil);
        }
        for ($frame = 0; $frame < 5; $frame++) {
            $names[] = "ELPH" . chr(65 + $frame) . "0";
            $pictures[] = $this->patch($this->elephant($frame), 32, 24, 16, 12);
        }
        // Portraits follow the existing projectile frames so save indices stay stable.
        ElephantFaces $faces = new ElephantFaces();
        for ($slot = 0; $slot < ElephantFaces::COUNT; $slot++) {
            $names[] = ElephantFaces::name($slot);
            $pictures[] = $this->patch($faces->picture($slot), 32, 32, 0, 0);
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

    public function rearHead(int $frame): array {
        $rows = [];
        for ($y = 0; $y < 64; $y++) {
            string $row = "";
            for ($x = 0; $x < 88; $x++) {
                string $ink = ".";
                // Broad, scalloped ears frame the back of the head. On firing,
                // they spread slightly while the entire head recoils downward.
                int $flap = $frame === 1 ? 2 : ($frame === 2 ? 1 : 0);
                int $side = $x < 44 ? $x : 87 - $x;
                int $ex = $side - 19 + $flap;
                int $ey = $y - 31;
                int $ear = $ex * $ex * 484 + $ey * $ey * 289;
                bool $notch = $side < 10 && $y > 42;
                if ($ear <= 139876 && !$notch) {
                    $ink = "0";
                    if ($ear < 118000) {
                        $ink = $ey < -8 ? "3" : "2";
                        if ($side > 25) { $ink = "1"; }
                        if ($ey < -15 && $side > 12 && $side < 24) { $ink = "4"; }
                        int $fold = 35 + intdiv(abs($ex), 2);
                        if ($y === $fold && $side < 26) { $ink = "1"; }
                    }
                }
                // A rounded crown and nape are all that the rear view exposes.
                int $hx = $x - 44; int $hy = $y - 42;
                int $head = $hx * $hx * 676 + $hy * $hy * 625;
                if ($head <= 422500) {
                    $ink = "0";
                    if ($head < 377000) {
                        $ink = $hx < -12 || $hy > 12 ? "2" : "3";
                        int $shineX = $hx - 7; int $shineY = $hy + 10;
                        int $shine = $shineX * $shineX * 196 + $shineY * $shineY * 144;
                        if ($shine < 28224) { $ink = "4"; }
                        if ($shine < 3500 && $shineY < 0) { $ink = "5"; }
                    }
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
