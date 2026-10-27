<?php

namespace Showcases\Doom\Tests;

require_once __DIR__ . '/../src/engine.php';
require_once __DIR__ . '/Fixture/TestWad.php';
require_once __DIR__ . '/Fixture/SessionCase.php';
require_once __DIR__ . '/Fixture/CombatCase.php';

use Showcases\Doom\Tests\Fixture\CombatCase;
use Showcases\Doom\Data\Linedef;

final class SaveMenuTest extends CombatCase {
    protected bool $full = true;

    public function testSnapshotRestoresInventoryCombatMovingFloorsAndRandomness(): void {
        $s = $this->boot();
        $s->cheats->feed($s, "idkfaiddqd");
        $s->combat->hurt(3, 7, -1);
        $s->specials->startFloor(1, 64, 1, -1);
        $this->advance($s, 5);
        string $snapshot = $s->saves->encode($s);
        int $health = $s->things->mobjs[3]->health;
        int $floor = $s->map->sectors[1]->floor_height;
        int $seed = $s->combat->seed;
        $s->player->health = 1;
        $s->player->keys = 0;
        $s->loadMap("E1M2");
        $this->assertTrue($s->saves->decode($s, $snapshot));
        $this->assertSameString("E1M1", $s->mapName);
        $this->assertSameInt(100, $s->player->health);
        $this->assertTrue($s->player->godMode);
        $this->assertSameInt(7, $s->player->keys);
        $this->assertSameInt($health, $s->things->mobjs[3]->health);
        $this->assertSameInt($floor, $s->map->sectors[1]->floor_height);
        $this->assertSameInt($seed, $s->combat->seed);
        $this->advance($s, 3);
        $this->assertTrue($s->map->sectors[1]->floor_height > $floor);
    }

    public function testMalformedAndForeignSavesLeaveTheCurrentGameUntouched(): void {
        $s = $this->boot();
        string $snapshot = $s->saves->encode($s);
        $this->assertFalse($s->saves->decode($s, "{broken"));
        $this->assertFalse($s->saves->decode($s, str_replace('"version":1', '"version":2', $snapshot)));
        $this->assertFalse($s->saves->decode($s, str_replace($s->saves->wadHash, "wrong-wad", $snapshot)));
        $this->assertSameString("E1M1", $s->mapName);
        $this->assertSameInt(100, $s->player->health);
    }

    public function testSaveFileRoundTripAndDeadGameRejection(): void {
        $s = $this->boot();
        string $path = $this->wadPath . ".save";
        $this->assertTrue($s->saves->write($s, $path));
        $s->player->health = 0;
        $this->assertFalse($s->saves->write($s, $path));
        bool $loaded = $s->saves->read($s, $path);
        unlink($path);
        $this->assertTrue($loaded);
        $this->assertSameInt(100, $s->player->health);
    }

    public function testInvalidWeaponAndActorReferencesAreRejectedBeforeRestore(): void {
        $s = $this->boot();
        array $data = (array)json_decode($s->saves->encode($s), true);
        array $player = (array)$data["player"];
        $player[21] = -1;
        $data["player"] = $player;
        $this->assertFalse($s->saves->decode($s, (string)json_encode($data)));
        $player[21] = 999999;
        $data["player"] = $player;
        $this->assertFalse($s->saves->decode($s, (string)json_encode($data)));
        $player[21] = 2;
        $data["player"] = $player;
        array $objects = (array)$data["objects"];
        array $actor = (array)$objects[0];
        $actor[7] = 999999;
        $objects[0] = $actor;
        $data["objects"] = $objects;
        $this->assertFalse($s->saves->decode($s, (string)json_encode($data)));
        $this->assertSameInt(2, $s->player->weapon);
        $this->assertSameInt(100, $s->player->health);
    }

    public function testLargeMapSnapshotProducesACompleteJsonDocument(): void {
        $s = $this->boot();
        $previous = $s->map->linedefs;
        $lines = buffer_new<Linedef>(1500);
        $s->map->linedefs = $lines;
        $s->map->linedefCount = 1500;
        $lines[1499]->special = 97;
        array $snapshot = (array)json_decode($s->saves->encode($s), true);
        $s->map->linedefs = $previous;
        $s->map->linedefCount = 7;
        buffer_free($lines);
        $this->assertSameInt(1500, count((array)$snapshot["lines"]));
        array $records = (array)$snapshot["lines"];
        array $last = (array)$records[1499];
        $this->assertSameInt(97, (int)$last[0]);
    }

    public function testPauseFreezesWorldTimeUntilTheMenuCloses(): void {
        $s = $this->boot();
        $this->advance($s, 1, 41);
        int $time = $s->levelTime;
        $this->advance($s, 40);
        $this->assertSameInt(1, $s->menu->page);
        $this->assertSameInt($time, $s->levelTime);
        $this->advance($s, 1, 41);
        $this->advance($s, 1);
        $this->assertSameInt(0, $s->menu->page);
        $this->assertTrue($s->levelTime > $time);
    }

    public function testNewGameSelectsDifficultyAndUnavailableEpisodesStayInMenu(): void {
        $s = $this->boot();
        $s->menu->page = 2;
        $s->menu->selected = 1;
        $s->menu->confirm($s);
        $this->assertSameInt(2, $s->menu->page);
        $s->menu->selected = 0;
        $s->menu->confirm($s);
        $s->menu->selected = 4;
        $s->menu->confirm($s);
        $this->assertSameInt(5, $s->config->skill);
        $this->assertSameInt(5, $s->combat->skill);
        $this->assertSameInt(0, $s->menu->page);
    }
}
