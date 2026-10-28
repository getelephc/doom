<?php

namespace Showcases\Doom\App;

// Versioned JSON saves are tied to WAD contents and validated before live state
// changes. This is the showcase's portable format, not the DOS savegame format.
final class SaveGame {
    public SaveRecords $records;
    public string $wadHash = "";
    public function __construct() { $this->records = new SaveRecords(); }

    public function fingerprint(Session $s): string {
        if ($this->wadHash === "") {
            $this->wadHash = hash("sha256", ptr_read_string(buffer_ptr($s->wad->data), $s->wad->sourceSize));
        }
        return $this->wadHash;
    }

    public function encode(Session $s): string {
        $objects = []; $sectors = []; $sides = []; $lines = []; $movers = []; $lights = []; $buttons = []; $secrets = []; $frames = [];
        for ($i = 0; $i < $s->things->count; $i++) { $objects[] = $this->records->readMobj($s->things->mobjs, $i); }
        for ($i = 0; $i < $s->map->sectorCount; $i++) {
            $sectors[] = $this->records->readSector($s->map->sectors, $i);
            $secrets[] = $s->combat->visitedSecrets[$i];
        }
        for ($i = 0; $i < $s->map->sidedefCount; $i++) { $sides[] = $this->records->readSidedef($s->map->sidedefs, $i); }
        for ($i = 0; $i < $s->map->linedefCount; $i++) { $lines[] = [$s->map->linedefs[$i]->special, $s->map->linedefs[$i]->mapped]; }
        for ($i = 0; $i < $s->specials->moverCount; $i++) { $movers[] = $this->records->readMover($s->specials->movers, $i); }
        for ($i = 0; $i < $s->specials->lightCount; $i++) { $lights[] = $this->records->readLightEffect($s->specials->lights, $i); }
        for ($i = 0; $i < 16; $i++) { $buttons[] = $this->records->readButton($s->specials->buttons, $i); }
        for ($i = 0; $i < $s->things->count * 4; $i++) { $frames[] = $s->things->frameDefinition[$i]; }
        $c = $s->combat;
        $data = ["version" => 1, "wad" => $this->fingerprint($s), "map" => $s->mapName, "skill" => $s->config->skill,
            "time" => $s->levelTime, "elephant" => $s->player->elephantMode,
            "player" => $this->records->readPlayer($s->player), "objects" => $objects,
            "sectors" => $sectors, "sides" => $sides, "lines" => $lines, "movers" => $movers, "lights" => $lights,
            "buttons" => $buttons, "secrets" => $secrets, "frames" => $frames,
            "combat" => [$c->seed, $c->kills, $c->totalKills, $c->items, $c->totalItems, $c->secrets, $c->totalSecrets, (int)$c->bossTriggered, $s->specials->seed, $c->clock]];
        // Encode bounded sections separately, keeping large maps out of the
        // runtime encoder's recursively shared scratch space.
        array $parts = [];
        foreach ($data as $key => $value) {
            $json = json_encode($value);
            if (!is_string($json)) { return ""; }
            $parts[] = '"' . (string)$key . '":' . (string)$json;
        }
        return "{" . implode(",", $parts) . "}";
    }

    public function write(Session $s, string $path): bool {
        if ($s->phase !== 0 || $s->player->health <= 0) { return false; }
        string $json = $this->encode($s);
        if ($json === "") { return false; }
        string $temporary = $path . ".tmp";
        if (file_put_contents($temporary, $json) === false) { return false; }
        if (!rename($temporary, $path)) { unlink($temporary); return false; }
        return true;
    }

    public function read(Session $s, string $path): bool {
        if (!is_file($path) || filesize($path) > 4194304) { return false; }
        $json = file_get_contents($path);
        return is_string($json) && $this->decode($s, (string)$json);
    }

    public function integers(array $values, int $count): bool {
        if (count($values) !== $count || !array_is_list($values)) { return false; }
        foreach ($values as $value) {
            if (!is_int($value) || $value < -2147483648 || $value > 4294967295) { return false; }
        }
        return true;
    }

    public function rows(array $values, int $width, int $count, int $limit): bool {
        if (!array_is_list($values) || ($count >= 0 && count($values) !== $count) || count($values) > $limit) { return false; }
        foreach ($values as $row) { if (!is_array($row) || !$this->integers((array)$row, $width)) { return false; } }
        return true;
    }

