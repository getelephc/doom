<?php

namespace Showcases\Doom\Play;

use Showcases\Doom\App\Session;

// Feed text events, never held keyboard state: repeats cannot type a cheat per tic.
final class Cheats {
    public string $tail = "";
    public bool $showPosition = false;

    public function feed(Session $s, string $text): void {
        string $lower = strtolower($text);
        for ($i = 0; $i < strlen($lower); $i++) {
            int $c = ord($lower[$i]);
            if (($c < 97 || $c > 122) && ($c < 48 || $c > 57)) {
                continue;
            }
            $this->tail = substr($this->tail . $lower[$i], -16);
            $this->apply($s);
        }
    }

    public function apply(Session $s): void {
        Player $p = $s->player;
        string $message = "";
        if (str_ends_with($this->tail, "idelephc")) {
            $p->elephantMode = !$p->elephantMode;
            $message = "ELEPHC MODE OFF";
            if ($p->elephantMode) {
                $p->weapons |= 4;
                $p->pendingWeapon = 2;
                $message = "ELEPHC MODE ON";
            }
        } else if (str_ends_with($this->tail, "iddqd")) {
            $p->godMode = !$p->godMode;
            $message = "GOD MODE OFF";
            if ($p->godMode) {
                $p->health = max(100, $p->health);
                $s->phase = 0;
                $message = "GOD MODE ON";
            }
        } else if (str_ends_with($this->tail, "idclip") || str_ends_with($this->tail, "idspispopd")) {
            $p->noclip = !$p->noclip;
            $message = "NOCLIP OFF";
            if ($p->noclip) { $message = "NOCLIP ON"; }
        } else if (str_ends_with($this->tail, "idkfa") || str_ends_with($this->tail, "idfa")) {
            $p->inventory->grant($p, str_ends_with($this->tail, "idkfa"));
            $message = "WEAPONS, AMMO AND ARMOR GRANTED";
        } else if (str_ends_with($this->tail, "idchoppers")) {
            $p->pickup(2005);
            $message = $p->message;
        } else if (str_ends_with($this->tail, "iddt")) {
            $s->renderer->automap->reveal = ($s->renderer->automap->reveal + 1) % 3;
            $message = "AUTOMAP CHEAT CHANGED";
        } else if (str_ends_with($this->tail, "idmypos")) {
            $this->showPosition = !$this->showPosition;
            $message = "POSITION DISPLAY CHANGED";
        } else if (strlen($this->tail) >= 9 && substr($this->tail, -9, 8) === "idbehold") {
            string $power = substr($this->tail, -1);
            if ($power === "v") { $p->invulnerable = $p->invulnerable > 0 ? 0 : 1050; }
            else if ($power === "s") { $p->berserk = !$p->berserk; $p->health = max(100, $p->health); }
            else if ($power === "i") { $p->invisible = $p->invisible > 0 ? 0 : 2100; }
            else if ($power === "r") { $p->suit = $p->suit > 0 ? 0 : 2100; }
            else if ($power === "a") { $p->fullMap = !$p->fullMap; }
            else if ($power === "l") { $p->lightAmp = $p->lightAmp > 0 ? 0 : 4200; }
            else { return; }
            $message = "POWER-UP CHANGED";
        } else if (strlen($this->tail) >= 8 && substr($this->tail, -8, 6) === "idclev") {
            string $episode = substr($this->tail, -2, 1);
            string $mission = substr($this->tail, -1);
            if ($episode < "1" || $episode > "4" || $mission < "1" || $mission > "9") { return; }
            string $map = "E" . $episode . "M" . $mission;
            if (!$s->loadMap($map)) { $message = "MAP NOT IN THIS WAD"; }
            else { $message = "WARPED TO " . $map; }
        } else if (strlen($this->tail) >= 7 && substr($this->tail, -7, 5) === "idmus") {
            string $episode = substr($this->tail, -2, 1); string $mission = substr($this->tail, -1);
            if ($episode < "1" || $episode > "4" || $mission < "1" || $mission > "9") { return; }
            string $name = $s->mapMusic("E" . $episode . "M" . $mission);
            $message = "MUSIC NOT IN THIS WAD";
            if ($s->sounds->music->load($name)) { $message = "MUSIC CHANGED"; }
        }
        if ($message !== "") {
            $s->renderer->hud->say($message);
            $this->tail = "";
        }
    }
}
