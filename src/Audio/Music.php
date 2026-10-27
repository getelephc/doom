<?php

namespace Showcases\Doom\Audio;

use Showcases\Doom\Data\MusicEvent;
use Showcases\Doom\Data\MusicVoice;
use Showcases\Doom\Wad\Wad;

// MUS playback at its native 140 Hz event clock, with an independent polyphonic
// wavetable synthesizer. It preserves notes, timing and controllers, not OPL timbre.
final class Music {
    public Wad $wad;
    public buffer<MusicEvent> $events;
    public buffer<MusicVoice> $voices;
    public buffer<int> $waves;
    public buffer<int> $frequencies;
    public buffer<int> $pcm;
    public buffer<int> $volumes;
    public buffer<int> $programs;
    public buffer<int> $pans;
    public buffer<int> $sustain;
    public buffer<int> $bends;
    public int $count = 0;
    public int $event = 0;
    public int $cursor = 0;
    public int $duration = 0;
    public int $volume = 96;
    public int $serial = 0;
    public string $name = "";
    public bool $enabled = true;

    public function __construct(Wad $wad) {
        $this->wad = $wad;
        $this->events = buffer_new<MusicEvent>(1);
        $this->voices = buffer_new<MusicVoice>(32);
        $this->waves = buffer_new<int>(8192);
        $this->frequencies = buffer_new<int>(128);
        $this->pcm = buffer_new<int>(1260);
        $this->volumes = buffer_new<int>(16);
        $this->programs = buffer_new<int>(16);
        $this->pans = buffer_new<int>(16);
        $this->sustain = buffer_new<int>(16);
        $this->bends = buffer_new<int>(16);
        for ($i = 0; $i < 2048; $i++) {
            $this->waves[$i] = (int)round(sin($i * 2.0 * M_PI / 2048.0) * 32767.0);
            $this->waves[2048 + $i] = 32767 - abs($i * 64 - 65535);
            $this->waves[4096 + $i] = $i * 32 - 32768;
            $this->waves[6144 + $i] = $i < 1024 ? 24000 : -24000;
        }
        for ($n = 0; $n < 128; $n++) {
            float $frequency = 440.0 * pow(2.0, ($n - 69) / 12.0);
            $this->frequencies[$n] = (int)round(min(10000.0, $frequency) * 4294967296.0 / 22050.0);
        }
        $this->resetChannels();
    }

    public function resetChannels(): void {
        for ($c = 0; $c < 16; $c++) {
            $this->volumes[$c] = 100;
            $this->programs[$c] = 0;
            $this->pans[$c] = 64;
            $this->sustain[$c] = 0;
            $this->bends[$c] = 128;
        }
        for ($v = 0; $v < 32; $v++) { $this->voices[$v]->active = 0; }
    }

    public function load(string $name): bool {
        int $lump = $this->wad->find($name);
        if ($lump < 0 || $this->wad->lumpSize[$lump] < 16) { return false; }
        int $at = $this->wad->lumpOffset[$lump];
        int $end = $at + $this->wad->lumpSize[$lump];
        if ($at < 0 || $end > $this->wad->size || $this->wad->u16($at) !== 21837 || $this->wad->u16($at + 2) !== 6739) { return false; }
        int $length = $this->wad->u16($at + 4);
        int $offset = $this->wad->u16($at + 6);
        if ($offset < 16 || $length < 2 || $at + $offset + $length > $end) { return false; }
        buffer<MusicEvent> $decoded = buffer_new<MusicEvent>($length);
        buffer<int> $velocity = buffer_new<int>(16);
        for ($c = 0; $c < 16; $c++) { $velocity[$c] = 64; }
        int $pos = $at + $offset;
        int $limit = $pos + $length;
        int $time = 0;
        int $count = 0;
        bool $ended = false;
        while ($pos < $limit && $count < $length) {
            int $descriptor = $this->wad->data[$pos]; $pos++;
            int $type = ($descriptor >> 4) & 7;
            int $channel = $descriptor & 15;
            int $a = 0; int $b = 0;
            if ($type !== 6) {
                if ($pos >= $limit || $type === 5 || $type === 7) { break; }
                $a = $this->wad->data[$pos]; $pos++;
                if ($type === 1) {
                    if (($a & 128) !== 0) {
                        if ($pos >= $limit) { break; }
                        $velocity[$channel] = $this->wad->data[$pos] & 127; $pos++;
                    }
                    $a &= 127;
                    $b = $velocity[$channel];
                } else if ($type === 4) {
                    if ($pos >= $limit) { break; }
                    $b = $this->wad->data[$pos]; $pos++;
                }
            }
            $decoded[$count]->sample = intdiv($time * 22050, 140);
            $decoded[$count]->type = $type; $decoded[$count]->channel = $channel;
            $decoded[$count]->a = $a; $decoded[$count]->b = $b;
            $count++;
            if ($type === 6) { $ended = true; break; }
            if (($descriptor & 128) !== 0) {
                int $delay = 0; int $bytes = 0;
                do {
                    if ($pos >= $limit || $bytes >= 4) { $pos = $limit; break; }
                    int $byte = $this->wad->data[$pos]; $pos++; $bytes++;
                    $delay = ($delay << 7) | ($byte & 127);
                } while (($byte & 128) !== 0);
                $time += $delay;
            }
        }
        buffer_free($velocity);
        if (!$ended || $time <= 0) { buffer_free($decoded); return false; }
        buffer<MusicEvent> $old = $this->events; buffer_free($old);
        $this->events = $decoded; $this->count = $count;
        $this->duration = intdiv($time * 22050, 140);
        $this->cursor = 0; $this->event = 0; $this->name = $name;
        $this->resetChannels();
        return true;
    }