    public function decode(Session $s, string $json): bool {
        if (strlen($json) > 4194304) { return false; }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) { return false; }
        array $d = (array)$decoded;
        if (array_key_exists("elephant", $d) && !is_bool($d["elephant"])) { return false; }
        if (($d["version"] ?? 0) !== 1 || ($d["wad"] ?? "") !== $this->fingerprint($s) || !is_string($d["map"] ?? null)) { return false; }
        string $mapName = (string)$d["map"];
        if (strlen($mapName) !== 4 || $mapName[0] !== "E" || $mapName[2] !== "M" || $mapName[1] < "1" || $mapName[1] > "4" || $mapName[3] < "1" || $mapName[3] > "9") { return false; }
        if (!is_int($d["skill"] ?? null) || (int)$d["skill"] < 1 || (int)$d["skill"] > 5 || !is_int($d["time"] ?? null) || (int)$d["time"] < 0) { return false; }
        $names = ["player", "objects", "sectors", "sides", "lines", "movers", "lights", "buttons", "secrets", "frames", "combat"];
        foreach ($names as $name) { if (!is_array($d[(string)$name] ?? null)) { return false; } }
        $map = $s->mapLoader->load($s->wad, $s->textures, $s->flats, $mapName);
        if (!$map->isValid()) { $map->release(); return false; }
        int $capacity = $map->thingCount * 2 + 64;
        array $objects = (array)$d["objects"];
        bool $valid = $this->integers((array)$d["player"], 47) && $this->integers((array)$d["combat"], 10)
            && $this->rows($objects, 35, -1, $capacity)
            && $this->rows((array)$d["sectors"], 8, $map->sectorCount, $map->sectorCount)
            && $this->rows((array)$d["sides"], 6, $map->sidedefCount, $map->sidedefCount)
            && $this->rows((array)$d["lines"], 2, $map->linedefCount, $map->linedefCount)
            && $this->rows((array)$d["movers"], 16, -1, 256) && $this->rows((array)$d["lights"], 8, -1, 256)
            && $this->rows((array)$d["buttons"], 5, 16, 16)
            && $this->integers((array)$d["secrets"], $map->sectorCount) && $this->integers((array)$d["frames"], count($objects) * 4);
        if ($valid) { $valid = $this->references($s, (array)$d, $map->sectorCount, $map->sidedefCount); }
        $map->release();
        if (!$valid) { return false; }
        $s->config->skill = (int)$d["skill"];
        if (!$s->loadMap($mapName)) { return false; }
        $this->restore($s, (array)$d);
        return true;
    }

    public function references(Session $s, array $d, int $sectors, int $sides): bool {
        array $objects = (array)$d["objects"];
        foreach ($objects as $raw) {
            array $r = (array)$raw;
            if ((int)$r[0] < 0 || (int)$r[0] > 1 || (int)$r[6] < 0 || (int)$r[6] >= $s->things->types->count
                || (int)$r[7] < 0 || (int)$r[7] >= $sectors || (int)$r[8] < 0 || (int)$r[8] >= $s->sprites->count
                || (int)$r[9] < 1 || (int)$r[9] > 4 || (int)$r[13] < 0 || (int)$r[13] > 256
                || (int)$r[14] < 0 || (int)$r[14] > 256 || (int)$r[17] < 0 || (int)$r[17] > 8
                || (int)$r[20] < -1 || (int)$r[20] >= count($objects)) { return false; }
        }
        foreach ((array)$d["frames"] as $frame) { if ((int)$frame < 0 || (int)$frame >= $s->sprites->count) { return false; } }
        foreach ((array)$d["sectors"] as $raw) {
            array $r = (array)$raw;
            if ((int)$r[2] < -1 || (int)$r[2] >= $s->flats->count || (int)$r[3] < -1 || (int)$r[3] >= $s->flats->count || (int)$r[7] < 0 || (int)$r[7] > count((array)$d["movers"])) { return false; }
        }
        foreach ((array)$d["sides"] as $raw) {
            array $r = (array)$raw;
            for ($i = 2; $i < 5; $i++) { if ((int)$r[$i] < -1 || (int)$r[$i] >= $s->textures->count) { return false; } }
            if ((int)$r[5] < 0 || (int)$r[5] >= $sectors) { return false; }
        }
        foreach ((array)$d["movers"] as $raw) {
            array $r = (array)$raw;
            if ((int)$r[0] < 0 || (int)$r[0] > 2 || (int)$r[1] < 0 || (int)$r[1] >= $sectors
                || (int)$r[2] < 1 || (int)$r[2] > 4 || abs((int)$r[4]) > 1 || (int)$r[5] < 1 || (int)$r[5] > 64
                || (int)$r[10] < -1 || (int)$r[10] >= $s->flats->count || (int)$r[11] < 1 || (int)$r[11] > 64
                || (int)$r[12] < 0 || (int)$r[12] > (int)$r[11] || (int)$r[13] < 0 || (int)$r[13] > 1 || abs((int)$r[15]) > 1) { return false; }
        }
        foreach ((array)$d["lights"] as $raw) { array $r = (array)$raw; if ((int)$r[0] < 0 || (int)$r[0] >= $sectors) { return false; } }
        foreach ((array)$d["buttons"] as $raw) {
            array $r = (array)$raw;
            if ((int)$r[0] !== 0 && ((int)$r[1] < 0 || (int)$r[1] >= $sides || (int)$r[2] < 0 || (int)$r[2] > 2 || (int)$r[3] < 0 || (int)$r[3] >= $s->textures->count)) { return false; }
        }
        array $p = (array)$d["player"];
        if ((int)$p[21] < 1 || (int)$p[21] > 8) { return false; }
        if ((int)$p[6] < 0 || (int)$p[6] > 8191 || (int)$p[14] < 0 || (int)$p[14] > 7 || (int)$p[43] < 0 || (int)$p[43] > 8
            || (int)$p[18] < 0 || (int)$p[18] > 400 || (int)$p[19] < 0 || (int)$p[19] > 100 || (int)$p[27] < 0 || (int)$p[27] > 100
            || (int)$p[28] < 0 || (int)$p[28] > 600 || ((int)$p[20] & (1 << (int)$p[21])) === 0) { return false; }
        if ((int)$p[15] <= 0 || (int)$p[15] > 200 || (int)$p[21] < 1 || (int)$p[21] > 8 || ((int)$p[20] & ~$s->player->availableWeapons) !== 0) { return false; }
        return true;
    }

    public function restore(Session $s, array $d): void {
        $this->records->writePlayer($s->player, (array)$d["player"]);
        $s->player->elephantMode = (bool)($d["elephant"] ?? false);
        $s->player->availableWeapons = $s->renderer->weapons->available;
        $s->levelTime = (int)$d["time"];
        array $rows = (array)$d["objects"];
        for ($i = 0; $i < count($rows); $i++) { $this->records->writeMobj($s->things->mobjs, $i, (array)$rows[$i]); }
        $s->things->count = count($rows);
        $rows = (array)$d["sectors"];
        for ($i = 0; $i < count($rows); $i++) { $this->records->writeSector($s->map->sectors, $i, (array)$rows[$i]); }
        $rows = (array)$d["sides"];
        for ($i = 0; $i < count($rows); $i++) { $this->records->writeSidedef($s->map->sidedefs, $i, (array)$rows[$i]); }
        $rows = (array)$d["lines"];
        for ($i = 0; $i < count($rows); $i++) {
            array $r = (array)$rows[$i];
            $s->map->linedefs[$i]->special = (int)$r[0]; $s->map->linedefs[$i]->mapped = (int)$r[1];
        }
        $rows = (array)$d["movers"];
        for ($i = 0; $i < count($rows); $i++) { $this->records->writeMover($s->specials->movers, $i, (array)$rows[$i]); }
        $s->specials->moverCount = count($rows);
        $rows = (array)$d["lights"];
        for ($i = 0; $i < count($rows); $i++) { $this->records->writeLightEffect($s->specials->lights, $i, (array)$rows[$i]); }
        $s->specials->lightCount = count($rows);
        $rows = (array)$d["buttons"];
        for ($i = 0; $i < 16; $i++) { $this->records->writeButton($s->specials->buttons, $i, (array)$rows[$i]); }
        $rows = (array)$d["secrets"];
        for ($i = 0; $i < count($rows); $i++) { $s->combat->visitedSecrets[$i] = (int)$rows[$i]; }
        $rows = (array)$d["frames"];
        for ($i = 0; $i < count($rows); $i++) { $s->things->frameDefinition[$i] = (int)$rows[$i]; }
        $rows = (array)$d["combat"];
        $s->combat->seed = (int)$rows[0]; $s->combat->kills = (int)$rows[1]; $s->combat->totalKills = (int)$rows[2];
        $s->combat->items = (int)$rows[3]; $s->combat->totalItems = (int)$rows[4]; $s->combat->secrets = (int)$rows[5];
        $s->combat->totalSecrets = (int)$rows[6]; $s->combat->bossTriggered = (bool)$rows[7]; $s->specials->seed = (int)$rows[8]; $s->combat->clock = (int)$rows[9];
        $s->renderer->showAutomap = false;
        $s->renderer->hud->say("GAME LOADED");
    }
}
