<?php

namespace Showcases\Doom\Play;

use Showcases\Doom\SDL\Input;
use Showcases\Doom\Support\Trig;

// The player as DOOM moves it, one tic at a time: input adds thrust to momentum,
// momentum moves the player through the collision checks, friction slows it, and
// the view height eases back after steps while bobbing with speed.
// Position, height and momentum are 16.16 fixed point.
final class Player {
    public int $x;
    public int $y;
    public int $z;
    public int $momX;
    public int $momY;
    public int $momZ;
    public int $angle;
    public int $turnHeld;
    public int $viewHeight;
    public int $deltaViewHeight;
    public int $viewZ;
    public int $floorZ;
    public int $ceilingZ;
    public bool $useHeld;
    public int $keys;
    public int $health;
    public int $armor;
    public int $armorType;
    public int $bullets;
    public int $shells;
    public int $weapons;
    public int $weapon;
    public int $weaponAge;
    public int $weaponCooldown;
    public int $damageCount;
    public int $bonusCount;
    public string $message;
    public Inventory $inventory;
    public int $skill = 3;
    public int $rockets;
    public int $cells;
    public int $maxBullets;
    public int $maxShells;
    public int $maxRockets;
    public int $maxCells;
    public bool $backpack;
    public bool $godMode;
    public bool $noclip;
    public bool $berserk;
    public bool $fullMap;
    public int $invulnerable;
    public int $invisible;
    public int $suit;
    public int $lightAmp;
    public int $availableWeapons;
    public int $pendingWeapon;
    public int $selectionHeld;
    public int $sawClock;
    public bool $fireHeld;

    public function __construct() {
        $this->x = 0;
        $this->y = 0;
        $this->z = 0;
        $this->momX = 0;
        $this->momY = 0;
        $this->momZ = 0;
        $this->angle = 0;
        $this->turnHeld = 0;
        $this->viewHeight = 2686976;
        $this->deltaViewHeight = 0;
        $this->viewZ = 0;
        $this->floorZ = 0;
        $this->ceilingZ = 0;
        $this->useHeld = false;
        $this->keys = 0;
        $this->message = "";
        $this->inventory = new Inventory();
        $this->availableWeapons = 6;
        $this->reset();
    }

    // A fresh marine: 100% health, a pistol with 50 bullets.
    public function reset(): void {
        $this->keys = 0;
        $this->health = 100;
        $this->armor = 0;
        $this->armorType = 0;
        $this->bullets = 50;
        $this->shells = 0;
        $this->rockets = 0;
        $this->cells = 0;
        $this->maxBullets = 200;
        $this->maxShells = 50;
        $this->maxRockets = 50;
        $this->maxCells = 300;
        $this->backpack = false;
        $this->godMode = false;
        $this->noclip = false;
        $this->berserk = false;
        $this->fullMap = false;
        $this->invulnerable = 0;
        $this->invisible = 0;
        $this->suit = 0;
        $this->lightAmp = 0;
        $this->weapons = 6;
        $this->weapon = 2;
        $this->weaponAge = 0;
        $this->weaponCooldown = 0;
        $this->pendingWeapon = 0;
        $this->selectionHeld = 0;
        $this->sawClock = 0;
        $this->fireHeld = false;
        $this->damageCount = 0;
        $this->bonusCount = 0;
    }

    // Palette to show: red while hurt, gold right after a pickup.
    public function palette(): int {
        if ($this->damageCount > 0) {
            int $rounded = $this->damageCount + 7;
            int $red = $rounded >> 3;
            if ($red > 8) {
                return 8;
            }
            return $red;
        }
        if ($this->bonusCount > 0) {
            int $rounded = $this->bonusCount + 7;
            int $gold = $rounded >> 3;
            if ($gold > 4) {
                $gold = 4;
            }
            int $palette = 8 + $gold;
            return $palette;
        }
        if ($this->suit > 128 || ($this->suit & 8) !== 0) {
            return 13;
        }
        return 0;
    }

