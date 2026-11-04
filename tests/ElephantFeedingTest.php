<?php

namespace Showcases\Doom\Tests;

require_once __DIR__ . '/../src/engine.php';
require_once __DIR__ . '/Fixture/TestWad.php';
require_once __DIR__ . '/Fixture/SessionCase.php';
require_once __DIR__ . '/Fixture/CombatCase.php';

use Showcases\Doom\App\Session;
use Showcases\Doom\Tests\Fixture\CombatCase;

final class ElephantFeedingTest extends CombatCase {
    protected bool $full = true;

    public function testEveryMonsterFamilyUsesElephantsButItemsAndBarrelsKeepTheirArt(): void {
        $s = $this->boot();
        $renderer = $s->renderer->spriteRenderer;
        $s->cheats->feed($s, "idelephc");
        $s->render("");
        $types = [3004, 9, 3001, 3002, 58, 3006, 3005, 3003, 16, 7];
        foreach ($types as $type) {
            $s->things->mobjs[3]->type = (int)$type;
            $s->things->mobjs[3]->state = 0;
            int $definition = $renderer->definitionFor($s->things, 3, 0);
            $this->assertSameInt($s->wad->key("ELEN"), $s->sprites->spriteKey[$definition]);
        }
        $this->assertSameInt($s->things->definitionAt(0, 0), $renderer->definitionFor($s->things, 0, 0));
        $this->assertSameInt($s->things->definitionAt(6, 0), $renderer->definitionFor($s->things, 6, 0));
        $s->cheats->feed($s, "idelephc");
        $s->render("");
        $this->assertSameInt($s->things->definitionAt(3, 0), $renderer->definitionFor($s->things, 3, 0));
    }

    public function testFoodMakesAnElephantChewThenFlopAndSleepWithoutBlocking(): void {
        $s = $this->boot();
        $this->isolate($s, 3);
        $s->cheats->feed($s, "idelephc");
        $s->render("");
        $renderer = $s->renderer->spriteRenderer;
        $s->things->mobjs[3]->health = 25;
        $s->combat->hurt(3, 1, -1);
        $this->assertSameInt(3, $s->things->mobjs[3]->state);
        int $chewing = $renderer->definitionFor($s->things, 3, 0);
        $this->assertSameInt(4, $s->sprites->frame[$chewing]);
        // A real food projectile delivers the last mouthful.
        $s->things->mobjs[3]->health = 1;
        $s->combat->projectiles->launch($s->combat, -1, 160, 128, 32, 192, 128, 32, 6009);
        $s->combat->projectiles->tick($s->combat, 0);
        $this->assertSameInt(4, $s->things->mobjs[3]->state);
        $this->assertSameInt(0, $s->things->mobjs[3]->solid);
        $this->assertSameInt(1, $s->combat->kills);
        int $tipping = $renderer->definitionFor($s->things, 3, 0);
        $this->assertSameInt(5, $s->sprites->frame[$tipping]);
        for ($t = 0; $t < 5; $t++) { $s->combat->monsters->tick($s->combat, 3); }
        int $flopped = $renderer->definitionFor($s->things, 3, 0);
        $this->assertSameInt(6, $s->sprites->frame[$flopped]);
        for ($t = 0; $t < 60; $t++) { $s->combat->monsters->tick($s->combat, 3); }
        $this->assertSameInt(5, $s->things->mobjs[3]->state);
        $this->assertSameInt(1, $s->things->mobjs[3]->active);
        int $asleep = $renderer->definitionFor($s->things, 3, 0);
        $this->assertSameInt(7, $s->sprites->frame[$asleep]);
        int $breathing = $renderer->definitionFor($s->things, 3, 16);
        $this->assertSameInt(8, $s->sprites->frame[$breathing]);
        int $kills = $s->combat->kills;
        $s->combat->hurt(3, 100, -1);
        $this->assertSameInt($kills, $s->combat->kills);
    }

    public function testSleepingFlyingElephantsSettleOntoTheFloorAndSurviveSaves(): void {
        $s = $this->boot();
        $this->isolate($s, 3);
        $s->cheats->feed($s, "idelephc");
        $s->things->mobjs[3]->type = 3005;
        $s->things->mobjs[3]->kind = $s->things->types->find(3005);
        $s->things->mobjs[3]->z = 48;
        $s->combat->hurt(3, 1000, -1);
        for ($t = 0; $t < 60; $t++) { $s->combat->monsters->tick($s->combat, 3); }
        $this->assertSameInt(0, $s->things->mobjs[3]->z);
        $this->assertSameInt(5, $s->things->mobjs[3]->state);
        string $snapshot = $s->saves->encode($s);
        $s->player->elephantMode = false;
        $this->assertTrue($s->saves->decode($s, $snapshot));
        $this->assertTrue($s->player->elephantMode);
        $this->assertSameInt(5, $s->things->mobjs[3]->state);
        $this->assertSameInt(0, $s->things->mobjs[3]->solid);
        $s->render("");
        int $definition = $s->renderer->spriteRenderer->definitionFor($s->things, 3, 0);
        $this->assertSameInt(7, $s->sprites->frame[$definition]);
    }

    public function testSleepingElephantAndLettersAreClippedTogetherBehindClosedDoors(): void {
        $s = $this->boot();
        $this->isolate($s, 3);
        $s->cheats->feed($s, "idelephc");
        $s->combat->hurt(3, 1000, -1);
        for ($t = 0; $t < 60; $t++) { $s->combat->monsters->tick($s->combat, 3); }
        $s->things->mobjs[3]->active = 0;
        $s->render("");
        string $empty = $this->frame($s);
        $s->things->mobjs[3]->active = 1;
        $s->render("");
        $this->assertFalse($empty === $this->frame($s));
        $s->map->sectors[1]->ceiling_height = 0;
        $s->things->mobjs[3]->active = 0;
        $s->render("");
        string $closed = $this->frame($s);
        $s->things->mobjs[3]->active = 1;
        $s->things->mobjs[3]->x = 384;
        $s->things->mobjs[3]->sector = 1;
        $s->render("");
        $this->assertSameString($closed, $this->frame($s));
    }

    public function testFullBossStillUnlocksTheExit(): void {
        $s = $this->boot();
        $this->isolate($s, 3);
        $s->cheats->feed($s, "idelephc");
        $s->things->mobjs[3]->type = 3003;
        $s->things->mobjs[3]->kind = $s->things->types->find(3003);
        $s->map->mapName = "E1M8";
        $s->map->sectors[1]->tag = 666;
        $s->map->sectors[1]->floor_height = 64;
        $s->combat->hurt(3, 2000, -1);
        $this->assertSameInt(4, $s->things->mobjs[3]->state);
        $this->assertTrue($s->map->sectors[1]->mover > 0);
        $this->assertTrue($s->combat->bossTriggered);
    }

    protected function frame(Session $s): string {
        return hash("sha256", ptr_read_string(buffer_ptr($s->renderer->screen->pixels), 64000));
    }
}
