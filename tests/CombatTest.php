<?php

namespace Showcases\Doom\Tests;

require_once __DIR__ . '/../src/engine.php';
require_once __DIR__ . '/Fixture/TestWad.php';
require_once __DIR__ . '/Fixture/SessionCase.php';
require_once __DIR__ . '/Fixture/CombatCase.php';

use Showcases\Doom\Tests\Fixture\CombatCase;
use Showcases\Doom\SDL\Input;

final class CombatTest extends CombatCase {
    public function testPistolConsumesOneBulletAndRespectsItsCooldown(): void {
        $s = $this->boot();
        $this->isolate($s, 3);
        $this->advance($s, 1, 224);
        $this->assertSameInt(49, $s->player->bullets);
        $this->assertTrue($s->things->mobjs[3]->health < 20);
        $this->advance($s, 10, 224);
        $this->assertSameInt(49, $s->player->bullets);
        $this->assertSameInt(1, $s->sounds->played);
    }

    public function testShotgunFiresSevenPelletsForOneShell(): void {
        $s = $this->boot();
        $this->isolate($s, 3);
        $s->player->pickup(2001);
        $s->things->mobjs[3]->x = 128;
        $this->advance($s, 1, 224);
        $this->assertSameInt(7, $s->player->shells);
        $this->assertSameInt(1, $s->combat->kills);
        $this->assertSameInt(0, $s->things->mobjs[3]->solid);
    }

    public function testPistolCanFireAgainExactlyFourteenTicsLater(): void {
        $s = $this->boot();
        $this->isolate($s, 3);
        $this->advance($s, 14, 224);
        $this->assertSameInt(49, $s->player->bullets);
        $this->advance($s, 1, 224);
        $this->assertSameInt(48, $s->player->bullets);
    }

    public function testEmptyPistolFallsBackToFistsWithoutNegativeAmmo(): void {
        $s = $this->boot();
        $this->isolate($s, 3);
        $s->player->bullets = 0;
        $s->things->mobjs[3]->x = 112;
        $this->advance($s, 1, 224);
        $this->assertSameInt(1, $s->player->weapon);
        $this->assertSameInt(0, $s->player->bullets);
        $this->assertTrue($s->things->mobjs[3]->health < 20);
    }

    public function testUnownedWeaponCannotBeSelected(): void {
        $s = $this->boot();
        $this->advance($s, 1, 32);
        $this->assertSameInt(2, $s->player->weapon);
        $s->player->pickup(2001);
        $this->advance($s, 1, 31);
        $this->assertSameInt(2, $s->player->weapon);
    }

    public function testClosedDoorBlocksBulletsButAnOpenGrateDoesNot(): void {
        $s = $this->boot();
        $this->isolate($s, 4);
        $s->things->mobjs[4]->y = 128;
        $s->map->sectors[1]->ceiling_height = 0;
        $this->assertSameInt(-1, $s->combat->playerShot(0, 5, 2048));
        $this->assertSameInt(30, $s->things->mobjs[4]->health);
        $s->map->sectors[1]->ceiling_height = 128;
        $this->assertSameInt(4, $s->combat->playerShot(0, 5, 2048));
        $this->assertSameInt(25, $s->things->mobjs[4]->health);
    }

    public function testNearestEnemyAbsorbsTheBullet(): void {
        $s = $this->boot();
        $s->things->mobjs[4]->x = 320;
        $s->things->mobjs[4]->y = 128;
        $this->assertSameInt(3, $s->combat->playerShot(0, 10, 2048));
        $this->assertSameInt(30, $s->things->mobjs[4]->health);
    }

    public function testKillingAnEnemyDropsAmmoAndCountsOnlyOnce(): void {
        $s = $this->boot();
        $this->isolate($s, 3);
        $s->combat->hurt(3, 100, -1);
        $s->combat->hurt(3, 100, -1);
        $this->assertSameInt(1, $s->combat->kills);
        $this->assertSameInt(2007, $s->things->mobjs[0]->type);
        $this->assertSameInt(192, $s->things->mobjs[0]->x);
        for ($t = 0; $t < 25; $t++) {
            $s->combat->tick();
        }
        $this->assertSameInt(5, $s->things->mobjs[3]->state);
    }

    public function testBarrelExplosionChainsAndHurtsThePlayer(): void {
        $s = $this->boot();
        $this->isolate($s, 6);
        $s->things->mobjs[7]->active = 1;
        $s->things->mobjs[7]->x = 288;
        $s->player->spawn(144, 192, 0, 0);
        $s->combat->hurt(6, 20, -1);
        for ($t = 0; $t < 11; $t++) {
            $s->combat->tick();
        }
        $this->assertSameInt(7, $s->things->mobjs[7]->state);
        $this->assertTrue($s->player->health < 100);
        $this->assertSameInt(0, $s->combat->kills);
    }

    public function testBlastCannotCrossAClosedDoor(): void {
        $s = $this->boot();
        $this->isolate($s, 6);
        $s->things->mobjs[7]->active = 1;
        $s->things->mobjs[7]->x = 288;
        $s->map->sectors[1]->ceiling_height = 0;
        $s->combat->blast(6);
        $this->assertSameInt(20, $s->things->mobjs[7]->health);
    }

    public function testHudRandomnessDoesNotChangeCombatRandomness(): void {
        $s = $this->boot();
        int $first = $s->combat->random();
        for ($t = 0; $t < 100; $t++) {
            $s->specials->random();
        }
        $s->combat->seed = 1;
        $this->assertSameInt($first, $s->combat->random());
    }

    public function testEnemyDropsGiveReducedAmmoAndAUsableShotgun(): void {
        $s = $this->boot();
        $this->isolate($s, 4);
        $s->combat->hurt(4, 100, -1);
        $s->player->spawn($s->things->mobjs[4]->x, $s->things->mobjs[4]->y, 0, 0);
        $this->assertSameInt(1, $s->things->touch($s->player));
        $this->assertSameInt(4, $s->player->shells);
        $this->assertSameInt(3, $s->player->weapon);
        $this->assertTrue($s->player->pickup(2007, true));
        $this->assertSameInt(55, $s->player->bullets);
    }
}
