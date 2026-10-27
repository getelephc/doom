<?php

namespace Showcases\Doom\Play;

use Showcases\Doom\Audio\Sounds;
use Showcases\Doom\Graphics\Sprites;
use Showcases\Doom\Support\Trig;
use Showcases\Doom\Wad\Wad;

// Combat owns a private deterministic random stream and resolves every hit once.
// Monster decisions and projectile updates are separate from damage and spawning.
final class Combat {
    public Things $things;
    public Player $player;
    public Collision $collision;
    public CombatTrace $trace;
    public Specials $specials;
    public Trig $trig;
    public Sounds $sounds;
    public MonsterAI $monsters;
    public Projectiles $projectiles;
    public MonsterRules $rules;
    public int $skill = 3;
    public bool $bossTriggered = false;
    public int $clock = 0;
    public buffer<int> $frames;
    public int $seed;
    public int $kills;
    public int $totalKills;
    public int $items;
    public int $totalItems;
    public int $secrets;
    public int $totalSecrets;
    public buffer<int> $visitedSecrets;
    public buffer<int> $heard;
    public buffer<int> $hearingQueue;
    public int $noiseStamp;

    public function __construct(Things $things, Player $player, Collision $collision, Specials $specials, Trig $trig, Sounds $sounds, Sprites $sprites, Wad $wad) {
        $this->things = $things;
        $this->player = $player;
        $this->collision = $collision;
        $this->trace = new CombatTrace($collision);
        $this->specials = $specials;
        $this->trig = $trig;
        $this->sounds = $sounds;
        $this->monsters = new MonsterAI();
        $this->projectiles = new Projectiles();
        $this->rules = new MonsterRules();
        $this->seed = 1;
        $this->kills = 0;
        $this->totalKills = 0;
        $this->items = 0;
        $this->totalItems = 0;
        $this->secrets = 0;
        $this->totalSecrets = 0;
        int $size = $things->types->count * 26;
        $this->frames = buffer_new<int>($size);
        for ($k = 0; $k < $things->types->count; $k++) {
            int $key = $wad->key($things->types->spriteName($k));
            for ($f = 0; $f < 26; $f++) {
                int $slot = $k * 26 + $f;
                $this->frames[$slot] = $sprites->find($key, $f);
            }
        }
        $this->visitedSecrets = buffer_new<int>($collision->map->sectorCount);
        $this->heard = buffer_new<int>($collision->map->sectorCount);
        $this->hearingQueue = buffer_new<int>($collision->map->sectorCount * 2);
        $this->noiseStamp = 0;
        for ($s = 0; $s < $collision->map->sectorCount; $s++) {
            if ($collision->map->sectors[$s]->special === 9) {
                $this->totalSecrets++;
            }
        }
        for ($i = 0; $i < $things->count; $i++) {
            int $type = $things->mobjs[$i]->type;
            int $health = $this->spawnHealth($type);
            $things->mobjs[$i]->health = $health;
            if ($health > 0 && $type !== 2035 && $type !== 3006) {
                $this->totalKills++;
            }
            if ($this->countedItem($type)) {
                $this->totalItems++;
            }
        }
    }

    public function release(): void {
        buffer<int> $frames = $this->frames;
        buffer_free($frames);
        buffer<int> $secrets = $this->visitedSecrets;
        buffer_free($secrets);
        buffer<int> $heard = $this->heard;
        buffer_free($heard);
        buffer<int> $queue = $this->hearingQueue;
        buffer_free($queue);
    }

    public function random(): int {
        int $next = $this->seed * 1664525 + 1013904223;
        $this->seed = $next & 4294967295;
        return ($this->seed >> 16) & 255;
    }

    public function spawnHealth(int $type): int { return $this->rules->health($type); }

    public function countedItem(int $type): bool {
        return $this->things->types->countedItem($type);
    }

    public function frame(int $index, int $letter, int $bright = 0): void {
        int $kind = $this->things->mobjs[$index]->kind;
        int $slot = $kind * 26 + $letter;
        int $definition = $this->frames[$slot];
        if ($definition >= 0) {
            $this->things->mobjs[$index]->first_definition = $definition;
        }
        $this->things->mobjs[$index]->bright = $bright;
    }

