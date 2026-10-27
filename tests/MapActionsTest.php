<?php

namespace Showcases\Doom\Tests;

require_once __DIR__ . '/../src/engine.php';
require_once __DIR__ . '/Fixture/TestWad.php';
require_once __DIR__ . '/Fixture/SessionCase.php';
require_once __DIR__ . '/Fixture/CombatCase.php';

use Showcases\Doom\Tests\Fixture\CombatCase;
use Showcases\Doom\App\Session;
use Showcases\Doom\Data\Sector;
use Showcases\Doom\Play\Specials;

final class MapActionsTest extends CombatCase {
    protected bool $full = true;

    public function testGunDoorCannotBeUsedButOpensWhenShot(): void {
        $s = $this->boot();
        $this->isolate($s, -1);
        $s->map->linedefs[6]->special = 46;
        $s->player->spawn(320, 128, 4096, 0);
        $s->specials->playerX = 320; $s->specials->playerY = 128;
        $s->map->sectors[0]->ceiling_height = 0;
        $this->assertFalse($s->specials->useLine(6));
        $s->combat->playerShot(4096, 10, 2048);
        $this->assertTrue($s->map->sectors[0]->mover > 0);
        $this->advance($s, 40);
        $this->assertTrue($s->map->sectors[0]->ceiling_height > 56);
    }

    public function testSlowStairsAdvanceOneUnitEveryFourTics(): void {
        $s = $this->boot();
        $s->specials->buildStairs(1);
        for ($t = 0; $t < 3; $t++) { $s->specials->tickMovers(); }
        $this->assertSameInt(0, $s->map->sectors[1]->floor_height);
        $s->specials->tickMovers();
        $this->assertSameInt(1, $s->map->sectors[1]->floor_height);
    }

    public function testCrusherDamagesBodiesAndCanStopAndRestart(): void {
        $s = $this->boot();
        $this->isolate($s, -1);
        $s->player->spawn(384, 128, 0, 0);
        $s->map->sectors[1]->ceiling_height = 56;
        $this->assertTrue($s->specials->advanced->start($s->specials, 6, 1, 44, 1));
        $this->advance($s, 8);
        $this->assertTrue($s->player->health < 100);
        int $height = $s->map->sectors[1]->ceiling_height;
        $s->specials->advanced->start($s->specials, 6, 1, 45, 1);
        $this->advance($s, 4);
        $this->assertSameInt($height, $s->map->sectors[1]->ceiling_height);
        $s->specials->advanced->start($s->specials, 6, 1, 44, 1);
        $this->advance($s, 4);
        $this->assertTrue($s->map->sectors[1]->ceiling_height < $height);
    }

    public function testEndSectorCompletesE1m8BeforeDeathRestart(): void {
        $s = $this->boot();
        $this->isolate($s, -1);
        $s->mapName = "E1M8";
        $s->map->sectors[0]->special = 11;
        $s->player->health = 5;
        $this->advance($s, 1);
        $this->assertSameInt(3, $s->phase);
        $this->assertSameString("E1M8", $s->mapName);
    }

    public function testNeighborRejectsAnUnrelatedSector(): void {
        $s = $this->boot();
        $this->assertSameInt(-1, $s->specials->neighbor(0, 1));
    }

    public function threeSectors(Session $s): void {
        $previous = $s->map->sectors;
        $sectors = buffer_new<Sector>(3);
        for ($i = 0; $i < 3; $i++) { $s->saves->records->writeSector($sectors, $i, $s->saves->records->readSector($previous, $i % 2)); }
        $s->map->sectors = $sectors;
        $s->map->sectorCount = 3;
        buffer_free($previous);
        $s->specials->release();
        $s->specials = new Specials($s->map, $s->collision, $s->things, $s->textures, $s->wad, $s->trig);
        $s->combat->specials = $s->specials;
    }

