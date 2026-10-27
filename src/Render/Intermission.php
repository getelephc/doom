<?php

namespace Showcases\Doom\Render;

use Showcases\Doom\Play\Combat;
use Showcases\Doom\Wad\Wad;

// A completed level stays visible until the player explicitly continues.
final class Intermission {
    public Wad $wad;
    public int $background;
    public buffer<int> $digits;

    public function __construct(Wad $wad) {
        $this->wad = $wad;
        $this->background = $wad->find("WIMAP0");
        $this->digits = buffer_new<int>(10);
        for ($d = 0; $d < 10; $d++) {
            $this->digits[$d] = $wad->find("WINUM" . $d);
        }
    }

    public function draw(Hud $hud, Combat $combat, string $map, int $tics): void {
        $hud->screen->fill(0);
        int $episode = (int)substr($map, 1, 1) - 1;
        int $background = $this->wad->find($episode < 3 ? "WIMAP" . $episode : "INTERPIC");
        $hud->screen->drawPatch($this->wad, $background >= 0 ? $background : $this->background, 0, 0);
        int $mission = (int)substr($map, 3, 1) - 1;
        int $title = $this->wad->find("WILV" . $episode . $mission);
        if ($title >= 0) {
            int $width = $this->wad->i16($this->wad->lumpOffset[$title]);
            $hud->screen->drawPatch($this->wad, $title, intdiv(320 - $width, 2), 3);
        }
        $this->label($hud, "WIF", "FINISHED!", 100, 24);
        $this->label($hud, "WIOSTK", "KILLS", 50, 58);
        $this->label($hud, "WIOSTI", "ITEMS", 50, 83);
        $this->label($hud, "WISCRT2", "SECRETS", 50, 108);
        $this->label($hud, "WITIME", "TIME", 50, 145);
        $this->number($hud, $this->percent($combat->kills, $combat->totalKills), 260, 58);
        $this->number($hud, $this->percent($combat->items, $combat->totalItems), 260, 83);
        $this->number($hud, $this->percent($combat->secrets, $combat->totalSecrets), 260, 108);
        int $percent = $this->wad->find("WIPCNT");
        for ($row = 0; $row < 3; $row++) {
            $hud->screen->drawPatch($this->wad, $percent, 260, 58 + $row * 25);
        }
        int $seconds = intdiv($tics, 35);
        $hud->text((string)intdiv($seconds, 60) . ":" . str_pad((string)($seconds % 60), 2, "0", STR_PAD_LEFT), 220, 147);
        $hud->text("ENTER OR FIRE TO CONTINUE", 65, 185);
    }

    public function percent(int $value, int $total): int {
        if ($total === 0) {
            return 100;
        }
        return min(100, intdiv($value * 100, $total));
    }

    public function label(Hud $hud, string $name, string $fallback, int $x, int $y): void {
        int $lump = $this->wad->find($name);
        if ($lump < 0) {
            $hud->text($fallback, $x, $y);
        } else {
            $hud->screen->drawPatch($this->wad, $lump, $x, $y);
        }
    }

    public function number(Hud $hud, int $value, int $right, int $y): void {
        int $remaining = $value;
        int $x = $right;
        for ($place = 0; $place < 3; $place++) {
            int $digit = $remaining % 10;
            int $lump = $this->digits[$digit];
            if ($lump < 0) {
                $hud->text((string)$value, $right - 24, $y);
                return;
            }
            $x -= $this->wad->i16($this->wad->lumpOffset[$lump]);
            $hud->screen->drawPatch($this->wad, $lump, $x, $y);
            $remaining = intdiv($remaining, 10);
            if ($remaining === 0) {
                return;
            }
        }
    }
}