    public function soundAt(int $sound, int $x, int $y): void {
        int $dx = $x - $this->player->unitX();
        int $dy = $y - $this->player->unitY();
        int $distance = max(abs($dx), abs($dy));
        int $gain = 192;
        if ($distance > 160) {
            $gain = max(0, intdiv((1200 - $distance) * 192, 1040));
        }
        int $side = $dx * $this->trig->sin($this->player->angle) - $dy * $this->trig->cos($this->player->angle);
        int $pan = 0;
        if ($distance > 0) {
            $pan = max(-96, min(96, intdiv($side, $distance * 1024)));
        }
        $this->sounds->play($sound, $gain, $pan);
    }

    // Sound passes one sound-blocking line, but never two or a closed door.
    // A sector can be revisited through a route with fewer blockers.
    public function noise(): void {
        int $sector = $this->collision->sectorAt($this->player->unitX(), $this->player->unitY());
        if ($sector < 0) {
            return;
        }
        $this->noiseStamp++;
        int $stamp = $this->noiseStamp;
        $this->heard[$sector] = $stamp * 2;
        $this->hearingQueue[0] = $sector * 2;
        int $tail = 1;
        $map = $this->collision->map;
        for ($head = 0; $head < $tail; $head++) {
            int $entry = $this->hearingQueue[$head];
            int $current = $entry >> 1;
            int $cost = $entry & 1;
            for ($l = 0; $l < $map->linedefCount; $l++) {
                int $front = $map->linedefs[$l]->front_sector;
                int $back = $map->linedefs[$l]->back_sector;
                if ($front < 0 || $back < 0) {
                    continue;
                }
                int $next = -1;
                if ($front === $current) {
                    $next = $back;
                } else if ($back === $current) {
                    $next = $front;
                }
                int $nextCost = $cost + (($map->linedefs[$l]->flags & 64) !== 0 ? 1 : 0);
                if ($next < 0 || $nextCost > 1 || (intdiv($this->heard[$next], 2) === $stamp && ($this->heard[$next] & 1) <= $nextCost)) {
                    continue;
                }
                int $bottom = max($map->sectors[$front]->floor_height, $map->sectors[$back]->floor_height);
                int $top = min($map->sectors[$front]->ceiling_height, $map->sectors[$back]->ceiling_height);
                if ($top <= $bottom) {
                    continue;
                }
                $this->heard[$next] = $stamp * 2 + $nextCost;
                $this->hearingQueue[$tail] = $next * 2 + $nextCost;
                $tail++;
            }
        }
        for ($i = 0; $i < $this->things->count; $i++) {
            if ($this->things->mobjs[$i]->health <= 0 || $this->things->mobjs[$i]->type === 2035) {
                continue;
            }
            if (($this->things->mobjs[$i]->spawnFlags & 8) !== 0) {
                continue;
            }
            int $where = $this->things->mobjs[$i]->sector;
            if ($where >= 0 && intdiv($this->heard[$where], 2) === $stamp) {
                $this->things->mobjs[$i]->target = -1;
                if ($this->things->mobjs[$i]->state === 0) {
                    $this->things->mobjs[$i]->state = 1;
                }
            }
        }
    }

    public function playerShot(int $angle, int $damage, int $range): int {
        int $dx = ($this->trig->cos($angle) * $range) >> 16;
        int $dy = ($this->trig->sin($angle) * $range) >> 16;
        int $z = $this->player->unitZ() + 32;
        int $hit = $this->trace->aim($this->player->unitX(), $this->player->unitY(), $z, $dx, $dy, -1);
        int $limit = $this->trace->wallFraction($this->player->unitX(), $this->player->unitY(), $z, $dx, $dy, 0);
        if ($hit >= 0) {
            $limit = min($limit, $this->trace->boxFraction($this->player->unitX(), $this->player->unitY(), $dx, $dy, $this->things->mobjs[$hit]->x, $this->things->mobjs[$hit]->y, $this->things->mobjs[$hit]->radius));
            $this->hurt($hit, $damage, -1);
        }
        for ($line = 0; $line < $this->collision->map->linedefCount; $line++) {
            int $special = $this->collision->map->linedefs[$line]->special;
            if ($special !== 24 && $special !== 46 && $special !== 47) { continue; }
            int $t = $this->collision->traceHit($line, $this->player->unitX(), $this->player->unitY(), $dx, $dy);
            if ($t > 0 && $t <= $limit) { $this->specials->shootLine($line); }
        }
        return $hit;
    }

