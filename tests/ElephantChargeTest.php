<?php

namespace Showcases\Doom\Tests;

require_once __DIR__ . '/../src/engine.php';
require_once __DIR__ . '/Fixture/TestWad.php';
require_once __DIR__ . '/Fixture/SessionCase.php';
require_once __DIR__ . '/Fixture/CombatCase.php';

use Showcases\Doom\App\Session;
use Showcases\Doom\Play\ElephantCharge;
use Showcases\Doom\Tests\Fixture\CombatCase;

final class ElephantChargeTest extends CombatCase {
    protected bool $full = true;

    public function testChargesAreOccasionalAndBeginWithAStationaryWarning(): void {
        $s = $this->boot();
        $this->prepare($s, 3004, 384);
        $s->combat->seed = 7;
        for ($t = 0; $t < 36; $t++) { $s->combat->monsters->tick($s->combat, 3); }
        $this->assertSameInt(1, $s->things->mobjs[3]->state);
        $s->combat->monsters->tick($s->combat, 3);
        $this->assertSameInt(8, $s->things->mobjs[3]->state);
        int $x = $s->things->mobjs[3]->x;
        $this->tick($s, ElephantCharge::WINDUP);
        $this->assertSameInt($x, $s->things->mobjs[3]->x);
        $this->assertSameInt(100, $s->player->health);
        $this->assertSameInt(ElephantCharge::RUN_TICS, $s->things->mobjs[3]->stateTics);
        $s->combat->monsters->tick($s->combat, 3);
        $this->assertTrue($s->things->mobjs[3]->x < $x - 16);
        $this->prepare($s, 3004, 384);
        $s->things->mobjs[3]->stateAge = 36;
        $s->combat->seed = 1;
        $s->combat->monsters->tick($s->combat, 3);
        $this->assertSameInt(1, $s->things->mobjs[3]->state);
    }

    public function testDashDamagesOnlyAtContactOnceThenRecovers(): void {
        $s = $this->boot();
        $this->start($s, 3004, 224);
        $this->tick($s, ElephantCharge::WINDUP);
        $this->assertSameInt(100, $s->player->health);
        for ($t = 0; $t < ElephantCharge::RUN_TICS; $t++) {
            if ($s->things->mobjs[3]->state !== 8) { break; }
            $this->tick($s, 1);
        }
        $this->assertSameInt(100 - ElephantCharge::DAMAGE, $s->player->health);
        $this->assertSameInt(1, $s->things->mobjs[3]->state);
        $this->assertTrue($s->combat->monsters->touchingTarget($s->combat, 3));
        $this->assertSameInt(ElephantCharge::RECOVERY, $s->things->mobjs[3]->cooldown);
        $this->assertSameInt(0, $s->things->mobjs[3]->velX);
        $this->tick($s, 50);
        $this->assertSameInt(100 - ElephantCharge::DAMAGE, $s->player->health);
        $this->assertSameInt(0, $s->things->mobjs[0]->active);
    }

    public function testSidesteppingDuringTheWarningDodgesTheLockedDirection(): void {
        $s = $this->boot();
        $this->start($s, 3004, 224);
        $s->player->y = 208 << 16;
        $this->finish($s);
        $this->assertSameInt(100, $s->player->health);
        $this->assertSameInt(128, $s->things->mobjs[3]->y);
        $this->assertSameInt(1, $s->things->mobjs[3]->state);
        $this->assertSameInt(0, $s->things->mobjs[3]->velX);
        $this->assertTrue($s->things->mobjs[3]->x >= $s->things->mobjs[3]->radius);
    }

    public function testClosedDoorsAndSolidObjectsStopTheSweptDash(): void {
        $s = $this->boot();
        $s->player->x = 384 << 16;
        $this->start($s, 3004, 192);
        $s->map->sectors[1]->ceiling_height = 0;
        $this->finish($s);
        $this->assertTrue($s->things->mobjs[3]->x < 256);
        $this->assertSameInt(100, $s->player->health);
        $this->assertSameInt(1, $s->things->mobjs[3]->state);
        $s->map->sectors[1]->ceiling_height = 128;
        $this->start($s, 3004, 192);
        $s->things->mobjs[6]->active = 1;
        $s->things->mobjs[6]->x = 240;
        $s->things->mobjs[6]->y = 128;
        $this->finish($s);
        $this->assertTrue($s->things->mobjs[3]->x < 220);
        $this->assertSameInt(20, $s->things->mobjs[6]->health);
        $this->assertSameInt(100, $s->player->health);
    }

