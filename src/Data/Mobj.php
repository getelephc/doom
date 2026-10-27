<?php

namespace Showcases\Doom\Data;

// A spawned map object: position, look (sprite frames cycled every $tics) and size.
// $active drops to 0 when the player picks it up.
packed class Mobj {
    public int $active;
    public int $x;
    public int $y;
    public int $z;
    public int $angle;
    public int $type;
    public int $kind;
    public int $sector;
    public int $first_definition;
    public int $frames;
    public int $tics;
    public int $phase;
    public int $bright;
    public int $radius;
    public int $height;
    public int $solid;
    // Combat state: 0 idle, 1 chase, 2 attack windup, 3 pain, 4 dying, 5 corpse,
    // 6 flying projectile, 7 exploding. Noncombat decorations keep health at 0.
    public int $health;
    public int $state;
    public int $stateTics;
    public int $stateAge;
    public int $target;
    public int $cooldown;
    public int $spawnFlags;
    public int $velX;
    public int $velY;
    public int $velZ;
    public int $fixedX;
    public int $fixedY;
    public int $fixedZ;
    public int $hitDamage;
    public int $splashDamage;
    public int $spawnX;
    public int $spawnY;
    public int $spawnZ;
    public int $spawnAngle;
}
