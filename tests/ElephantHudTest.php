<?php

namespace Showcases\Doom\Tests;

require_once __DIR__ . '/../src/engine.php';
require_once __DIR__ . '/Fixture/TestWad.php';
require_once __DIR__ . '/Fixture/SessionCase.php';

use Showcases\Doom\App\Config;
use Showcases\Doom\App\Session;
use Showcases\Doom\Tests\Fixture\SessionCase;
use Showcases\Doom\Tests\Fixture\TestWad;

final class ElephantHudTest extends SessionCase {
    protected function boot(): Session {
        TestWad $wad = new TestWad(true);
        $wad->add("STBAR", $wad->patch(320, 32, 0, 0, 40, 0));
        for ($pain = 0; $pain < 5; $pain++) {
            for ($look = 0; $look < 3; $look++) {
                $wad->add("STFST" . $pain . $look, $wad->patch(24, 30, 0, 0, 100 + $pain * 3 + $look, 0));
            }
        }
        $wad->add("STFGOD0", $wad->patch(24, 30, 0, 0, 206, 0));
        $wad->add("STFDEAD0", $wad->patch(24, 30, 0, 0, 207, 0));
        $this->wadPath = $wad->write();
        Config $config = new Config();
        $config->wadPath = $this->wadPath;
        $config->verbose = false;
        Session $s = new Session($config);
        $this->assertTrue($s->boot());
        return $s;
    }

    public function testElephantReplacesOnlyThePortraitAndTogglesBackToTheMarine(): void {
        $s = $this->boot();
        string $classic = $this->status($s);
        int $marine = $s->renderer->hud->faceLump($s->player);
        $s->cheats->feed($s, "idelephc");
        string $elephant = $this->status($s);
        $this->assertFalse($classic === $elephant);
        int $outside = 0;
        for ($i = 0; $i < 64000; $i++) {
            int $x = $i % 320; int $y = intdiv($i, 320);
            if (($x < 143 || $x >= 175 || $y < 168) && $classic[$i] !== $elephant[$i]) { $outside++; }
        }
        $this->assertSameInt(0, $outside);
        $s->cheats->feed($s, "idelephc");
        $this->assertSameInt($marine, $s->renderer->hud->faceLump($s->player));
        $this->assertSameString($classic, $this->status($s));
    }

    public function testGlancesAndAllFiveHealthBandsHaveDistinctPortraits(): void {
        $s = $this->boot();
        $s->cheats->feed($s, "idelephc");
        $s->renderer->hud->tick(17, 0);
        string $left = $this->portrait($s);
        $s->renderer->hud->tick(34, 2);
        string $right = $this->portrait($s);
        $this->assertFalse($left === $right);
        $s->renderer->hud->tick(51, 1);
        $this->assertFalse($left === $this->portrait($s));
        $this->assertFalse($right === $this->portrait($s));
        $health = [100, 75, 50, 25, 1];
        $seen = [];
        foreach ($health as $value) {
            $s->player->health = (int)$value;
            string $face = $this->portrait($s);
            $this->assertFalse(in_array($face, $seen, true));
            $seen[] = $face;
        }
    }

    public function testPortraitReactsToFireDamagePickupsGodModeAndDeath(): void {
        $s = $this->boot();
        $s->cheats->feed($s, "idelephc");
        string $idle = $this->portrait($s);
        $s->player->fireHeld = true; $s->player->weaponCooldown = 14;
        string $firing = $this->portrait($s);
        $this->assertFalse($idle === $firing);
        $s->player->damageCount = 8;
        string $hurt = $this->portrait($s);
        $this->assertFalse($hurt === $firing);
        $s->player->bonusCount = 8;
        $this->assertSameString($hurt, $this->portrait($s));
        $s->player->damageCount = 0;
        string $grin = $this->portrait($s);
        $this->assertFalse($grin === $hurt);
        $this->assertFalse($grin === $idle);
        $s->player->godMode = true;
        string $god = $this->portrait($s);
        $this->assertFalse($god === $grin);
        $s->player->godMode = false; $s->player->invulnerable = 10;
        $this->assertSameString($god, $this->portrait($s));
        $s->player->health = 0;
        $this->assertFalse($god === $this->portrait($s));
        $s->player->elephantMode = false;
        $this->assertSameInt($s->wad->find("STFDEAD0"), $s->renderer->hud->faceLump($s->player));
    }

    public function testSavesRestoreTheElephantExpressionWithoutExtraState(): void {
        $s = $this->boot();
        $s->cheats->feed($s, "idelephc");
        $s->player->health = 25;
        $s->player->damageCount = 8;
        string $hurt = $this->portrait($s);
        string $snapshot = $s->saves->encode($s);
        $s->player->reset();
        $this->assertTrue($s->saves->decode($s, $snapshot));
        $this->assertSameString($hurt, $this->portrait($s));
    }

    public function testTrunkHasALowForwardSilhouette(): void {
        $s = $this->boot();
        $s->cheats->feed($s, "idelephc");
        $screen = $s->renderer->screen;
        $screen->fill(254);
        $s->renderer->weapons->draw($screen, $s->player, $s->trig, 0);
        int $left = 320; int $right = 0; int $top = 200; int $bottom = 0;
        for ($i = 0; $i < 64000; $i++) {
            if ($screen->pixels[$i] === 254) { continue; }
            int $x = $i % 320; int $y = intdiv($i, 320);
            $left = min($left, $x); $right = max($right, $x);
            $top = min($top, $y); $bottom = max($bottom, $y);
        }
        $this->assertTrue($top >= 115);
        $this->assertTrue($right - $left > $bottom - $top);
        $this->assertTrue($right - $left >= 80);
    }

    protected function status(Session $s): string {
        $s->renderer->screen->fill(254);
        $s->renderer->hud->drawStatusBar($s->player);
        return ptr_read_string(buffer_ptr($s->renderer->screen->pixels), 64000);
    }

    protected function portrait(Session $s): string {
        string $screen = $this->status($s);
        string $crop = "";
        for ($y = 168; $y < 200; $y++) { $crop .= substr($screen, $y * 320 + 143, 32); }
        return hash("sha256", $crop);
    }
}
