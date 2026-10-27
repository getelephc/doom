<?php

namespace Showcases\Doom\Play;

// Public DOOM linedef action catalog. Trigger modes: use=1, cross=2, shoot=3.
final class LineActions {
    public buffer<int> $trigger;
    public buffer<int> $repeat;
    public buffer<int> $action;
    public buffer<int> $speed;

    public function __construct() {
        $this->trigger = buffer_new<int>(142); $this->repeat = buffer_new<int>(142);
        $this->action = buffer_new<int>(142); $this->speed = buffer_new<int>(142);
        $groups = [
            [[4],2,0,1,2], [[29],1,0,1,2], [[90],2,1,1,2], [[63],1,1,1,2],
            [[2],2,0,2,2], [[103],1,0,2,2], [[86],2,1,2,2], [[61],1,1,2,2],
            [[3],2,0,3,2], [[50],1,0,3,2], [[75],2,1,3,2], [[42],1,1,3,2], [[16],2,0,4,2], [[76],2,1,4,2],
            [[108],2,0,1,8], [[111],1,0,1,8], [[105],2,1,1,8], [[114],1,1,1,8],
            [[109],2,0,2,8], [[112,133,135,137],1,0,2,8], [[106],2,1,2,8], [[115,99,134,136],1,1,2,8],
            [[110],2,0,3,8], [[113],1,0,3,8], [[107],2,1,3,8], [[116],1,1,3,8],
            [[10],2,0,10,4], [[21],1,0,10,4], [[88],2,1,10,4], [[62],1,1,10,4],
            [[121],2,0,10,8], [[122],1,0,10,8], [[120],2,1,10,8], [[123],1,1,10,8],
            [[38],2,0,20,1], [[23],1,0,20,1], [[82],2,1,20,1], [[60],1,1,20,1],
            [[36],2,0,21,4], [[71],1,0,21,4], [[98],2,1,21,4], [[70],1,1,21,4],
            [[5],2,0,22,1], [[101],1,0,22,1], [[91],2,1,22,1], [[64],1,1,22,1], [[24],3,0,22,1],
            [[119],2,0,23,1], [[18],1,0,23,1], [[128],2,1,23,1], [[69],1,1,23,1],
            [[130],2,0,23,4], [[131],1,0,23,4], [[129],2,1,23,4], [[132],1,1,23,4],
            [[22],2,0,24,1], [[20],1,0,24,1], [[95],2,1,24,1], [[68],1,1,24,1], [[47],3,0,24,1],
            [[8],2,0,30,1], [[7],1,0,30,1], [[100],2,0,31,4], [[127],1,0,31,4],
            [[40],2,0,40,1], [[41],1,0,41,1], [[43],1,1,41,1], [[44],2,0,42,1], [[49],1,0,44,1], [[72],2,1,42,1],
            [[6],2,0,43,2], [[25,141],2,0,44,1], [[77],2,1,43,2], [[73],2,1,44,1], [[57],2,0,45,1], [[74],2,1,45,1],
            [[53],2,0,46,1], [[87],2,1,46,1], [[54],2,0,47,1], [[89],2,1,47,1],
            [[58],2,0,50,1], [[92],2,1,50,1], [[15],1,0,51,1], [[66],1,1,51,1], [[59],2,0,51,1], [[93],2,1,51,1],
            [[14],1,0,52,1], [[67],1,1,52,1], [[140],1,0,53,4], [[30],2,0,54,1], [[96],2,1,54,1],
            [[19],2,0,55,1], [[102],1,0,55,1], [[83],2,1,55,1], [[45],1,1,55,1], [[37],2,0,56,1], [[84],2,1,56,1],
            [[56],2,0,57,1], [[55],1,0,57,1], [[94],2,1,57,1], [[65],1,1,57,1], [[9],1,0,58,1],
            [[35],2,0,65,1], [[79],2,1,65,1], [[139],1,1,60,1], [[12],2,0,61,1], [[80],2,1,61,1],
            [[13],2,0,62,1], [[81],2,1,62,1], [[138],1,1,62,1], [[104],2,0,63,1], [[17],2,0,64,1]
        ];
        foreach ($groups as $raw) {
            array $row = (array)$raw;
            foreach ((array)$row[0] as $number) {
                int $n = (int)$number;
                $this->trigger[$n] = (int)$row[1]; $this->repeat[$n] = (int)$row[2];
                $this->action[$n] = (int)$row[3]; $this->speed[$n] = (int)$row[4];
            }
        }
    }

    public function run(Specials $s, int $line, int $mode, int $side): bool {
        int $n = $s->map->linedefs[$line]->special;
        if ($n <= 0 || $n > 141 || ($mode === 1 && $side >= 0)) { return false; }
        if ($mode === 1 && ($n === 1 || ($n >= 26 && $n <= 28) || ($n >= 31 && $n <= 34) || $n === 117 || $n === 118)) {
            int $type = $n === 31 || $n === 32 || $n === 33 || $n === 34 || $n === 118 ? $s->doorOpen : $s->doorNormal;
            bool $ok = $s->manualDoor($line, $type, $n);
            int $sector = $s->map->linedefs[$line]->back_sector;
            if ($ok && $n >= 117 && $sector >= 0 && $s->map->sectors[$sector]->mover > 0) { $s->movers[$s->map->sectors[$sector]->mover - 1]->speed = 8; }
            if ($ok && $type === $s->doorOpen) { $s->map->linedefs[$line]->special = 0; }
            return $ok;
        }
        if ($mode === 3 && $n === 46) { return $s->manualDoor($line, $s->doorOpen, 1); }
        if (($mode === 1 && ($n === 11 || $n === 51)) || ($mode === 2 && ($n === 52 || $n === 124))) {
            $s->exitRequest = $n === 51 || $n === 124 ? 2 : 1;
            if ($mode === 1) { $s->pressSwitch($line, false); }
            return true;
        }
        if ($mode === 2 && ($n === 39 || $n === 97)) {
            if ($side >= 0) { return false; }
            $s->teleport($line);
            if ($s->teleportPending && $n === 39) { $s->map->linedefs[$line]->special = 0; }
            return $s->teleportPending;
        }
        if ($this->trigger[$n] !== $mode || !$s->hasKeyFor($n)) { return false; }
        int $tag = $s->map->linedefs[$line]->tag;
        if ($tag === 0) { return false; }
        bool $started = false;
        for ($sector = 0; $sector < $s->map->sectorCount; $sector++) {
            if ($s->map->sectors[$sector]->tag === $tag && $s->advanced->start($s, $line, $sector, $this->action[$n], $this->speed[$n])) { $started = true; }
        }
        if ($started) {
            if ($mode === 1 || $mode === 3) { $s->pressSwitch($line, $this->repeat[$n] !== 0); }
            if ($this->repeat[$n] === 0) { $s->map->linedefs[$line]->special = 0; }
        }
        return $started;
    }
}
