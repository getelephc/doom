<?php

namespace Showcases\Doom\Tests;

require_once __DIR__ . '/../src/engine.php';
require_once __DIR__ . '/Fixture/TestWad.php';
require_once __DIR__ . '/Fixture/SessionCase.php';
require_once __DIR__ . '/Fixture/CombatCase.php';

use Showcases\Doom\Tests\Fixture\CombatCase;

final class LevelFlowTest extends CombatCase {
    // Real inputs kill the blocking enemy, cross the map and use the exit switch.
    public function testPlayFromSpawnThroughCombatToTheExitAndContinue(): void {
        $s = $this->boot();
        $this->isolate($s, 3);
        $s->map->linedefs[4]->special = 11;
        $this->advance($s, 50, 224);
        $this->assertSameInt(1, $s->combat->kills);
        $this->advance($s, 65, 26);
        $this->assertTrue($s->player->unitX() > 448);
        $this->advance($s, 1, 8);
        $this->assertSameInt(2, $s->phase);
        $this->assertSameString("E1M1", $s->mapName);
        int $time = $s->levelTime;
        $this->advance($s, 40, 26);
        $this->assertSameInt($time, $s->levelTime);
        $this->assertSameInt(1, $s->combat->kills);
        $s->render("");
        $this->advance($s, 1, 40);
        $this->assertSameInt(0, $s->phase);
        $this->assertSameString("E1M2", $s->mapName);
        $this->assertSameInt(0, $s->combat->kills);
        $this->assertSameInt(100, $s->player->health);
    }

    public function testHeldFireCannotSkipDeathOrIntermission(): void {
        $s = $this->boot();
        $s->player->health = 0;
        $this->advance($s, 45, 224);
        $this->assertSameInt(1, $s->phase);
        $this->assertSameInt(0, $s->player->health);
        $this->advance($s, 1);
        $this->advance($s, 1, 40);
        $this->assertSameInt(0, $s->phase);
        $this->assertSameInt(100, $s->player->health);
        $s->specials->exitRequest = 1;
        $this->advance($s, 45, 224);
        $this->assertSameInt(2, $s->phase);
        $this->assertSameString("E1M1", $s->mapName);
        $this->advance($s, 1);
        $this->advance($s, 1, 40);
        $this->assertSameString("E1M2", $s->mapName);
    }

    public function testSecretAndItemStatisticsCountEachPickupOnlyOnce(): void {
        $s = $this->boot();
        $this->isolate($s, 0);
        $s->map->sectors[1]->special = 9;
        $s->player->spawn(384, 128, 0, 0);
        $this->advance($s, 3);
        $this->assertSameInt(1, $s->combat->secrets);
        $this->assertSameInt(1, $s->combat->items);
        $this->assertSameInt(101, $s->player->health);
        $this->assertSameInt(0, $s->things->mobjs[0]->active);
    }

    public function testWeaponAndFlashAppearInTheViewBeforeTheHud(): void {
        $s = $this->boot();
        $this->isolate($s, 3);
        $s->render("");
        $this->assertSameInt(210, $s->renderer->screen->pixels[145 * 320 + 160]);
        $this->advance($s, 1, 224);
        $s->render("");
        $this->assertSameInt(212, $s->renderer->screen->pixels[115 * 320 + 160]);
        $s->player->health = 0;
        $s->render("");
        $this->assertTrue($s->renderer->screen->pixels[145 * 320 + 160] !== 210);
    }
}