    public function hurt(int $index, int $damage, int $source): void {
        $mobjs = $this->things->mobjs;
        if ($index < 0 || $index >= $this->things->count || $mobjs[$index]->health <= 0) {
            return;
        }
        int $remaining = $mobjs[$index]->health - $damage;
        $mobjs[$index]->health = $remaining;
        if ($remaining <= 0) {
            $mobjs[$index]->health = -1;
            $mobjs[$index]->solid = 0;
            $mobjs[$index]->state = 4;
            $mobjs[$index]->stateAge = 0;
            $mobjs[$index]->stateTics = 0;
            if ($mobjs[$index]->type === 2035) {
                $mobjs[$index]->state = 7;
                $mobjs[$index]->target = $source;
                $mobjs[$index]->kind = $this->things->types->find(6001);
                $this->frame($index, 0, 1);
                $this->soundAt(Sounds::BARREL, $mobjs[$index]->x, $mobjs[$index]->y);
            } else {
                if ($mobjs[$index]->type !== 3006) { $this->kills++; }
                $this->bossDeath($mobjs[$index]->type);
                int $firstDeath = $this->rules->deathFirst($mobjs[$index]->type);
                $this->frame($index, $firstDeath);
                int $sound = $this->rules->deathSound($mobjs[$index]->type);
                $this->soundAt($sound, $mobjs[$index]->x, $mobjs[$index]->y);
                if ($mobjs[$index]->type === 3004) {
                    $this->drop($index, 2007);
                } else if ($mobjs[$index]->type === 9) {
                    $this->drop($index, 2001);
                }
            }
            return;
        }
        if ($mobjs[$index]->type !== 2035) {
            $mobjs[$index]->target = $source;
            $mobjs[$index]->state = 3;
            $mobjs[$index]->stateTics = 6;
            int $pain = $this->rules->pain($mobjs[$index]->type);
            $this->frame($index, $pain);
            int $sound = ($mobjs[$index]->type === 3004 || $mobjs[$index]->type === 9) ? Sounds::PAIN : Sounds::MONSTER_PAIN;
            $this->soundAt($sound, $mobjs[$index]->x, $mobjs[$index]->y);
        }
    }

    public function drop(int $source, int $type): void {
        int $slot = $this->things->freeSlot();
        int $kind = $this->things->types->find($type);
        if ($slot < 0 || $kind < 0) {
            return;
        }
        $mobjs = $this->things->mobjs;
        $mobjs[$slot]->active = 1;
        $mobjs[$slot]->x = $mobjs[$source]->x;
        $mobjs[$slot]->y = $mobjs[$source]->y;
        $mobjs[$slot]->z = $mobjs[$source]->z;
        $mobjs[$slot]->sector = $mobjs[$source]->sector;
        $mobjs[$slot]->type = $type;
        $mobjs[$slot]->kind = $kind;
        $mobjs[$slot]->health = 0;
        $mobjs[$slot]->state = 0;
        $mobjs[$slot]->solid = 0;
        $mobjs[$slot]->angle = 0;
        $mobjs[$slot]->radius = 20;
        $mobjs[$slot]->height = 16;
        $mobjs[$slot]->frames = 1;
        $mobjs[$slot]->tics = 0;
        $mobjs[$slot]->bright = 0;
        $mobjs[$slot]->spawnFlags = 256;
        $this->frame($slot, 0);
        int $at = $slot * 4;
        $this->things->frameDefinition[$at] = $mobjs[$slot]->first_definition;
    }

    public function blast(int $source): void {
        $m = $this->things->mobjs;
        $this->radiusDamage($m[$source]->x, $m[$source]->y, $m[$source]->z + 16, 128, $m[$source]->target);
    }

