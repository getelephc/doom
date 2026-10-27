<?php

namespace Showcases\Doom\Tests;

require_once __DIR__ . '/../src/engine.php';
require_once __DIR__ . '/Fixture/TestWad.php';
require_once __DIR__ . '/Fixture/SessionCase.php';
require_once __DIR__ . '/Fixture/CombatCase.php';

use Showcases\Doom\Tests\Fixture\CombatCase;

final class PowerupsTest extends CombatCase {
    protected bool $full = true;

    public function testSuitBlocksFloorDamageAndExpires(): void {
        $s = $this->boot();
        $this->isolate($s, -1);
        $s->map->sectors[0]->special = 7;
        $s->player->pickup(2025);
        $this->advance($s, 33);
        $this->assertSameInt(100, $s->player->health);
        $s->player->suit = 1;
        $this->advance($s, 32);
        $this->assertSameInt(0, $s->player->suit);
        $this->assertSameInt(95, $s->player->health);
    }

    public function testInvulnerabilityAndVisorSelectWorldColormaps(): void {
        $s = $this->boot();
        $s->player->pickup(2045);
        $s->render("");
        $this->assertSameInt(0, $s->renderer->world->fixedColormap);
        $s->player->pickup(2022);
        $s->render("");
        $this->assertSameInt(8192, $s->renderer->world->fixedColormap);
        $s->player->damage(10000);
        $this->assertSameInt(100, $s->player->health);
    }

    public function testDifficultyControlsDamageAndAmmoAndLevelChangesClearPowers(): void {
        $s = $this->boot();
        $s->player->skill = 1;
        $s->player->damage(20);
        $this->assertSameInt(90, $s->player->health);
        $s->player->pickup(2007);
        $this->assertSameInt(70, $s->player->bullets);
        $s->player->pickup(2023);
        $s->player->pickup(2022);
        $s->loadMap("E1M2");
        $this->assertFalse($s->player->berserk);
        $this->assertSameInt(0, $s->player->invulnerable);
        $this->assertSameInt(70, $s->player->bullets);
    }

    public function testEndSectorDisablesCheatsAndIgnoresTheRadiationSuit(): void {
        $s = $this->boot();
        $this->isolate($s, -1);
        $s->mapName = "E1M8";
        $s->map->sectors[0]->special = 11;
        $s->player->godMode = true;
        $s->player->invulnerable = 1050;
        $s->player->suit = 2100;
        $this->advance($s, 160);
        $this->assertFalse($s->player->godMode);
        $this->assertSameInt(3, $s->phase);
    }
}
