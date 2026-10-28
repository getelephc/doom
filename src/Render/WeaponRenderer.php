<?php

namespace Showcases\Doom\Render;

use Showcases\Doom\Play\Player;
use Showcases\Doom\Support\Trig;
use Showcases\Doom\Wad\Wad;

// First-person patches contain their own placement in the original 320x200
// coordinate system. Draw at (0,0), then cover the bottom 32 rows with the HUD.
final class WeaponRenderer {
    public Wad $wad;
    public buffer<int> $patches;
    public int $available;
    public buffer<int> $trunk;

    public function __construct(Wad $wad) {
        $this->wad = $wad;
        $this->trunk = buffer_new<int>(3);
        for ($f = 0; $f < 3; $f++) {
            $this->trunk[$f] = $wad->find("ELTG" . chr(65 + $f) . "0");
        }
        $this->patches = buffer_new<int>(90);
        $prefixes = ["", "PUNG", "PISG", "SHTG", "CHGG", "MISG", "PLSG", "BFGG", "SAWG"];
        for ($weapon = 1; $weapon < 9; $weapon++) {
            for ($frame = 0; $frame < 5; $frame++) {
                int $at = $weapon * 10 + $frame;
                $this->patches[$at] = $wad->find((string)$prefixes[$weapon] . chr(65 + $frame) . "0");
            }
        }
        $this->available = 6;
        for ($w = 3; $w < 9; $w++) {
            if ($this->patches[$w * 10] >= 0) { $this->available |= 1 << $w; }
        }
        $flashes = ["", "", "PISF", "SHTF", "CHGF", "MISF", "PLSF", "BFGF", ""];
        for ($w = 2; $w < 8; $w++) {
            $this->patches[$w * 10 + 5] = $wad->find((string)$flashes[$w] . "A0");
            $this->patches[$w * 10 + 6] = $wad->find((string)$flashes[$w] . "B0");
        }
        $this->patches[25] = $wad->find("PISFA0");
        $this->patches[35] = $wad->find("SHTFA0");
        $this->patches[36] = $wad->find("SHTFB0");
    }

    public function draw(Screen $screen, Player $player, Trig $trig, int $tic): void {
        if ($player->health <= 0) {
            return;
        }
        int $base = $player->weapon * 10;
        int $frame = 0;
        int $flash = -1;
        if ($player->weaponCooldown > 0) {
            int $age = $player->weaponAge;
            if ($player->weapon === 1) {
                $frame = min(3, intdiv($age, 4));
                if ($age > 11) {
                    $frame = 1;
                }
            } else if ($player->weapon === 2) {
                if ($age < 8) {
                    $frame = 1;
                    $flash = $this->patches[25];
                }
            } else if ($player->weapon === 3) {
                if ($age < 7) {
                    $flash = $this->patches[35];
                } else if ($age < 14) {
                    $frame = 1;
                } else if ($age < 21) {
                    $frame = 2;
                } else if ($age < 28) {
                    $frame = 3;
                } else {
                    $frame = 1;
                }
            } else if ($player->weapon === 4) {
                $frame = $age % 2;
                $flash = $this->patches[45 + $age % 2];
            } else if ($player->weapon === 5 || $player->weapon === 6) {
                $frame = 1;
                if ($age < 8) { $flash = $this->patches[$base + 5]; }
            } else if ($player->weapon === 7) {
                $frame = $age < 20 ? 1 : 2;
                if ($age >= 20 && $age < 28) { $flash = $this->patches[75]; }
            } else if ($player->weapon === 8) {
                $frame = 2 + ($age & 1);
            }
        } else if ($player->weapon === 8) {
            $frame = intdiv($tic, 4) & 1;
        }
        int $speed = max(abs($player->momX), abs($player->momY)) >> 16;
        int $bob = min(4, intdiv($speed, 3));
        int $x = ($trig->cos($tic * 409) * $bob) >> 16;
        int $y = abs(($trig->sin($tic * 409) * $bob) >> 16);
        if ($player->weapon === 2 && $player->elephantMode) {
            int $trunkFrame = 0;
            if ($player->weaponCooldown > 0 && $player->weaponAge < 9) {
                $trunkFrame = $player->weaponAge < 5 ? 1 : 2;
            }
            $screen->drawPatch($this->wad, $this->trunk[$trunkFrame], $x, $y);
            return;
        }
        int $slot = $base + $frame;
        int $patch = $this->patches[$slot];
        if ($patch < 0) {
            $patch = $this->patches[$base];
        }
        $screen->drawPatch($this->wad, $patch, $x, $y);
        $screen->drawPatch($this->wad, $flash, $x, $y);
    }
}
