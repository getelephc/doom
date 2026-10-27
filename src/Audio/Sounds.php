<?php

namespace Showcases\Doom\Audio;

use Showcases\Doom\Data\SoundChannel;
use Showcases\Doom\Wad\Wad;

// Bounded stereo mixer, independent of SDL. WAD DMX samples are unsigned 8-bit
// mono; output is 22050 Hz signed 16-bit little-endian stereo, 630 frames per tic.
final class Sounds {
    public const PISTOL = 0;
    public const SHOTGUN = 1;
    public const COCK = 2;
    public const PUNCH = 3;
    public const PICKUP = 4;
    public const WEAPON = 5;
    public const SIGHT = 6;
    public const IMP_SIGHT = 7;
    public const PAIN = 8;
    public const DEATH = 9;
    public const IMP_DEATH = 10;
    public const FIREBALL = 11;
    public const EXPLODE = 12;
    public const BARREL = 13;
    public const PLAYER_PAIN = 14;
    public const PLAYER_DEATH = 15;
    public const DOOR = 16;
    public const SWITCH = 17;
    public const ROCKET = 18;
    public const PLASMA = 19;
    public const BFG = 20;
    public const SAW_IDLE = 21;
    public const SAW_FULL = 22;
    public const SAW_HIT = 23;
    public const CACO_SIGHT = 24;
    public const CACO_DEATH = 25;
    public const DEMON_SIGHT = 26;
    public const DEMON_DEATH = 27;
    public const BARON_SIGHT = 28;
    public const BARON_DEATH = 29;
    public const CYBER_SIGHT = 30;
    public const CYBER_DEATH = 31;
    public const SPIDER_SIGHT = 32;
    public const SPIDER_DEATH = 33;
    public const SKULL_ATTACK = 34;
    public const SKULL_DEATH = 35;
    public const MONSTER_PAIN = 36;
    public const TELEPORT = 37;
    public const STONE = 38;
    public const STOP = 39;
    public const COUNT = 40;
    public Wad $wad;
    public Music $music;
    public int $volume = 192;
    public buffer<int> $offsets;
    public buffer<int> $lengths;
    public buffer<int> $steps;
    public buffer<SoundChannel> $channels;
    public buffer<u16> $output;
    public int $serial;
    public int $played;

    public function __construct(Wad $wad) {
        $this->wad = $wad;
        $this->music = new Music($wad);
        $this->offsets = buffer_new<int>(40);
        $this->lengths = buffer_new<int>(40);
        $this->steps = buffer_new<int>(40);
        $this->channels = buffer_new<SoundChannel>(8);
        $this->output = buffer_new<u16>(1260);
        $this->serial = 0;
        $this->played = 0;
    }

    public function load(): void {
        $names = ["DSPISTOL", "DSSHOTGN", "DSSGCOCK", "DSPUNCH", "DSITEMUP", "DSWPNUP", "DSPOSIT1", "DSBGSIT1", "DSPOPAIN", "DSPODTH1", "DSBGDTH1", "DSFIRSHT", "DSFIRXPL", "DSBAREXP", "DSPLPAIN", "DSPLDETH", "DSDOROPN", "DSSWTCHN", "DSRLAUNC", "DSPLASMA", "DSBFG", "DSSAWIDL", "DSSAWFUL", "DSSAWHIT", "DSCACSIT", "DSCACDTH", "DSSGTSIT", "DSSGTDTH", "DSBRSSIT", "DSBRSDTH", "DSCYBSIT", "DSCYBDTH", "DSSPISIT", "DSSPIDTH", "DSSKLATK", "DSSKLDTH", "DSDMPAIN", "DSTELEPT", "DSSTNMOV", "DSPSTOP"];
        for ($i = 0; $i < 40; $i++) {
            int $lump = $this->wad->find((string)$names[$i]);
            if ($lump < 0 || $this->wad->lumpSize[$lump] < 8) {
                continue;
            }
            int $at = $this->wad->lumpOffset[$lump];
            int $size = $this->wad->lumpSize[$lump];
            if ($at < 0 || $at + $size > $this->wad->size || $this->wad->u16($at) !== 3) {
                continue;
            }
            int $rate = $this->wad->u16($at + 2);
            int $length = $this->wad->i32($at + 4);
            if ($rate <= 0 || $length <= 0 || $length > $size - 8) {
                continue;
            }
            $this->offsets[$i] = $at + 8;
            $this->lengths[$i] = $length;
            $this->steps[$i] = intdiv($rate * 65536, 22050);
        }
    }

    // Gain and pan are 0..256 and -128..128. Steal the oldest voice when full.
    public function play(int $sound, int $gain = 192, int $pan = 0): void {
        if ($sound < 0 || $sound >= 40 || $this->lengths[$sound] === 0 || $gain <= 0) {
            return;
        }
        int $slot = 0;
        int $oldest = $this->serial + 1;
        for ($c = 0; $c < 8; $c++) {
            if ($this->channels[$c]->length === 0) {
                $slot = $c;
                break;
            }
            if ($this->channels[$c]->serial < $oldest) {
                $oldest = $this->channels[$c]->serial;
                $slot = $c;
            }
        }
        int $volume = min(256, intdiv($gain * $this->volume, 192));
        int $balance = max(-128, min(128, $pan));
        $this->serial++;
        $this->played++;
        $this->channels[$slot]->offset = $this->offsets[$sound];
        $this->channels[$slot]->length = $this->lengths[$sound];
        $this->channels[$slot]->step = $this->steps[$sound];
        $this->channels[$slot]->position = 0;
        $this->channels[$slot]->left = intdiv($volume * (128 - $balance), 256);
        $this->channels[$slot]->right = intdiv($volume * (128 + $balance), 256);
        $this->channels[$slot]->serial = $this->serial;
    }

    public function stop(): void {
        for ($c = 0; $c < 8; $c++) {
            $this->channels[$c]->length = 0;
        }
    }

    public function mix(): void {
        $this->music->mix();
        buffer<SoundChannel> $channels = $this->channels;
        buffer<u8> $data = $this->wad->data;
        buffer<u16> $output = $this->output;
        for ($frame = 0; $frame < 630; $frame++) {
            int $left = $this->music->pcm[$frame * 2];
            int $right = $this->music->pcm[$frame * 2 + 1];
            for ($c = 0; $c < 8; $c++) {
                int $sample = $channels[$c]->position >> 16;
                if ($sample >= $channels[$c]->length) {
                    $channels[$c]->length = 0;
                    continue;
                }
                int $at = $channels[$c]->offset + $sample;
                int $value = $data[$at] - 128;
                int $l = $value * $channels[$c]->left;
                int $r = $value * $channels[$c]->right;
                int $sumL = $left + $l;
                int $sumR = $right + $r;
                $left = $sumL;
                $right = $sumR;
                int $next = $channels[$c]->position + $channels[$c]->step;
                $channels[$c]->position = $next;
            }
            int $lClamped = max(-32768, min(32767, $left));
            int $rClamped = max(-32768, min(32767, $right));
            int $at = $frame * 2;
            int $next = $at + 1;
            $output[$at] = $lClamped & 65535;
            $output[$next] = $rClamped & 65535;
        }
    }
}
