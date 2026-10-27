<?php

namespace Showcases\Doom\App;

use Showcases\Doom\SDL\Input;

// SDL-free menu controller. An open menu pauses simulation, not music playback.
final class Menu {
    // 0 closed, 1 main, 2 episode, 3 skill, 4 options, 5 save, 6 load, 7 help.
    public int $page = 0;
    public int $selected = 0;
    public int $episode = 1;
    public int $held = 0;
    public int $quickSlot = 0;
    public bool $title = false;
    public bool $quit = false;
    public string $message = "";

    public function handle(Session $s, Input $input, ptr $keys): bool {
        int $action = 0;
        $scans = [41, 82, 81, 80, 79, 40, 44, 58, 59, 60, 63, 66];
        for ($i = 0; $i < count($scans); $i++) {
            int $scan = (int)$scans[$i];
            if ($input->keyPressed($keys, $scan) !== 0) { $action = $scan; break; }
        }
        if ($action === $this->held) { return $this->page !== 0; }
        $this->held = $action;
        if ($action === 0) { return $this->page !== 0; }
        if ($action === 63 && $this->page === 0) { $this->slot($s, $this->quickSlot, true); return true; }
        if ($action === 66 && $this->page === 0) { $this->slot($s, $this->quickSlot, false); return true; }
        if ($action === 58) { $this->page = 7; $this->title = false; return true; }
        if ($action === 59 || $action === 60) { $this->page = $action === 59 ? 5 : 6; $this->selected = $this->quickSlot; $this->title = false; return true; }
        if ($action === 41) {
            if ($this->page === 0) { $this->page = 1; }
            else if ($this->page === 1) { $this->page = 0; }
            else { $this->page = 1; }
            $this->title = false; $this->selected = 0;
            if ($this->page === 0 && $s->sounds->music->name === "D_INTRO") { $s->startMapMusic(); }
            return true;
        }
        if ($this->page === 0) { return false; }
        if ($this->title) {
            if ($action === 40 || $action === 44) { $this->title = false; }
            return true;
        }
        if ($this->page === 7) { $this->page = 1; return true; }
        int $count = $this->page === 1 ? 7 : ($this->page === 2 ? 4 : ($this->page === 3 ? 5 : ($this->page === 4 ? 4 : 6)));
        if ($action === 82) { $this->selected = ($this->selected + $count - 1) % $count; }
        else if ($action === 81) { $this->selected = ($this->selected + 1) % $count; }
        else if ($this->page === 4 && ($action === 80 || $action === 79 || $action === 40)) { $this->option($s, $action === 80 ? -1 : 1); }
        else if ($action === 40 || $action === 44) { $this->confirm($s); }
        return true;
    }

    public function confirm(Session $s): void {
        if ($this->page === 1) {
            if ($this->selected === 0) { $this->page = 0; if ($s->sounds->music->name === "D_INTRO") { $s->startMapMusic(); } }
            else if ($this->selected === 1) { $this->page = 2; }
            else if ($this->selected === 2) { $this->page = 5; }
            else if ($this->selected === 3) { $this->page = 6; }
            else if ($this->selected === 4) { $this->page = 4; }
            else if ($this->selected === 5) { $this->page = 7; }
            else { $this->quit = true; }
            $this->selected = 0;
        } else if ($this->page === 2) {
            int $episode = $this->selected + 1;
            if ($s->wad->find("E" . $episode . "M1") < 0) { $this->message = "EPISODE NOT IN THIS WAD"; return; }
            $this->episode = $episode; $this->page = 3; $this->selected = 2;
        } else if ($this->page === 3) {
            $s->config->skill = $this->selected + 1;
            $s->player->reset();
            if ($s->loadMap("E" . $this->episode . "M1")) { $this->page = 0; }
        } else if ($this->page === 5 || $this->page === 6) {
            $this->slot($s, $this->selected, $this->page === 5);
        }
    }