    public function testDonutMovesPillarAndRingToTheOutsideHeightAndFlat(): void {
        $s = $this->boot();
        $this->isolate($s, -1);
        $s->map->linedefs[0]->back_sector = 2;
        $s->map->linedefs[3]->back_sector = 0;
        $this->threeSectors($s);
        $s->map->sectors[1]->floor_height = 64;
        $s->map->sectors[2]->floor_height = 32;
        $s->map->sectors[2]->floor_pic = 1;
        $s->map->sectors[2]->special = 7;
        $this->assertTrue($s->specials->advanced->donut($s->specials, 1));
        for ($t = 0; $t < 64; $t++) { $s->specials->tickMovers(); }
        $this->assertSameInt(32, $s->map->sectors[1]->floor_height);
        $this->assertSameInt(32, $s->map->sectors[0]->floor_height);
        $this->assertSameInt(1, $s->map->sectors[0]->floor_pic);
        $this->assertSameInt(7, $s->map->sectors[0]->special);
    }

    public function testSoundCrossesOneBlockingLineButStopsAtTheSecond(): void {
        $s = $this->boot();
        $this->isolate($s, 4);
        $this->threeSectors($s);
        $s->combat->release();
        $s->combat = new \Showcases\Doom\Play\Combat($s->things, $s->player, $s->collision, $s->specials, $s->trig, $s->sounds, $s->sprites, $s->wad);
        $s->things->mobjs[4]->sector = 2;
        $s->map->linedefs[0]->front_sector = 0; $s->map->linedefs[0]->back_sector = 1; $s->map->linedefs[0]->flags = 64;
        $s->map->linedefs[6]->front_sector = 1; $s->map->linedefs[6]->back_sector = 2; $s->map->linedefs[6]->flags = 64;
        $s->combat->noise();
        $this->assertSameInt(1, intdiv($s->combat->heard[1], 2));
        $this->assertSameInt(0, $s->things->mobjs[4]->state);
        $s->map->linedefs[6]->flags = 0;
        $s->combat->noise();
        $this->assertSameInt(1, $s->things->mobjs[4]->state);
    }

    public function testMonsterOnlyTeleporterMovesTheActorAndKeepsItsRepeatTrigger(): void {
        $s = $this->boot();
        $this->isolate($s, 3);
        $s->map->things[0]->type = 14;
        $s->map->things[0]->x = 384; $s->map->things[0]->y = 128; $s->map->things[0]->angle = 90;
        $s->map->sectors[1]->tag = 42;
        $s->map->linedefs[6]->special = 126; $s->map->linedefs[6]->tag = 42;
        $s->specials->crossLine(6, -1);
        $this->assertFalse($s->specials->teleportPending);
        $s->specials->monsterCross(6, -1, 3);
        $this->assertSameInt(384, $s->things->mobjs[3]->x);
        $this->assertSameInt(1, $s->things->mobjs[3]->sector);
        $this->assertSameInt(2048, $s->things->mobjs[3]->angle);
        $this->assertSameInt(126, $s->map->linedefs[6]->special);
    }

    public function testKeyedDoorRejectsMissingKeyAndBlazingDoorUsesItsSpeed(): void {
        $s = $this->boot();
        $s->specials->playerX = 320; $s->specials->playerY = 128;
        $s->map->linedefs[6]->front_sector = 0; $s->map->linedefs[6]->back_sector = 1;
        $s->map->linedefs[6]->special = 26;
        $s->map->sectors[1]->ceiling_height = 0;
        $this->assertFalse($s->specials->useLine(6));
        $s->specials->playerKeys = 1;
        $this->assertTrue($s->specials->useLine(6));
        int $slot = $s->map->sectors[1]->mover - 1;
        $s->specials->finishMover($slot);
        $s->map->linedefs[6]->special = 117;
        $this->assertTrue($s->specials->useLine(6));
        $this->assertSameInt(8, $s->specials->movers[$s->map->sectors[1]->mover - 1]->speed);
    }

    public function testRisingLiftReversesWhenAnActorCannotFitUnderTheCeiling(): void {
        $s = $this->boot();
        $this->isolate($s, -1);
        $s->player->spawn(384, 128, 0, 0);
        $s->specials->playerX = 384; $s->specials->playerY = 128;
        $s->map->sectors[1]->ceiling_height = 56;
        $s->specials->advanced->start($s->specials, 6, 1, 10, 4);
        int $slot = $s->map->sectors[1]->mover - 1;
        $s->specials->movers[$slot]->top = 64;
        $s->specials->movers[$slot]->direction = 1;
        $s->specials->tickMovers();
        $this->assertSameInt(0, $s->map->sectors[1]->floor_height);
        $this->assertSameInt(-1, $s->specials->movers[$slot]->direction);
    }
}