    public function stop(): void {
        $this->count = 0;
        $this->name = "";
        $this->resetChannels();
    }

    public function dispatch(int $type, int $channel, int $a, int $b): void {
        if ($type === 0) {
            for ($v = 0; $v < 32; $v++) {
                if ($this->voices[$v]->channel === $channel && $this->voices[$v]->note === $a) {
                    $this->voices[$v]->released = $this->sustain[$channel] > 0 ? 2 : 1;
                }
            }
        } else if ($type === 1) {
            int $slot = 0; int $oldest = $this->serial + 1;
            for ($v = 0; $v < 32; $v++) {
                if ($this->voices[$v]->active === 0) { $slot = $v; break; }
                if ($this->voices[$v]->serial < $oldest) { $oldest = $this->voices[$v]->serial; $slot = $v; }
            }
            $this->serial++;
            $this->voices[$slot]->active = 1; $this->voices[$slot]->channel = $channel;
            $this->voices[$slot]->note = $a; $this->voices[$slot]->velocity = $b;
            $this->voices[$slot]->phase = 0; $this->voices[$slot]->step = $this->frequencies[$a & 127];
            $this->voices[$slot]->envelope = $channel === 15 ? 65535 : 0;
            $this->voices[$slot]->released = 0; $this->voices[$slot]->age = 0;
            int $group = $this->programs[$channel] >> 3;
            $this->voices[$slot]->wave = $group < 4 ? 1 : ($group < 8 ? 0 : 2);
            $this->voices[$slot]->serial = $this->serial; $this->voices[$slot]->noise = $a + 1;
        } else if ($type === 2) {
            $this->bends[$channel] = $a;
        } else if ($type === 3) {
            if ($a === 10 || $a === 11 || $a === 12) {
                for ($v = 0; $v < 32; $v++) { if ($this->voices[$v]->channel === $channel) { $this->voices[$v]->released = 1; } }
            }
            if ($a === 14) { $this->volumes[$channel] = 100; $this->pans[$channel] = 64; $this->sustain[$channel] = 0; }
        } else if ($type === 4) {
            if ($a === 0) { $this->programs[$channel] = $b & 127; }
            else if ($a === 3) { $this->volumes[$channel] = $b & 127; }
            else if ($a === 4) { $this->pans[$channel] = $b & 127; }
            else if ($a === 8) {
                $this->sustain[$channel] = $b;
                if ($b < 64) {
                    $this->sustain[$channel] = 0;
                    for ($v = 0; $v < 32; $v++) { if ($this->voices[$v]->channel === $channel && $this->voices[$v]->released === 2) { $this->voices[$v]->released = 1; } }
                }
            }
        }
    }

    public function mix(): void {
        buffer<int> $pcm = $this->pcm;
        buffer<MusicVoice> $voices = $this->voices;
        for ($f = 0; $f < 630; $f++) {
            int $left = 0; int $right = 0;
            if ($this->count > 0 && $this->enabled) {
                int $budget = 256;
                while ($this->event < $this->count && $this->events[$this->event]->sample <= $this->cursor && $budget > 0) {
                    int $e = $this->event; $this->event++; $budget--;
                    if ($this->events[$e]->type === 6) { $this->event = 0; $this->cursor = 0; $this->resetChannels(); }
                    else { $this->dispatch($this->events[$e]->type, $this->events[$e]->channel, $this->events[$e]->a, $this->events[$e]->b); }
                }
                $this->cursor++;
                for ($v = 0; $v < 32; $v++) {
                    if ($voices[$v]->active === 0) { continue; }
                    int $channel = $voices[$v]->channel;
                    int $envelope = $voices[$v]->envelope;
                    if ($voices[$v]->released === 1 || $channel === 15) { $envelope -= max(1, $envelope >> 9); }
                    else if ($voices[$v]->age < 128) { $envelope = min(65535, $envelope + 512); }
                    else if ($voices[$v]->wave === 1) { $envelope = max(10000, $envelope - max(1, $envelope >> 13)); }
                    $voices[$v]->envelope = $envelope; $voices[$v]->age++;
                    if ($envelope < 16 && ($voices[$v]->released !== 0 || $channel === 15)) { $voices[$v]->active = 0; continue; }
                    int $step = $voices[$v]->step;
                    int $bend = $this->bends[$channel] - 128;
                    int $bent = $step + intdiv($step * $bend, 768);
                    int $phase = ($voices[$v]->phase + $bent) & 4294967295;
                    $voices[$v]->phase = $phase;
                    int $sample = $this->waves[$voices[$v]->wave * 2048 + ($phase >> 21)];
                    if ($channel === 15) {
                        int $noise = ($voices[$v]->noise * 1664525 + 1013904223) & 2147483647;
                        $voices[$v]->noise = $noise;
                        $sample = ($noise & 65535) - 32768;
                    }
                    int $value = ($sample * $envelope) >> 16;
                    int $volume = $voices[$v]->velocity * $this->volumes[$channel];
                    int $scaled = intdiv($value * $volume, 16129);
                    int $gain = intdiv($scaled * $this->volume, 2048);
                    int $pan = $this->pans[$channel];
                    $left += intdiv($gain * (127 - $pan), 127);
                    $right += intdiv($gain * $pan, 127);
                }
            }
            $pcm[$f * 2] = $left; $pcm[$f * 2 + 1] = $right;
        }
    }
}