    // Damage with armor soaking a third (green) or half (blue) of it.
    public function damage(int $amount, bool $force = false): void {
        if (!$force && ($this->godMode || $this->invulnerable > 0)) {
            return;
        }
        if ($this->skill === 1) { $amount = max(1, intdiv($amount, 2)); }
        int $absorbed = 0;
        if ($this->armorType === 1) {
            $absorbed = intdiv($amount, 3);
        } else if ($this->armorType === 2) {
            $absorbed = intdiv($amount, 2);
        }
        if ($absorbed > $this->armor) {
            $absorbed = $this->armor;
        }
        int $armorLeft = $this->armor - $absorbed;
        $this->armor = $armorLeft;
        if ($this->armor === 0) {
            $this->armorType = 0;
        }
        int $taken = $amount - $absorbed;
        int $healthLeft = $this->health - $taken;
        $this->health = $healthLeft;
        int $hurt = $this->damageCount + $taken;
        $this->damageCount = $hurt;
        if ($this->damageCount > 100) {
            $this->damageCount = 100;
        }
    }

    // Inventory rules are shared with ordinary pickups and cheat grants.
    public function pickup(int $type, bool $dropped = false): bool {
        return $this->inventory->pickup($this, $type, $dropped);
    }

    public function capped(int $value, int $amount, int $limit): int {
        int $sum = $value + $amount;
        if ($sum > $limit) {
            return $limit;
        }
        return $sum;
    }

    public function spawn(int $x, int $y, int $angle, int $floor): void {
        $this->x = $x << 16;
        $this->y = $y << 16;
        $this->z = $floor << 16;
        $this->momX = 0;
        $this->momY = 0;
        $this->momZ = 0;
        $this->angle = $angle;
        $this->viewHeight = 2686976;
        $this->deltaViewHeight = 0;
        $this->viewZ = $this->z + $this->viewHeight;
    }

    public function unitX(): int {
        return $this->x >> 16;
    }

    public function unitY(): int {
        return $this->y >> 16;
    }

    public function unitZ(): int {
        return $this->z >> 16;
    }

    public function tick(Input $input, ptr $keys, Collision $collision, Specials $specials, Trig $trig, int $levelTime): void {
        $this->invulnerable = max(0, $this->invulnerable - 1);
        $this->invisible = max(0, $this->invisible - 1);
        $this->suit = max(0, $this->suit - 1);
        $this->lightAmp = max(0, $this->lightAmp - 1);
        bool $run = $input->run($keys) !== 0;
        bool $left = $input->turnLeft($keys) !== 0;
        bool $right = $input->turnRight($keys) !== 0;

        // turning: slow for the first 6 tics, then 80 (walking) or 160 (running) fine angles per tic
        if ($left || $right) {
            $this->turnHeld++;
        } else {
            $this->turnHeld = 0;
        }
        int $turnSpeed = 80;
        if ($run) {
            $turnSpeed = 160;
        }
        if ($this->turnHeld < 6) {
            $turnSpeed = 40;
        }
        if ($left) {
            int $turned = $this->angle + $turnSpeed;
            $this->angle = $turned & 8191;
        }
        if ($right) {
            int $turned = $this->angle - $turnSpeed;
            $this->angle = $turned & 8191;
        }

        // thrust: DOOM's forward move 25 (50 running) and side move 24 (40), times 2048
        int $forward = 0;
        int $side = 0;
        int $forwardStep = 25;
        int $sideStep = 24;
        if ($run) {
            $forwardStep = 50;
            $sideStep = 40;
        }
        if ($input->moveForward($keys) !== 0) {
            $forward = $forwardStep;
        }
        if ($input->moveBackward($keys) !== 0) {
            $forward = -$forwardStep;
        }
        if ($input->moveRight($keys) !== 0) {
            $side = $sideStep;
        }
        if ($input->moveLeft($keys) !== 0) {
            $side = -$sideStep;
        }
        if ($forward !== 0) {
            int $forwardThrust = $forward * 2048;
            $this->thrust($trig, $this->angle, $forwardThrust);
        }
        if ($side !== 0) {
            int $sideThrust = $side * 2048;
            int $rightAngle = $this->angle - 2048;
            $this->thrust($trig, $rightAngle, $sideThrust);
        }

        $this->moveXY($collision, $specials, $forward !== 0 || $side !== 0);
        $this->moveZ($collision);
        $this->calcHeight($trig, $levelTime);
        if ($this->damageCount > 0) {
            $this->damageCount--;
        }
        if ($this->bonusCount > 0) {
            $this->bonusCount--;
        }

        // damaging floors (nukage 5, harder slime 10 or 20) hurt every 32 tics while standing on them
        int $sector = $collision->sectorAt($this->unitX(), $this->unitY());
        int $floorFixed = $this->floorZ << 16;
        int $everyThirtyTwo = $levelTime & 31;
        if ($sector >= 0 && $this->z === $floorFixed && $everyThirtyTwo === 0) {
            int $special = $collision->map->sectors[$sector]->special;
            if ($special === 11) {
                $this->godMode = false;
                $this->invulnerable = 0;
                $this->damage(20);
            } else if ($this->suit > 0) {
                // The radiation suit protects ordinary hazardous floors.
            } else if ($special === 7) {
                $this->damage(5);
            } else if ($special === 5) {
                $this->damage(10);
            } else if ($special === 4 || $special === 16) {
                $this->damage(20);
            }
        }

        // use: once per key press, on the nearest special line within 64 units ahead
        bool $use = $input->use($keys) !== 0;
        if ($use && !$this->useHeld) {
            int $line = $collision->useTarget($this->unitX(), $this->unitY(), $trig->cos($this->angle), $trig->sin($this->angle));
            if ($line >= 0) {
                $specials->useLine($line);
            }
        }
        $this->useHeld = $use;
    }