    public function testFoodInterruptsWarningAndDashAndDisablingTheModeCancelsIt(): void {
        $s = $this->boot();
        $this->start($s, 3004, 224);
        $s->combat->hurt(3, 1, -1);
        $this->assertSameInt(3, $s->things->mobjs[3]->state);
        $this->assertSameInt(0, $s->things->mobjs[3]->hitDamage);
        $this->assertSameInt(0, $s->things->mobjs[3]->velX);
        $this->start($s, 3004, 224);
        $this->tick($s, ElephantCharge::WINDUP + 1);
        $s->combat->hurt(3, 1, -1);
        $this->assertSameInt(3, $s->things->mobjs[3]->state);
        $this->assertSameInt(0, $s->things->mobjs[3]->velX);
        $this->start($s, 3004, 224);
        $s->cheats->feed($s, "idelephc");
        $this->tick($s, 1);
        $this->assertSameInt(1, $s->things->mobjs[3]->state);
        $this->assertSameInt(0, $s->things->mobjs[3]->hitDamage);
        $this->assertSameInt(0, $s->things->mobjs[3]->velX);
        $s->cheats->feed($s, "idelephc");
        $this->start($s, 3004, 224);
        $s->combat->hurt(3, 1000, -1);
        $this->tick($s, 60);
        $this->assertSameInt(5, $s->things->mobjs[3]->state);
        $this->assertSameInt(100, $s->player->health);
    }

    public function testAllFamiliesCanChargeButOriginalModeCannotStartOne(): void {
        $s = $this->boot();
        $types = [3004, 9, 3001, 3002, 58, 3006, 3005, 3003, 16, 7];
        foreach ($types as $type) {
            $this->start($s, (int)$type, 384);
            $this->assertSameInt(ElephantCharge::DAMAGE, $s->things->mobjs[3]->hitDamage);
            $this->assertSameInt(0, $s->things->mobjs[0]->active);
        }
        $s->player->elephantMode = false;
        $this->assertFalse($s->combat->elephants->tryStart($s->combat, 3, -320, 0, true));
    }

    public function testSavesResumeBothTheWarningAndDashWithMatchingMovement(): void {
        $s = $this->boot();
        $this->start($s, 3004, 224);
        string $warning = $s->saves->encode($s);
        $this->tick($s, 5);
        int $time = $s->things->mobjs[3]->stateTics;
        $this->assertTrue($s->saves->decode($s, $warning));
        $this->tick($s, 5);
        $this->assertSameInt($time, $s->things->mobjs[3]->stateTics);
        $this->assertSameInt(224, $s->things->mobjs[3]->x);
        $this->tick($s, ElephantCharge::WINDUP - 5 + 1);
        string $dash = $s->saves->encode($s);
        $this->tick($s, 3);
        int $x = $s->things->mobjs[3]->x;
        $time = $s->things->mobjs[3]->stateTics;
        int $health = $s->player->health;
        $this->assertTrue($s->saves->decode($s, $dash));
        $this->tick($s, 3);
        $this->assertSameInt($x, $s->things->mobjs[3]->x);
        $this->assertSameInt($time, $s->things->mobjs[3]->stateTics);
        $this->assertSameInt($health, $s->player->health);
        $s->render("");
        int $definition = $s->renderer->spriteRenderer->definitionFor($s->things, 3, 0);
        $this->assertTrue($s->sprites->frame[$definition] >= 9);
    }

    protected function start(Session $s, int $type, int $x): void {
        $this->prepare($s, $type, $x);
        $s->things->mobjs[3]->stateAge = 36;
        $s->combat->seed = 7;
        $this->tick($s, 1);
        $this->assertSameInt(8, $s->things->mobjs[3]->state);
    }

    protected function prepare(Session $s, int $type, int $x): void {
        $this->isolate($s, 3);
        $s->player->elephantMode = true;
        int $kind = $s->things->types->find($type);
        $m = $s->things->mobjs;
        $m[3]->type = $type; $m[3]->kind = $kind;
        $m[3]->radius = $s->things->types->radius[$kind];
        $m[3]->height = $s->things->types->height[$kind];
        $m[3]->health = $s->combat->rules->health($type); $m[3]->solid = 1;
        $m[3]->x = $x; $m[3]->y = 128; $m[3]->z = 0;
        $m[3]->sector = $x >= 256 ? 1 : 0;
        $m[3]->state = 1; $m[3]->stateAge = 0; $m[3]->cooldown = 0;
        $m[3]->target = -1; $m[3]->angle = 4096; $m[3]->hitDamage = 0;
    }

    protected function tick(Session $s, int $tics): void {
        for ($t = 0; $t < $tics; $t++) { $s->combat->monsters->tick($s->combat, 3); }
    }

    protected function finish(Session $s): void {
        int $end = ElephantCharge::WINDUP + ElephantCharge::RUN_TICS;
        for ($t = 0; $t < $end; $t++) {
            if ($s->things->mobjs[3]->state !== 8) { break; }
            $this->tick($s, 1);
        }
    }
}