    public function option(Session $s, int $direction): void {
        if ($this->selected === 0) { $s->sounds->music->volume = max(0, min(256, $s->sounds->music->volume + $direction * 16)); }
        else if ($this->selected === 1) { $s->sounds->volume = max(0, min(256, $s->sounds->volume + $direction * 16)); }
        else if ($this->selected === 2) { $s->config->mouseEnabled = !$s->config->mouseEnabled; }
        else { $s->config->alwaysRun = !$s->config->alwaysRun; }
    }

    public function slot(Session $s, int $slot, bool $save): void {
        string $directory = $s->config->saveDirectory;
        if ($save && !is_dir($directory) && !mkdir($directory, 0755, true)) { $this->message = "CANNOT CREATE SAVE DIRECTORY"; return; }
        string $path = $directory . "/slot" . $slot . ".json";
        bool $ok = $save ? $s->saves->write($s, $path) : $s->saves->read($s, $path);
        $this->message = $save ? "SAVE FAILED" : "LOAD FAILED OR INCOMPATIBLE SAVE";
        if ($ok) {
            $this->quickSlot = $slot; $this->page = 0;
            $s->renderer->hud->say($save ? "GAME SAVED" : "GAME LOADED");
            $this->message = "";
        }
    }

    public function draw(Session $s): void {
        $hud = $s->renderer->hud;
        $screen = $s->renderer->screen;
        if ($this->title) {
            $screen->fill(0);
            $screen->drawPatch($s->wad, $s->wad->find("TITLEPIC"), 0, 0);
            $hud->text("PRESS ENTER", 115, 180);
            return;
        }
        $screen->fillRect(25, 10, 270, 180, 0);
        $screen->drawPatch($s->wad, $s->wad->find("M_DOOM"), 94, 5);
        $labels = ["RESUME", "NEW GAME", "SAVE GAME", "LOAD GAME", "OPTIONS", "HELP", "QUIT"];
        string $heading = "MAIN MENU";
        if ($this->page === 2) { $heading = "CHOOSE EPISODE"; $labels = ["KNEE-DEEP IN THE DEAD", "THE SHORES OF HELL", "INFERNO", "THY FLESH CONSUMED"]; }
        else if ($this->page === 3) { $heading = "CHOOSE DIFFICULTY"; $labels = ["I'M TOO YOUNG TO DIE", "HEY, NOT TOO ROUGH", "HURT ME PLENTY", "ULTRA-VIOLENCE", "NIGHTMARE!"]; }
        else if ($this->page === 4) {
            $heading = "OPTIONS";
            $labels = ["MUSIC " . $s->sounds->music->volume, "SOUND " . $s->sounds->volume, "MOUSE " . ($s->config->mouseEnabled ? "ON" : "OFF"), "ALWAYS RUN " . ($s->config->alwaysRun ? "ON" : "OFF")];
        } else if ($this->page === 5 || $this->page === 6) {
            $heading = $this->page === 5 ? "SAVE GAME" : "LOAD GAME";
            $labels = [];
            for ($i = 0; $i < 6; $i++) { $labels[] = "SLOT " . ($i + 1) . (is_file($s->config->saveDirectory . "/slot" . $i . ".json") ? " - SAVED" : " - EMPTY"); }
        } else if ($this->page === 7) {
            $heading = "CONTROLS AND CHEATS";
            $labels = ["WASD / ARROWS MOVE", "MOUSE TURNS, CLICK / CTRL FIRES", "1-7 WEAPONS, E / SPACE USE", "TAB MAP, SHIFT RUN", "F2 SAVE, F3 LOAD, F6/F9 QUICK", "IDDQD GOD, IDKFA ARMS/KEYS", "IDSPISPOPD NOCLIP, IDCLEV## WARP"];
        }
        $hud->text($heading, 45, 66);
        for ($i = 0; $i < count($labels); $i++) {
            $hud->text((string)$labels[$i], 55, 85 + $i * 14);
            if ($i === $this->selected && $this->page !== 7) { $screen->drawPatch($s->wad, $s->wad->find("M_SKULL1"), 28, 82 + $i * 14); $hud->text(">", 43, 85 + $i * 14); }
        }
        $hud->text($this->message, 30, 181);
    }
}
