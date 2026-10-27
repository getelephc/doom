<?php

namespace Showcases\Doom\Play;

use Showcases\Doom\Audio\Sounds;

// Original DOOM's ten monster families, keyed by the public THINGS numbers.
final class MonsterRules {
    public function sightSound(int $type): int {
        if ($type === 3001) { return Sounds::IMP_SIGHT; }
        if ($type === 3002 || $type === 58 || $type === 3006) { return Sounds::DEMON_SIGHT; }
        if ($type === 3005) { return Sounds::CACO_SIGHT; }
        if ($type === 3003) { return Sounds::BARON_SIGHT; }
        if ($type === 16) { return Sounds::CYBER_SIGHT; }
        if ($type === 7) { return Sounds::SPIDER_SIGHT; }
        return Sounds::SIGHT;
    }
    public function deathSound(int $type): int {
        if ($type === 3001) { return Sounds::IMP_DEATH; }
        if ($type === 3002 || $type === 58) { return Sounds::DEMON_DEATH; }
        if ($type === 3006) { return Sounds::SKULL_DEATH; }
        if ($type === 3005) { return Sounds::CACO_DEATH; }
        if ($type === 3003) { return Sounds::BARON_DEATH; }
        if ($type === 16) { return Sounds::CYBER_DEATH; }
        if ($type === 7) { return Sounds::SPIDER_DEATH; }
        return Sounds::DEATH;
    }
    public function health(int $type): int {
        if ($type === 3004 || $type === 2035) { return 20; }
        if ($type === 9) { return 30; }
        if ($type === 3001) { return 60; }
        if ($type === 3002 || $type === 58) { return 150; }
        if ($type === 3006) { return 100; }
        if ($type === 3005) { return 400; }
        if ($type === 3003) { return 1000; }
        if ($type === 16) { return 4000; }
        if ($type === 7) { return 3000; }
        return 0;
    }
    public function speed(int $type): int {
        if ($type === 3002 || $type === 58) { return 10; }
        if ($type === 16) { return 16; }
        if ($type === 7) { return 12; }
        return 8;
    }
    public function flying(int $type): bool { return $type === 3005 || $type === 3006; }
    public function meleeOnly(int $type): bool { return $type === 3002 || $type === 58; }
    public function deathFirst(int $type): int {
        if ($type === 3001 || $type === 3002 || $type === 58 || $type === 3003) { return 8; }
        if ($type === 3006) { return 5; }
        if ($type === 3005) { return 6; }
        if ($type === 7) { return 9; }
        return 7;
    }
    public function deathFrames(int $type): int {
        if ($type === 3002 || $type === 58 || $type === 3005 || $type === 3006) { return 6; }
        if ($type === 3003) { return 7; }
        if ($type === 16) { return 9; }
        if ($type === 7) { return 10; }
        return 5;
    }
    public function pain(int $type): int {
        if ($type === 3001 || $type === 3002 || $type === 58 || $type === 3003) { return 7; }
        if ($type === 3005 || $type === 3006) { return 4; }
        if ($type === 7) { return 8; }
        return 6;
    }
    public function attackFrame(int $type): int {
        if ($type === 3005) { return 1; }
        if ($type === 3006) { return 2; }
        if ($type === 7) { return 6; }
        return 4;
    }
}
