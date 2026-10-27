<?php

namespace Showcases\Doom\Tests;

require_once __DIR__ . '/../src/engine.php';
require_once __DIR__ . '/Fixture/TestWad.php';
require_once __DIR__ . '/Fixture/SessionCase.php';
require_once __DIR__ . '/Fixture/CombatCase.php';

use Showcases\Doom\Tests\Fixture\CombatCase;
use Showcases\Doom\App\Session;

final class OriginalMonstersTest extends CombatCase {
    protected bool $full = true;

    public function monster(Session $s, int $i, int $type): void {
        $s->things->mobjs[$i]->type = $type;
        $s->things->mobjs[$i]->kind = $s->things->types->find($type);
        $s->things->mobjs[$i]->health = $s->combat->rules->health($type);
        $s->things->mobjs[$i]->height = $s->things->types->height[$s->things->mobjs[$i]->kind];
        $s->things->mobjs[$i]->radius = $s->things->types->radius[$s->things->mobjs[$i]->kind];
        $s->things->mobjs[$i]->state = 1;
    }

    public function testOriginalMonsterHealthAndDemonMelee(): void {
        $s = $this->boot();
        $this->isolate($s, 3);
        $this->monster($s, 3, 3002);
        $this->assertSameInt(150, $s->things->mobjs[3]->health);
        $s->things->mobjs[3]->x = 112;
        $s->combat->monsters->attack($s->combat, 3, 64, 128, 28, 48);
        $this->assertTrue($s->player->health < 100);
        $this->assertSameInt(1000, $s->combat->rules->health(3003));
        $this->assertSameInt(4000, $s->combat->rules->health(16));
        $this->assertSameInt(3000, $s->combat->rules->health(7));
    }

    public function testFlyingCacodemonKeepsHeightWhileChasing(): void {
        $s = $this->boot();
        $this->isolate($s, 3);
        $this->monster($s, 3, 3005);
        $s->things->mobjs[3]->z = 48;
        $s->things->mobjs[3]->cooldown = 100;
        $s->combat->monsters->move($s->combat, 3, -100, 0);
        $this->assertSameInt(48, $s->things->mobjs[3]->z);
        $s->combat->monsters->attack($s->combat, 3, 64, 128, 28, 128);
        $this->assertSameInt(6006, $s->things->mobjs[0]->type);
    }

    public function testLostSoulChargeHitsAndReturnsToChase(): void {
        $s = $this->boot();
        $this->isolate($s, 3);
        $this->monster($s, 3, 3006);
        $s->combat->monsters->attack($s->combat, 3, 64, 128, 28, 128);
        $this->assertSameInt(8, $s->things->mobjs[3]->state);
        for ($t = 0; $t < 8; $t++) { $s->combat->monsters->tick($s->combat, 3); }
        $this->assertTrue($s->player->health < 100);
        $this->assertTrue($s->things->mobjs[3]->state !== 8);
    }

    public function testBossesUseTheirProjectilesAndIgnoreSplashDamage(): void {
        $s = $this->boot();
        $this->isolate($s, 3);
        $this->monster($s, 3, 3003);
        $s->combat->monsters->attack($s->combat, 3, 64, 128, 28, 128);
        $this->assertSameInt(6005, $s->things->mobjs[0]->type);
        $this->monster($s, 3, 16);
        $s->combat->monsters->attack($s->combat, 3, 64, 128, 28, 128);
        $this->assertSameInt(6002, $s->things->mobjs[1]->type);
        $s->combat->radiusDamage(192, 128, 32, 128, -1);
        $this->assertSameInt(4000, $s->things->mobjs[3]->health);
    }

    public function testLastBaronLowersTheE1m8ExitFloor(): void {
        $s = $this->boot();
        $this->isolate($s, 3);
        $s->things->mobjs[4]->active = 1;
        $this->monster($s, 3, 3003);
        $this->monster($s, 4, 3003);
        $s->map->mapName = "E1M8";
        $s->map->sectors[1]->tag = 666;
        $s->map->sectors[1]->floor_height = 64;
        $s->combat->hurt(3, 2000, -1);
        $this->assertSameInt(0, $s->map->sectors[1]->mover);
        $s->combat->hurt(4, 2000, -1);
        $this->assertTrue($s->map->sectors[1]->mover > 0);
        $this->assertTrue($s->combat->bossTriggered);
    }

    public function testCyberdemonDeathEndsEpisodeTwo(): void {
        $s = $this->boot();
        $this->isolate($s, 3);
        $this->monster($s, 3, 16);
        $s->map->mapName = "E2M8";
        $s->mapName = "E2M8";
        $s->combat->hurt(3, 10000, -1);
        $this->advance($s, 1);
        $this->assertSameInt(3, $s->phase);
        $s->render("");
    }

    public function testUltimateFinalBossOpensTheExitWithoutSkippingTheMap(): void {
        $s = $this->boot();
        $this->isolate($s, 3);
        $this->monster($s, 3, 7);
        $s->map->mapName = "E4M8";
        $s->map->sectors[1]->tag = 666;
        $s->map->sectors[1]->floor_height = 64;
        $s->combat->hurt(3, 10000, -1);
        $this->assertTrue($s->map->sectors[1]->mover > 0);
        $this->assertSameInt(0, $s->specials->exitRequest);
    }

    public function testNightmareRespawnsAtTheOriginalPositionAndSector(): void {
        $s = $this->boot();
        $this->isolate($s, 3);
        $s->combat->skill = 5;
        $s->things->mobjs[3]->health = -1;
        $s->things->mobjs[3]->state = 5;
        $s->things->mobjs[3]->solid = 0;
        $s->things->mobjs[3]->spawnX = 384;
        $s->things->mobjs[3]->spawnY = 128;
        $s->things->mobjs[3]->spawnZ = 0;
        $s->things->mobjs[3]->sector = 0;
        for ($t = 0; $t < 5000; $t++) {
            if ($s->things->mobjs[3]->health > 0) { break; }
            $s->combat->monsters->tick($s->combat, 3);
        }
        $this->assertSameInt(20, $s->things->mobjs[3]->health);
        $this->assertSameInt(384, $s->things->mobjs[3]->x);
        $this->assertSameInt(1, $s->things->mobjs[3]->sector);
    }
}
