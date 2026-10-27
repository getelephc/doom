<?php

namespace Showcases\Doom\Tests;

require_once __DIR__ . '/../src/engine.php';
require_once __DIR__ . '/Fixture/TestWad.php';
require_once __DIR__ . '/Fixture/SessionCase.php';
require_once __DIR__ . '/Fixture/CombatCase.php';

use Showcases\Doom\Tests\Fixture\CombatCase;

final class CheatsTest extends CombatCase {
    protected bool $full = true;

    public function testGodModeAcceptsFragmentedTextAndTogglesDamage(): void {
        $s = $this->boot();
        $s->cheats->feed($s, "IdD");
        $this->assertFalse($s->player->godMode);
        $s->cheats->feed($s, "qD");
        $this->assertTrue($s->player->godMode);
        $s->player->damage(1000);
        $this->assertSameInt(100, $s->player->health);
        $s->cheats->feed($s, "iddqd");
        $s->player->damage(10);
        $this->assertSameInt(90, $s->player->health);
    }

    public function testNoclipCrossesClosedDoorsAndCanBeDisabled(): void {
        $s = $this->boot();
        $this->isolate($s, -1);
        $s->map->sectors[1]->ceiling_height = 0;
        $s->cheats->feed($s, "idspispopd");
        $this->advance($s, 35, 26);
        $this->assertTrue($s->player->unitX() > 256);
        $s->cheats->feed($s, "idclip");
        $this->assertFalse($s->player->noclip);
    }

    public function testWarpRejectsMissingMapsAndLoadsAvailableMaps(): void {
        $s = $this->boot();
        $s->cheats->feed($s, "idclev99");
        $this->assertSameString("E1M1", $s->mapName);
        $s->cheats->feed($s, "idclev49");
        $this->assertSameString("E1M1", $s->mapName);
        $s->cheats->feed($s, "idclev12");
        $this->assertSameString("E1M2", $s->mapName);
    }

    public function testPowerupsBackpackAndCheatEffectsUseInventoryRules(): void {
        $s = $this->boot();
        $s->player->pickup(8);
        $s->player->pickup(8);
        $this->assertSameInt(400, $s->player->maxBullets);
        $this->assertSameInt(600, $s->player->maxCells);
        $s->cheats->feed($s, "idfa");
        $this->assertSameInt(400, $s->player->bullets);
        $this->assertSameInt(0, $s->player->keys);
        $s->cheats->feed($s, "idbeholdv");
        $s->player->damage(1000);
        $this->assertSameInt(100, $s->player->health);
        $s->cheats->feed($s, "idbeholdaiddtiddt");
        $this->assertTrue($s->player->fullMap);
        $this->assertSameInt(2, $s->renderer->automap->reveal);
    }
}
