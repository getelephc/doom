<?php

namespace Showcases\Doom\Tests;

require_once __DIR__ . '/../src/engine.php';
require_once __DIR__ . '/Fixture/TestWad.php';
require_once __DIR__ . '/Fixture/SessionCase.php';
require_once __DIR__ . '/Fixture/CombatCase.php';

use Showcases\Doom\App\Session;
use Showcases\Doom\SDL\Input;
use Showcases\Doom\Tests\Fixture\CombatCase;

final class ElephantCheatTest extends CombatCase {
    public function testTypedCheatTogglesAndSelectsThePistol(): void {
        $s = $this->boot();
        $s->player->weapons |= 8;
        $s->player->weapon = 3;
        $s->cheats->feed($s, "IdE");
        $this->assertFalse($s->player->elephantMode);
        $s->cheats->feed($s, "lePhC");
        $this->assertTrue($s->player->elephantMode);
        $this->advance($s, 1);
        $this->assertSameInt(2, $s->player->weapon);
        $s->cheats->feed($s, "IDELEPHC");
        $this->assertFalse($s->player->elephantMode);
        $this->assertSameString("", $s->cheats->tail);
    }

    public function testElephantTravelsBeforeDamagingItsTarget(): void {
        $s = $this->boot();
        $this->isolate($s, 3);
        $s->things->mobjs[3]->x = 192;
        $s->things->mobjs[3]->y = 128;
        $s->things->mobjs[3]->health = 1000;
        $s->cheats->feed($s, "idelephc");
        $this->fire($s);
        $this->assertSameInt(49, $s->player->bullets);
        $this->assertSameInt(14, $s->player->weaponCooldown);
        $this->assertSameInt(1000, $s->things->mobjs[3]->health);
        $this->assertSameInt(6009, $s->things->mobjs[0]->type);
        $this->assertSameInt(6, $s->things->mobjs[0]->state);
        $this->assertSameInt(0, $s->things->mobjs[0]->splashDamage);
        $s->combat->projectiles->tick($s->combat, 0);
        $this->assertTrue($s->things->mobjs[0]->x > $s->player->unitX());
        for ($i = 0; $i < 10; $i++) { $s->combat->projectiles->tick($s->combat, 0); }
        $this->assertTrue($s->things->mobjs[3]->health < 1000);
        $this->assertSameInt(7, $s->things->mobjs[0]->state);
        $this->assertSameInt(100, $s->player->health);
        for ($i = 0; $i < 15; $i++) { $s->combat->projectiles->tick($s->combat, 0); }
        $this->assertSameInt(0, $s->things->mobjs[0]->active);
    }

    public function testClosedDoorsStopElephantsAndProtectMonstersBehindThem(): void {
        $s = $this->boot();
        $this->isolate($s, 4);
        $s->things->mobjs[4]->x = 384;
        $s->things->mobjs[4]->y = 128;
        $s->things->mobjs[4]->health = 1000;
        $s->map->sectors[1]->ceiling_height = 0;
        $s->cheats->feed($s, "idelephc");
        $this->fire($s);
        for ($i = 0; $i < 14; $i++) { $s->combat->projectiles->tick($s->combat, 0); }
        $this->assertSameInt(1000, $s->things->mobjs[4]->health);
        $this->assertSameInt(7, $s->things->mobjs[0]->state);
        $this->assertTrue($s->things->mobjs[0]->x <= 256);
    }

    public function testDisablingTheCheatRestoresHitscanWithoutRemovingFlyingElephants(): void {
        $s = $this->boot();
        $this->isolate($s, 3);
        $s->things->mobjs[3]->x = 192;
        $s->things->mobjs[3]->y = 128;
        $s->things->mobjs[3]->health = 1000;
        $s->cheats->feed($s, "idelephc");
        $this->fire($s);
        $s->cheats->feed($s, "idelephc");
        $this->assertSameInt(1, $s->things->mobjs[0]->active);
        $s->player->weaponCooldown = 0;
        $s->player->fireHeld = false;
        $this->fire($s);
        $this->assertTrue($s->things->mobjs[3]->health < 1000);
        $this->assertSameInt(48, $s->player->bullets);
        $this->assertSameInt(6, $s->things->mobjs[0]->state);
    }

    public function testTrunkAnimatesAndOtherWeaponsKeepTheirArt(): void {
        $s = $this->boot();
        string $pistol = $this->weaponFrame($s);
        $s->cheats->feed($s, "idelephc");
        string $idle = $this->weaponFrame($s);
        $this->assertFalse($idle === $pistol);
        $s->player->weaponCooldown = 14;
        string $firing = $this->weaponFrame($s);
        $this->assertFalse($idle === $firing);
        $s->player->weaponAge = 6;
        $this->assertFalse($firing === $this->weaponFrame($s));
        $s->player->weapon = 3;
        string $shotgun = $this->weaponFrame($s);
        $s->player->elephantMode = false;
        $this->assertSameString($shotgun, $this->weaponFrame($s));
    }

