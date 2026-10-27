<?php

namespace Showcases\Doom\Tests;

require_once __DIR__ . '/../src/engine.php';
require_once __DIR__ . '/Fixture/TestWad.php';
require_once __DIR__ . '/Fixture/SessionCase.php';
require_once __DIR__ . '/Fixture/CombatCase.php';

use Showcases\Doom\Tests\Fixture\CombatCase;

final class MusicTest extends CombatCase {
    protected bool $full = true;

    public function testMusTimingPolyphonyAndLoopProducePcm(): void {
        $s = $this->boot();
        $music = $s->sounds->music;
        $this->assertSameString("D_E1M1", $music->name);
        $this->assertSameInt(11025, $music->duration);
        $music->mix();
        int $energy = 0;
        for ($i = 0; $i < 1260; $i++) { $energy += abs($music->pcm[$i]); }
        $this->assertTrue($energy > 1000);
        $this->assertSameInt(630, $music->cursor);
        for ($t = 0; $t < 8; $t++) { $music->mix(); }
        $this->assertSameInt(1, $music->voices[0]->released);
        for ($t = 0; $t < 9; $t++) { $music->mix(); }
        $this->assertSameInt(315, $music->cursor);
    }

    public function testMalformedScoreDoesNotReplaceTheCurrentSong(): void {
        $s = $this->boot();
        $this->assertFalse($s->sounds->music->load("D_BAD"));
        $this->assertSameString("D_E1M1", $s->sounds->music->name);
        $s->sounds->music->stop();
        $s->sounds->music->mix();
        $this->assertSameInt(0, $s->sounds->music->pcm[50]);
    }

    public function testControllersAndVoiceLimitsRemainBounded(): void {
        $s = $this->boot();
        $music = $s->sounds->music;
        for ($i = 0; $i < 40; $i++) { $music->dispatch(1, 0, 40 + $i, 100); }
        $this->assertSameInt(32, buffer_len($music->voices));
        $music->dispatch(4, 0, 8, 127);
        $music->dispatch(0, 0, 79, 0);
        $this->assertSameInt(2, $music->voices[7]->released);
        $music->dispatch(4, 0, 8, 0);
        $this->assertSameInt(1, $music->voices[7]->released);
    }
}
