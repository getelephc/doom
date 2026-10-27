<?php

namespace Showcases\Doom\Render;

use Showcases\Doom\Wad\Wad;

// Episode endings use the WAD's own pictures and independently written captions.
final class Finale {
    public Wad $wad;
    public function __construct(Wad $wad) { $this->wad = $wad; }

    public function draw(Hud $hud, int $episode, int $tics): void {
        $hud->screen->fill(0);
        string $picture = "CREDIT";
        if ($episode === 2) { $picture = "VICTORY2"; }
        else if ($episode === 4) { $picture = "ENDPIC"; }
        if ($episode === 3 && $this->wad->find("PFUB1") >= 0) {
            int $scroll = max(0, min(320, intdiv($tics - 200, 2)));
            $hud->screen->drawPatch($this->wad, $this->wad->find("PFUB1"), -$scroll, 0);
            $hud->screen->drawPatch($this->wad, $this->wad->find("PFUB2"), 320 - $scroll, 0);
        } else {
            $hud->screen->drawPatch($this->wad, $this->wad->find($picture), 0, 0);
        }
        $hud->screen->fillRect(0, 65, 320, 65, 0);
        $hud->text("EPISODE " . $episode . " COMPLETE", 65, 73);
        if ($episode === 1) { $hud->text("PHOBOS IS SECURE. HELL AWAITS.", 35, 93); }
        else if ($episode === 2) { $hud->text("THE CYBERDEMON HAS FALLEN.", 45, 93); }
        else if ($episode === 3) { $hud->text("HELL IS SILENT. TIME TO GO HOME.", 25, 93); }
        else { $hud->text("THE INVASION HAS BEEN STOPPED.", 30, 93); }
        $hud->text("ENTER FOR THE MAIN MENU", 65, 115);
    }
}
