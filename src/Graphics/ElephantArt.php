<?php

namespace Showcases\Doom\Graphics;

use Showcases\Doom\Wad\Wad;

// Original pixel art, generated once at boot as ordinary transparent WAD patches.
// The warm red/orange ramp follows elephc's elephant mark. No external assets or
// changes to the player's WAD are needed; normal sprite projection clips the art.
final class ElephantArt {
    public array $colors = [];

    public function install(Wad $wad, Palette $palette): void {
        $ramp = [0x391522, 0x80182D, 0xE52C46, 0xFF6928, 0xFFA22A, 0xFFDA69, 0xFFF1CB, 0x20131D, 0x80C840, 0x28752B,
            0x57362E, 0xA56B50, 0xD89B73, 0xF2BE91, 0xFFE0B5, 0x76513C, 0x392B29, 0x253549, 0x475D77, 0x8EADC0, 0x335961];
        foreach ($ramp as $rgb) { $this->colors[] = $this->nearest($palette, (int)$rgb); }
        $names = []; $pictures = [];
        for ($frame = 0; $frame < 3; $frame++) {
            // Preserve the existing lump names and frame order for saved indices.
            $names[] = "ELTG" . chr(65 + $frame) . "0";
            $pictures[] = $this->patch($this->hand($frame), 88, 80, -132, -94);
        }
        for ($frame = 0; $frame < 5; $frame++) {
            $names[] = "ELPH" . chr(65 + $frame) . "0";
            $pictures[] = $this->patch($this->food($frame), 32, 24, 16, 12);
        }
        // Portraits follow the existing projectile frames so save indices stay stable.
        FeederFaces $faces = new FeederFaces();
        for ($slot = 0; $slot < FeederFaces::COUNT; $slot++) {
            $names[] = FeederFaces::name($slot);
            $pictures[] = $this->patch($faces->picture($slot), 32, 32, 0, 0);
        }
        // Append herd frames last, preserving all existing saved definitions.
        ElephantHerd $herd = new ElephantHerd();
        for ($frame = 0; $frame < 11; $frame++) {
            $names[] = "ELEN" . chr(65 + $frame) . "0";
            $pictures[] = $this->patch($herd->picture($frame), 64, 72, 32, 72);
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

    public function hand(int $frame): array {
        $rows = [];
        for ($y = 0; $y < 80; $y++) {
            string $row = "";
            for ($x = 0; $x < 88; $x++) {
                $row .= $this->handPixel($x, $y, $frame);
            }
            $rows[] = $row;
        }
        return $rows;
    }

    public function handPixel(int $x, int $y, int $frame): string {
        int $px = $x; int $py = $y;
        if ($frame === 1) { $px = $x + 10; $py = $y + 9; }
        else if ($frame === 2) { $px = $x - 8; $py = $y - 6; }
        string $ink = ".";
        // The right forearm runs into the lower edge of the view.
        int $axis = 56 + intdiv(($px - 47) * 3, 5);
        if ($px >= 43 && $px < 103 && abs($py - $axis) < 11) {
            $ink = $py < $axis - 5 ? "=" : ($py > $axis + 6 ? ";" : "<");
            if ($px > 69) { $ink = $py < $axis - 4 ? "B" : "A"; }
            if ($px === 69 || $px === 70) { $ink = "D"; }
        }
        int $hx = $px - 43; int $hy = $py - 52;
        int $palm = $hx * $hx * 100 + $hy * $hy * 169;
        if ($palm < 16900) { $ink = $palm > 13500 ? ";" : ($py < 51 ? "=" : "<"); }
        // A leafy carrot sits behind the curled fingers in ready/recovery poses.
        if ($frame !== 1) {
            int $cx = 31 + intdiv(max(0, $py - 16), 9);
            int $half = max(1, 7 - intdiv(max(0, $py - 16), 6));
            if ($py >= 16 && $py <= 55 && abs($px - $cx) <= $half) {
                $ink = $px < $cx ? "3" : "4";
                if ($px === $cx + 3 && $py < 37) { $ink = "5"; }
                if ($py % 7 === 0 && $px < $cx + 2) { $ink = "2"; }
            }
            if ($py >= 3 && $py <= 17 && (abs($px - 31) <= 1 || abs($px - 31) === intdiv(17 - $py, 2))) {
                $ink = $px < 31 ? "9" : "8";
            }
            // Four curled fingers overlap the carrot, with shaded separations.
            if ($px >= 24 && $px <= 42 && $py >= 44 && $py <= 60) {
                int $finger = intdiv($py - 44, 4);
                int $edge = 24 + $finger;
                if ($px >= $edge) { $ink = ($py - 44) % 4 === 3 ? ";" : ($px < 28 ? "<" : "="); }
            }
            int $tx = $px - 43; int $ty = $py - 42;
            if ($tx * $tx * 49 + $ty * $ty * 25 < 1225) { $ink = $py < 41 ? ">" : "="; }
        } else {
            // Open fingers follow through after the carrot leaves the hand.
            for ($finger = 0; $finger < 4; $finger++) {
                int $fy = 39 + $finger * 5;
                int $tip = 19 + abs($finger - 1) * 3;
                if ($px >= $tip && $px <= 42 && $py >= $fy && $py <= $fy + 3) {
                    $ink = $py === $fy + 3 ? ";" : "=";
                }
            }
            if ($px >= 43 && $px <= 48 && $py >= 29 && $py <= 47) { $ink = $px < 45 ? "<" : "="; }
        }
        return $ink;
    }

    public function food(int $frame): array {
        $rows = [];
        for ($y = 0; $y < 24; $y++) {
            string $row = "";
            for ($x = 0; $x < 32; $x++) {
                string $ink = ".";
                if ($frame >= 2) {
                    int $dx = abs($x - 16); int $dy = abs($y - 12);
                    int $spread = 3 + ($frame - 2) * 4;
                    if (($dx === $spread || $dx === $spread - 1) && $dy < 3) { $ink = "4"; }
                    if (($dy === $spread || $dy === $spread - 1) && $dx < 3) { $ink = "8"; }
                } else {
                    // A carrot with a leafy crown, bobbing in flight.
                    int $cy = $y - $frame;
                    int $width = intdiv(22 - $cy, 3);
                    if ($cy >= 7 && $cy <= 21 && abs($x - 16) <= $width) {
                        $ink = $x < 16 ? "3" : "4";
                        if ($cy % 4 === 0 && $x < 17) { $ink = "2"; }
                        if ($x === 18 && $cy < 14) { $ink = "5"; }
                    }
                    if ($cy >= 1 && $cy <= 7 && (abs($x - 16) <= 1 || abs($x - 16) === 7 - $cy)) {
                        $ink = $x < 16 ? "9" : "8";
                    }
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
