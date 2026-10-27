<?php

namespace Showcases\Doom\Tests;

require_once __DIR__ . '/../src/engine.php';
require_once __DIR__ . '/Fixture/TestWad.php';
require_once __DIR__ . '/Fixture/SessionCase.php';
require_once __DIR__ . '/Fixture/CombatCase.php';

use Showcases\Doom\Tests\Fixture\CombatCase;

final class MonsterTest extends CombatCase {
    public function testAnEnemySeesChasesAndAttacksThePlayer(): void {
        $s = $this->boot();
        $this->isolate($s, 3);
        int $startX = $s->things->mobjs[3]->x;
        $this->advance($s, 100);
        $this->assertTrue($s->things->mobjs[3]->x < $startX);
        $this->assertTrue($s->player->health < 100);
    }

    public function testSoundWakesAnEnemyFacingAwayButNotThroughAClosedDoor(): void {
        $s = $this->boot();
        $this->isolate($s, 4);
        $s->things->mobjs[4]->angle = 0;
        $s->map->sectors[1]->ceiling_height = 0;
        $s->combat->noise();
        $this->assertSameInt(0, $s->things->mobjs[4]->state);
        $s->map->sectors[1]->ceiling_height = 128;
        $s->combat->noise();
        $this->assertSameInt(1, $s->things->mobjs[4]->state);
    }

    public function testAmbushEnemyIgnoresSoundUntilItSeesThePlayer(): void {
        $s = $this->boot();
        $this->isolate($s, 3);
        $s->things->mobjs[3]->angle = 0;
        $s->things->mobjs[3]->spawnFlags = 15;
        $s->combat->noise();
        $this->advance($s, 1);
        $this->assertSameInt(0, $s->things->mobjs[3]->state);
        $s->things->mobjs[3]->angle = 4096;
        $this->advance($s, 1);
        $this->assertSameInt(1, $s->things->mobjs[3]->state);
    }

    public function testAClosedDoorBlocksSightAndMonsterBullets(): void {
        $s = $this->boot();
        $this->isolate($s, 4);
        $s->things->mobjs[4]->y = 128;
        $s->map->sectors[1]->ceiling_height = 0;
        $this->advance($s, 20);
        $this->assertSameInt(0, $s->things->mobjs[4]->state);
        $s->combat->monsters->bullet($s->combat, 4, 4096, 20);
        $this->assertSameInt(100, $s->player->health);
    }

    public function testAnEnemyCanMoveWithoutCollidingWithItself(): void {
        $s = $this->boot();
        $this->isolate($s, 3);
        $this->assertTrue($s->collision->checkActorPosition(184, 128, 0, 3, 20, 56));
        $this->assertFalse($s->collision->checkPosition(184, 128, 0));
        $s->map->linedefs[6]->flags = 22;
        $this->assertFalse($s->collision->checkActorPosition(248, 128, 0, 3, 20, 56));
    }

    public function testMonsterDamageStartsInfighting(): void {
        $s = $this->boot();
        $s->combat->hurt(3, 5, 4);
        $this->assertSameInt(4, $s->things->mobjs[3]->target);
        $this->assertSameInt(3, $s->things->mobjs[3]->state);
    }

    public function testImpFireballTravelsAndDamagesThePlayer(): void {
        $s = $this->boot();
        $this->isolate($s, 5);
        $s->things->mobjs[5]->x = 192;
        $s->things->mobjs[5]->y = 128;
        $s->things->mobjs[5]->sector = 0;
        $s->combat->projectiles->spawn($s->combat, 5, 64, 128, 28);
        $this->assertSameInt(6, $s->things->mobjs[0]->state);
        for ($t = 0; $t < 15; $t++) {
            $s->combat->projectiles->tick($s->combat, 0);
        }
        $this->assertTrue($s->player->health < 100);
        $this->assertSameInt(7, $s->things->mobjs[0]->state);
    }

    public function testFireballHitsTheDoorBeforeThePlayer(): void {
        $s = $this->boot();
        $this->isolate($s, 5);
        $s->things->mobjs[5]->x = 320;
        $s->things->mobjs[5]->y = 128;
        $s->map->sectors[1]->ceiling_height = 0;
        $s->combat->projectiles->spawn($s->combat, 5, 64, 128, 28);
        for ($t = 0; $t < 20; $t++) {
            $s->combat->projectiles->tick($s->combat, 0);
        }
        $this->assertSameInt(100, $s->player->health);
        $this->assertSameInt(0, $s->things->mobjs[0]->active);
    }

    public function testMovingFloorsDoNotSnapFlyingFireballsToTheGround(): void {
        $s = $this->boot();
        $this->isolate($s, 5);
        $s->combat->projectiles->spawn($s->combat, 5, 64, 128, 28);
        int $height = $s->things->mobjs[0]->z;
        $s->map->sectors[1]->floor_height = 16;
        $s->specials->settleThings(1);
        $this->assertSameInt($height, $s->things->mobjs[0]->z);
        $this->assertSameInt(16, $s->things->mobjs[5]->z);
    }
}