    public function thrust(Trig $trig, int $angle, int $move): void {
        int $alongX = $move * $trig->cos($angle);
        int $alongY = $move * $trig->sin($angle);
        int $pushX = $alongX >> 16;
        int $pushY = $alongY >> 16;
        int $momX = $this->momX + $pushX;
        int $momY = $this->momY + $pushY;
        $this->momX = $momX;
        $this->momY = $momY;
    }

    public function moveXY(Collision $collision, Specials $specials, bool $pushing): void {
        // at most 30 units per tic, moved in halves when faster than 15
        int $limit = 1966080;
        if ($this->momX > $limit) {
            $this->momX = $limit;
        } else if ($this->momX < -$limit) {
            $this->momX = -$limit;
        }
        if ($this->momY > $limit) {
            $this->momY = $limit;
        } else if ($this->momY < -$limit) {
            $this->momY = -$limit;
        }
        int $remainingX = $this->momX;
        int $remainingY = $this->momY;
        int $half = 983040;
        for ($pass = 0; $pass < 4; $pass++) {
            if ($remainingX === 0 && $remainingY === 0) {
                break;
            }
            int $stepX = $remainingX;
            int $stepY = $remainingY;
            if ($remainingX > $half || $remainingX < -$half || $remainingY > $half || $remainingY < -$half) {
                int $halfX = $remainingX >> 1;
                int $halfY = $remainingY >> 1;
                $stepX = $halfX;
                $stepY = $halfY;
            }
            int $leftX = $remainingX - $stepX;
            int $leftY = $remainingY - $stepY;
            $remainingX = $leftX;
            $remainingY = $leftY;
            int $targetX = $this->x + $stepX;
            int $targetY = $this->y + $stepY;
            if ($this->tryMove($collision, $specials, $targetX, $targetY)) {
                continue;
            }
            // slide along whichever axis is still free
            if ($stepX !== 0 && $this->tryMove($collision, $specials, $targetX, $this->y)) {
                $this->momY = 0;
                $remainingY = 0;
                continue;
            }
            if ($stepY !== 0 && $this->tryMove($collision, $specials, $this->x, $targetY)) {
                $this->momX = 0;
                $remainingX = 0;
                continue;
            }
            $this->momX = 0;
            $this->momY = 0;
            break;
        }

        // friction, and a full stop below 1/16 unit per tic when no key is held
        int $stop = 4096;
        bool $slow = $this->momX > -$stop && $this->momX < $stop && $this->momY > -$stop && $this->momY < $stop;
        if ($slow && !$pushing) {
            $this->momX = 0;
            $this->momY = 0;
            return;
        }
        int $frictionX = $this->momX * 59392;
        int $frictionY = $this->momY * 59392;
        int $slowedX = $frictionX >> 16;
        int $slowedY = $frictionY >> 16;
        $this->momX = $slowedX;
        $this->momY = $slowedY;
    }

