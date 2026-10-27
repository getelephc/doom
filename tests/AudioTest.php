<?php

namespace Showcases\Doom\Tests;

require_once __DIR__ . '/../src/engine.php';
require_once __DIR__ . '/Fixture/TestWad.php';
require_once __DIR__ . '/Fixture/SessionCase.php';
require_once __DIR__ . '/Fixture/CombatCase.php';

use Showcases\Doom\Audio\Sounds;
use Showcases\Doom\Tests\Fixture\CombatCase;

final class AudioTest extends CombatCase {
    public function testPcmIsResampledToStereoAndEndsInSilence(): void {
        $s = $this->boot();
        $s->sounds->play(Sounds::PISTOL);
        $s->sounds->mix();
        $this->assertSameInt(6912, $s->sounds->output[0]);
        $this->assertSameInt(6912, $s->sounds->output[1]);
        $this->assertSameInt(6912, $s->sounds->output[158]);
        $this->assertSameInt(0, $s->sounds->output[160]);
        $s->sounds->mix();
        $this->assertSameInt(0, $s->sounds->output[0]);
        int $first = $s->sounds->offsets[Sounds::PISTOL];
        $s->wad->data[$first] = 0;
        $s->sounds->play(Sounds::PISTOL);
        $s->sounds->mix();
        $this->assertSameInt(53248, $s->sounds->output[0]);
    }

    public function testPanAndSaturationAreBounded(): void {
        $s = $this->boot();
        for ($c = 0; $c < 12; $c++) {
            $s->sounds->play(Sounds::SHOTGUN, 256, -128);
        }
        $s->sounds->mix();
        $this->assertSameInt(32767, $s->sounds->output[0]);
        $this->assertSameInt(0, $s->sounds->output[1]);
        $this->assertSameInt(8, buffer_len($s->sounds->channels));
    }

    public function testMissingAndTruncatedSoundsAreSilent(): void {
        $s = $this->boot();
        $s->sounds->play(Sounds::PAIN);
        $s->sounds->play(Sounds::FIREBALL);
        $s->sounds->play(-1);
        $s->sounds->mix();
        $this->assertSameInt(0, $s->sounds->played);
        $this->assertSameInt(0, $s->sounds->output[0]);
    }
}
