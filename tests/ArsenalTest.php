<?php

namespace Showcases\Doom\Tests;

require_once __DIR__ . '/../src/engine.php';
require_once __DIR__ . '/Fixture/TestWad.php';
require_once __DIR__ . '/Fixture/SessionCase.php';
require_once __DIR__ . '/Fixture/CombatCase.php';

use Showcases\Doom\Tests\Fixture\CombatCase;

final class ArsenalTest extends CombatCase {
    protected bool $full = true;

    public function testAllOriginalWeaponsCanBeGrantedAndSelected(): void {
        $s = $this->boot();
        $s->player->inventory->grant($s->player, true);
        $this->assertSameInt(510, $s->player->weapons);
        $this->assertSameInt(7, $s->player->keys);
        $this->assertSameInt(300, $s->player->cells);
        $this->advance($s, 1, 34);
        $this->assertSameInt(5, $s->player->weapon);
        $this->advance($s, 1);
        $this->advance($s, 1, 30);
        $this->assertSameInt(8, $s->player->weapon);
        $this->advance($s, 1);
        $this->advance($s, 1, 30);
        $this->assertSameInt(1, $s->player->weapon);
    }

    public function testChaingunAndPlasmaUseTheirOwnAmmo(): void {
        $s = $this->boot();
        $this->isolate($s, 3);
        $s->player->inventory->grant($s->player, false);
        $s->player->godMode = true;
        $s->player->weapon = 4;
        $this->advance($s, 5, 224);
        $this->assertSameInt(198, $s->player->bullets);
        $s->player->weaponCooldown = 0;
        $s->player->weapon = 6;
        $this->advance($s, 4, 224);
        $this->assertSameInt(298, $s->player->cells);
        $this->assertSameInt(198, $s->player->bullets);
    }

    public function testRocketExplosionCanHurtItsOwnerAndNearbyMonsters(): void {
        $s = $this->boot();
        $this->isolate($s, 3);
        $s->player->weapons |= 32;
        $s->player->weapon = 5;
        $s->player->rockets = 2;
        $s->things->mobjs[3]->x = 144;
        $this->advance($s, 8, 224);
        $this->assertSameInt(1, $s->player->rockets);
        $this->assertTrue($s->player->health < 100);
        $this->assertSameInt(1, $s->combat->kills);
    }

    public function testBfgConsumesFortyCellsAndWaitsForItsCharge(): void {
        $s = $this->boot();
        $this->isolate($s, -1);
        $s->player->inventory->grant($s->player, false);
        $s->player->godMode = true;
        $s->player->weapon = 7;
        $this->advance($s, 1, 224);
        $this->assertSameInt(260, $s->player->cells);
        $this->assertSameInt(0, $s->things->mobjs[0]->active);
        $this->advance($s, 20);
        $this->assertSameInt(6004, $s->things->mobjs[0]->type);
        $this->assertSameInt(6, $s->things->mobjs[0]->state);
    }

    public function testChainsawAndBerserkDoNotConsumeAmmunition(): void {
        $s = $this->boot();
        $this->isolate($s, 3);
        $s->things->mobjs[3]->x = 112;
        $s->player->pickup(2023);
        $this->advance($s, 1, 224);
        $this->assertSameInt(1, $s->combat->kills);
        $this->assertSameInt(50, $s->player->bullets);
        $s->player->weaponCooldown = 0;
        $s->player->pickup(2005);
        $this->advance($s, 1, 224);
        $this->assertSameInt(4, $s->player->weaponCooldown);
        $this->assertSameInt(50, $s->player->bullets);
    }

    public function testBfgSprayDamagesVisibleTargetsAndCannotCrossAClosedDoor(): void {
        $s = $this->boot();
        $this->isolate($s, 3);
        $s->things->mobjs[3]->health = 1000;
        $s->combat->projectiles->bfgSpray($s->combat, -1);
        $this->assertTrue($s->things->mobjs[3]->health < 500);
        $s->things->mobjs[3]->active = 0;
        $s->things->mobjs[4]->active = 1;
        $s->things->mobjs[4]->health = 1000;
        $s->things->mobjs[4]->x = 384; $s->things->mobjs[4]->y = 128;
        $s->map->sectors[1]->ceiling_height = 0;
        $s->combat->projectiles->bfgSpray($s->combat, -1);
        $this->assertSameInt(1000, $s->things->mobjs[4]->health);
    }
}
