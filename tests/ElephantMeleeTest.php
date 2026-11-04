<?php

namespace Showcases\Doom\Tests;

require_once __DIR__ . '/../src/engine.php';
require_once __DIR__ . '/Fixture/TestWad.php';
require_once __DIR__ . '/Fixture/SessionCase.php';
require_once __DIR__ . '/Fixture/CombatCase.php';

use Showcases\Doom\App\Session;
use Showcases\Doom\Tests\Fixture\CombatCase;

final class ElephantMeleeTest extends CombatCase {
    protected bool $full = true;

    public function testEveryFamilyChasesInsteadOfShootingAtRange(): void {
        $s = $this->boot();
        $this->isolate($s, 3);
        $s->cheats->feed($s, "idelephc");
        $types = [3004, 9, 3001, 3002, 58, 3006, 3005, 3003, 16, 7];
        int $count = $s->things->count;
        foreach ($types as $type) {
            $this->monster($s, (int)$type, 384);
            $s->combat->monsters->attack($s->combat, 3, 64, 128, 28, 320);
            for ($t = 0; $t < 18; $t++) { $s->combat->monsters->tick($s->combat, 3); }
            $this->assertSameInt(100, $s->player->health);
            $this->assertSameInt($count, $s->things->count);
            $this->assertSameInt(1, $s->things->mobjs[3]->state);
        }
    }

    public function testEveryFamilyCanDamageOnlyAtBodyContact(): void {
        $s = $this->boot();
        $this->isolate($s, 3);
        $s->cheats->feed($s, "idelephc");
        $types = [3004, 9, 3001, 3002, 58, 3006, 3005, 3003, 16, 7];
        foreach ($types as $type) {
            $this->monster($s, (int)$type, 192);
            $s->player->health = 100;
            $s->things->mobjs[3]->x = 64 + 16 + $s->things->mobjs[3]->radius;
            $this->assertTrue($s->combat->monsters->touchingTarget($s->combat, 3));
            $s->combat->monsters->attack($s->combat, 3, 64, 128, 28, 0);
            $this->assertTrue($s->player->health < 100);
            $this->assertSameInt(1, $s->things->mobjs[3]->state);
        }
    }

    public function testContactCannotReachAcrossHeightsWallsOrAnEscapedTarget(): void {
        $s = $this->boot();
        $this->isolate($s, 3);
        $s->cheats->feed($s, "idelephc");
        $this->monster($s, 3004, 100);
        $s->things->mobjs[3]->z = 64;
        $s->combat->monsters->attack($s->combat, 3, 64, 128, 28, 36);
        $this->assertSameInt(100, $s->player->health);
        $s->things->mobjs[3]->z = 0;
        $s->things->mobjs[3]->state = 2;
        $s->things->mobjs[3]->stateTics = 1;
        $s->player->x = 16 << 16;
        $s->combat->monsters->tick($s->combat, 3);
        $this->assertSameInt(100, $s->player->health);
        $this->assertSameInt(1, $s->things->mobjs[3]->state);
        $s->player->x = 240 << 16;
        $s->things->mobjs[3]->x = 276;
        $s->things->mobjs[3]->sector = 1;
        $s->map->sectors[1]->ceiling_height = 0;
        $s->combat->monsters->attack($s->combat, 3, 240, 128, 28, 36);
        $this->assertSameInt(100, $s->player->health);
    }

    public function testPendingRangedAttacksAndChargesBecomeChasesOnToggle(): void {
        $s = $this->boot();
        $this->isolate($s, 3);
        $this->monster($s, 16, 384);
        $s->things->mobjs[3]->state = 2;
        $s->things->mobjs[3]->stateTics = 7;
        $s->cheats->feed($s, "idelephc");
        $s->combat->monsters->tick($s->combat, 3);
        $this->assertSameInt(1, $s->things->mobjs[3]->state);
        $this->assertSameInt(0, $s->things->mobjs[0]->active);
        $this->monster($s, 3006, 384);
        $s->things->mobjs[3]->state = 8;
        $s->things->mobjs[3]->stateTics = 70;
        $s->things->mobjs[3]->velX = -20;
        $s->combat->monsters->tick($s->combat, 3);
        $this->assertSameInt(1, $s->things->mobjs[3]->state);
        $this->assertSameInt(0, $s->things->mobjs[3]->velX);
        $this->assertSameInt(100, $s->player->health);
    }

    public function testFastLargeElephantReachesContactAndStopsDamagingWhenAsleep(): void {
        $s = $this->boot();
        $this->isolate($s, 3);
        $s->cheats->feed($s, "idelephc");
        $this->monster($s, 16, 129);
        for ($t = 0; $t < 35; $t++) { $s->combat->monsters->tick($s->combat, 3); }
        $this->assertTrue($s->player->health < 100);
        $this->assertTrue($s->things->mobjs[3]->x >= 120);
        $this->assertSameInt(0, $s->things->mobjs[0]->active);
        $s->combat->hurt(3, 10000, -1);
        int $health = $s->player->health;
        for ($t = 0; $t < 100; $t++) { $s->combat->monsters->tick($s->combat, 3); }
        $this->assertSameInt($health, $s->player->health);
        $this->assertSameInt(5, $s->things->mobjs[3]->state);
    }

    public function testModeRetiresHostileShotsButKeepsPlayerFoodAndRestoresNormalAttacks(): void {
        $s = $this->boot();
        $this->isolate($s, 3);
        $this->monster($s, 3001, 192);
        $s->combat->projectiles->launch($s->combat, 3, 96, 128, 28, 64, 128, 28, 6000);
        $s->cheats->feed($s, "idelephc");
        $s->combat->projectiles->tick($s->combat, 0);
        $this->assertSameInt(0, $s->things->mobjs[0]->active);
        $this->assertSameInt(100, $s->player->health);
        $s->combat->projectiles->launch($s->combat, 3, 96, 128, 28, 64, 128, 28, 6002);
        $s->things->mobjs[0]->state = 7;
        $s->combat->projectiles->tick($s->combat, 0);
        $this->assertSameInt(0, $s->things->mobjs[0]->active);
        $this->assertSameInt(100, $s->player->health);
        $s->combat->projectiles->launch($s->combat, -1, 96, 128, 28, 192, 128, 28, 6009);
        $s->combat->projectiles->tick($s->combat, 0);
        $this->assertSameInt(1, $s->things->mobjs[0]->active);
        $this->assertSameInt(6, $s->things->mobjs[0]->state);
        $s->cheats->feed($s, "idelephc");
        $s->combat->monsters->attack($s->combat, 3, 64, 128, 28, 128);
        $this->assertSameInt(6000, $s->things->mobjs[1]->type);
        $this->assertSameInt(6, $s->things->mobjs[1]->state);
    }

    protected function monster(Session $s, int $type, int $x): void {
        int $kind = $s->things->types->find($type);
        $m = $s->things->mobjs;
        $m[3]->type = $type; $m[3]->kind = $kind;
        $m[3]->radius = $s->things->types->radius[$kind];
        $m[3]->height = $s->things->types->height[$kind];
        $m[3]->health = $s->combat->rules->health($type);
        $m[3]->x = $x; $m[3]->y = 128; $m[3]->z = 0;
        $m[3]->sector = $x >= 256 ? 1 : 0;
        $m[3]->state = 1; $m[3]->stateAge = 0; $m[3]->cooldown = 0;
        $m[3]->target = -1; $m[3]->angle = 4096;
    }
}