    public function testHeldFireKeepsPistolCadenceAndEmptyAmmoFallsBack(): void {
        $s = $this->boot();
        $this->isolate($s, -1);
        $s->cheats->feed($s, "idelephc");
        $this->advance($s, 15, 224);
        $this->assertSameInt(48, $s->player->bullets);
        $s->player->bullets = 0;
        $s->player->weaponCooldown = 0;
        $this->fire($s);
        $this->assertSameInt(1, $s->player->weapon);
        $this->assertSameInt(0, $s->player->bullets);
    }

    public function testElephantSpritesAreVisibleAndClippedByWalls(): void {
        $s = $this->boot();
        $this->isolate($s, -1);
        $s->renderer->render($s->map, $s->things, $s->player, 0, "");
        string $empty = $this->frame($s);
        $s->combat->projectiles->launch($s->combat, -1, 192, 128, 48, 240, 128, 48, 6009);
        $s->renderer->render($s->map, $s->things, $s->player, 0, "");
        $this->assertFalse($empty === $this->frame($s));
        $s->map->sectors[1]->ceiling_height = 0;
        $s->things->mobjs[0]->active = 0;
        $s->renderer->render($s->map, $s->things, $s->player, 0, "");
        string $closed = $this->frame($s);
        $s->things->mobjs[0]->active = 1;
        $s->things->mobjs[0]->x = 384;
        $s->things->mobjs[0]->sector = 1;
        $s->renderer->render($s->map, $s->things, $s->player, 0, "");
        $this->assertSameString($closed, $this->frame($s));
    }

    public function testSavesPreserveTheModeAndProjectilesWithoutChangingTheWad(): void {
        $s = $this->boot();
        $this->isolate($s, -1);
        string $original = (string)file_get_contents($this->wadPath);
        $this->assertSameString(hash("sha256", $original), $s->saves->fingerprint($s));
        $s->cheats->feed($s, "idelephc");
        $this->fire($s);
        string $snapshot = $s->saves->encode($s);
        $s->player->elephantMode = false;
        $this->assertTrue($s->saves->decode($s, $snapshot));
        $this->assertTrue($s->player->elephantMode);
        $this->assertSameInt(6009, $s->things->mobjs[0]->type);
        int $before = $s->things->mobjs[0]->x;
        $s->combat->projectiles->tick($s->combat, 0);
        $this->assertTrue($s->things->mobjs[0]->x > $before);
        $this->assertSameString($original, (string)file_get_contents($this->wadPath));
    }

    public function testLegacySavesDisableTheModeAndInvalidFlagsAreRejected(): void {
        $s = $this->boot();
        string $snapshot = $s->saves->encode($s);
        $s->cheats->feed($s, "idelephc");
        $this->assertFalse($s->saves->decode($s, str_replace('"elephant":false', '"elephant":1', $snapshot)));
        $this->assertFalse($s->saves->decode($s, str_replace('"elephant":false', '"elephant":null', $snapshot)));
        $this->assertFalse($s->saves->decode($s, str_replace('"elephant":false', '"elephant":"true"', $snapshot)));
        $this->assertTrue($s->player->elephantMode);
        $this->assertTrue($s->saves->decode($s, str_replace('"elephant":false,', "", $snapshot)));
        $this->assertFalse($s->player->elephantMode);
    }

    public function testMapChangesKeepTheModeAndFreshPlayersResetIt(): void {
        $s = $this->boot();
        $s->cheats->feed($s, "idelephc");
        $this->assertTrue($s->loadMap("E1M2"));
        $this->assertTrue($s->player->elephantMode);
        $s->player->reset();
        $this->assertFalse($s->player->elephantMode);
    }

    protected function fire(Session $s): void {
        buffer<u8> $keys = buffer_new<u8>(512);
        $keys[224] = 1;
        $s->weapons->tick($s->player, $s->combat, new Input(), buffer_ptr($keys));
        buffer_free($keys);
    }

    protected function weaponFrame(Session $s): string {
        $s->renderer->screen->fill(254);
        $s->renderer->weapons->draw($s->renderer->screen, $s->player, $s->trig, 0);
        return $this->frame($s);
    }

    protected function frame(Session $s): string {
        return hash("sha256", ptr_read_string(buffer_ptr($s->renderer->screen->pixels), 64000));
    }
}