    public function radiusDamage(int $x, int $y, int $z, int $radius, int $owner): void {
        $m = $this->things->mobjs;
        for ($i = 0; $i < $this->things->count; $i++) {
            if ($m[$i]->active === 0 || $m[$i]->health <= 0 || $m[$i]->type === 16 || $m[$i]->type === 7) { continue; }
            int $distance = max(abs($m[$i]->x - $x), abs($m[$i]->y - $y)) - $m[$i]->radius;
            int $damage = $radius - max(0, $distance);
            if ($damage > 0 && $this->trace->visible($x, $y, $z, $m[$i]->x, $m[$i]->y, $m[$i]->z + 16)) { $this->hurt($i, $damage, $owner); }
        }
        int $distance = max(abs($this->player->unitX() - $x), abs($this->player->unitY() - $y)) - 16;
        int $damage = $radius - max(0, $distance);
        if ($damage > 0 && $this->trace->visible($x, $y, $z, $this->player->unitX(), $this->player->unitY(), $this->player->unitZ() + 32)) { $this->player->damage($damage); }
    }

    // Boss actions run only once, after the last matching living boss dies.
    public function bossDeath(int $type): void {
        if ($this->bossTriggered) { return; }
        string $map = $this->collision->map->mapName;
        bool $barons = ($map === "E1M8" && $type === 3003) || ($map === "E4M8" && $type === 7);
        bool $end = ($map === "E2M8" && $type === 16) || ($map === "E3M8" && $type === 7);
        bool $door = $map === "E4M6" && $type === 16;
        if (!$barons && !$end && !$door) { return; }
        for ($i = 0; $i < $this->things->count; $i++) {
            if ($this->things->mobjs[$i]->type === $type && $this->things->mobjs[$i]->health > 0 && $this->things->mobjs[$i]->active !== 0) { return; }
        }
        $this->bossTriggered = true;
        if ($end) { $this->specials->exitRequest = 3; }
        else {
            for ($s = 0; $s < $this->collision->map->sectorCount; $s++) {
                if ($this->collision->map->sectors[$s]->tag !== 666) { continue; }
                if ($door) { $this->specials->startDoor($s, $this->specials->doorOpen); }
                else { $this->specials->startFloor($s, $this->specials->lowestFloorAround($s), 1, -1); }
            }
        }
    }

    public function crushingDamage(): void {
        $map = $this->collision->map;
        for ($sector = 0; $sector < $map->sectorCount; $sector++) {
            int $marker = $map->sectors[$sector]->mover;
            if ($marker <= 0 || $this->specials->movers[$marker - 1]->crush === 0) { continue; }
            int $room = $map->sectors[$sector]->ceiling_height - $map->sectors[$sector]->floor_height;
            if ($room < 56 && $this->collision->sectorAt($this->player->unitX(), $this->player->unitY()) === $sector) { $this->player->damage(10); }
            for ($i = 0; $i < $this->things->count; $i++) {
                if ($this->things->mobjs[$i]->active !== 0 && $this->things->mobjs[$i]->sector === $sector && $this->things->mobjs[$i]->health > 0 && $room < $this->things->mobjs[$i]->height) { $this->hurt($i, 10, -1); }
            }
        }
    }

    public function tick(): void {
        $this->clock++;
        if (($this->clock & 3) === 0) { $this->crushingDamage(); }
        for ($i = 0; $i < $this->things->count; $i++) {
            if ($this->things->mobjs[$i]->active === 0) {
                continue;
            }
            int $state = $this->things->mobjs[$i]->state;
            if ($state === 6 || $state === 7) {
                $this->projectiles->tick($this, $i);
            } else if ($this->things->mobjs[$i]->type === 2035) {
                $this->things->mobjs[$i]->stateAge++;
                $this->frame($i, intdiv($this->things->mobjs[$i]->stateAge, 6) % 2);
            } else if ($this->things->mobjs[$i]->health !== 0 && $this->things->mobjs[$i]->type !== 2035) {
                $this->monsters->tick($this, $i);
            }
        }
        int $sector = $this->collision->sectorAt($this->player->unitX(), $this->player->unitY());
        if ($sector >= 0 && $this->collision->map->sectors[$sector]->special === 9 && $this->visitedSecrets[$sector] === 0
            && $this->player->unitZ() === $this->collision->map->sectors[$sector]->floor_height) {
            $this->visitedSecrets[$sector] = 1;
            $this->secrets++;
            $this->player->message = "A secret is revealed!";
        }
    }
}