    public function tryMove(Collision $collision, Specials $specials, int $targetX, int $targetY): bool {
        int $oldX = $this->unitX();
        int $oldY = $this->unitY();
        int $newX = $targetX >> 16;
        int $newY = $targetY >> 16;
        if ($this->noclip) {
            $this->x = $targetX;
            $this->y = $targetY;
            return true;
        }
        if (!$collision->checkPosition($newX, $newY, $this->unitZ())) {
            return false;
        }
        $this->x = $targetX;
        $this->y = $targetY;
        $this->floorZ = $collision->floorZ;
        $this->ceilingZ = $collision->ceilingZ;
        // walk-over specials fire when the player's center changes side of the line
        for ($t = 0; $t < $collision->touchedCount; $t++) {
            int $line = $collision->touched[$t];
            int $before = $collision->pointSide($line, $oldX, $oldY);
            int $after = $collision->pointSide($line, $newX, $newY);
            if ($before !== $after && $before !== 0) {
                $specials->crossLine($line, $before);
            }
        }
        return true;
    }

    // Floors follow moving sectors; walking off a ledge falls with DOOM's gravity of 1 unit/tic².
    public function moveZ(Collision $collision): void {
        if ($this->noclip) {
            int $sector = $collision->sectorAt($this->unitX(), $this->unitY());
            if ($sector >= 0) {
                $this->floorZ = $collision->map->sectors[$sector]->floor_height;
                $this->ceilingZ = max($this->floorZ + 56, $collision->map->sectors[$sector]->ceiling_height);
                $this->z = $this->floorZ << 16;
            }
            return;
        }
        $collision->checkPosition($this->unitX(), $this->unitY(), $this->unitZ());
        int $floorFixed = $collision->floorZ << 16;
        int $ceilingFixed = $collision->ceilingZ << 16;
        $this->floorZ = $collision->floorZ;
        $this->ceilingZ = $collision->ceilingZ;
        if ($this->z < $floorFixed) {
            // stepped up: lift the feet now and ease the eyes up over the next tics
            int $rise = $floorFixed - $this->z;
            int $lowered = $this->viewHeight - $rise;
            $this->viewHeight = $lowered;
            int $missing = 2686976 - $this->viewHeight;
            int $ease = $missing >> 3;
            $this->deltaViewHeight = $ease;
            $this->z = $floorFixed;
            $this->momZ = 0;
        } else if ($this->z > $floorFixed) {
            int $faster = $this->momZ - 65536;
            $this->momZ = $faster;
            int $fallen = $this->z + $this->momZ;
            if ($fallen <= $floorFixed) {
                // a hard landing squats the view a little
                if ($this->momZ < -524288) {
                    int $squat = $this->momZ >> 3;
                    $this->deltaViewHeight = $squat;
                }
                $this->z = $floorFixed;
                $this->momZ = 0;
            } else {
                $this->z = $fallen;
            }
        }
        int $headroom = $ceilingFixed - 3670016;
        if ($this->z > $headroom && $headroom >= $floorFixed) {
            $this->z = $headroom;
        }
    }

    public function calcHeight(Trig $trig, int $levelTime): void {
        int $speedX = $this->momX * $this->momX;
        int $speedY = $this->momY * $this->momY;
        int $squareX = $speedX >> 16;
        int $squareY = $speedY >> 16;
        int $squares = $squareX + $squareY;
        int $bob = $squares >> 2;
        if ($bob > 1048576) {
            $bob = 1048576;
        }
        int $phase = $levelTime * 409;
        int $halfBob = $bob >> 1;
        int $wave = $halfBob * $trig->sin($phase);
        int $bobOffset = $wave >> 16;

        int $eased = $this->viewHeight + $this->deltaViewHeight;
        $this->viewHeight = $eased;
        if ($this->viewHeight > 2686976) {
            $this->viewHeight = 2686976;
            $this->deltaViewHeight = 0;
        }
        if ($this->viewHeight < 1343488) {
            $this->viewHeight = 1343488;
            if ($this->deltaViewHeight <= 0) {
                $this->deltaViewHeight = 1;
            }
        }
        if ($this->deltaViewHeight !== 0) {
            int $accelerated = $this->deltaViewHeight + 16384;
            $this->deltaViewHeight = $accelerated;
            if ($this->deltaViewHeight === 0) {
                $this->deltaViewHeight = 1;
            }
        }
        int $eye = $this->z + $this->viewHeight;
        int $viewZ = $eye + $bobOffset;
        int $ceilingFixed = $this->ceilingZ << 16;
        int $limit = $ceilingFixed - 262144;
        if ($viewZ > $limit) {
            $viewZ = $limit;
        }
        $this->viewZ = $viewZ;
    }

    public function teleport(int $x, int $y, int $angle, int $floor): void {
        $this->spawn($x, $y, $angle, $floor);
    }
}
