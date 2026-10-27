<?php

namespace Showcases\Doom\Play;

// Pickup and cheat grants share the same capacities, ownership and power-up state.
final class Inventory {
    public function pickup(Player $p, int $type, bool $dropped): bool {
        if ($type === 2011 || $type === 2012) {
            if ($p->health >= 100) {
                return false;
            }
            int $amount = 10;
            $p->message = "Stimpack";
            if ($type === 2012) {
                $amount = 25;
                $p->message = "Medikit";
            }
            $p->health = min(100, $p->health + $amount);
        } else if ($type === 2014 || $type === 2013) {
            int $amount = 1;
            $p->message = "Health bonus";
            if ($type === 2013) {
                $amount = 100;
                $p->message = "Soulsphere";
            }
            $p->health = min(200, $p->health + $amount);
        } else if ($type === 2015) {
            $p->armor = min(200, $p->armor + 1);
            if ($p->armorType === 0) {
                $p->armorType = 1;
            }
            $p->message = "Armor bonus";
        } else if ($type === 2018 || $type === 2019) {
            int $level = 100;
            int $kind = 1;
            $p->message = "Green armor";
            if ($type === 2019) {
                $level = 200;
                $kind = 2;
                $p->message = "Blue armor";
            }
            if ($p->armor >= $level) {
                return false;
            }
            $p->armor = $level;
            $p->armorType = $kind;
        } else if ($this->weaponFor($type) > 0) {
            int $weapon = $this->weaponFor($type);
            int $bit = 1 << $weapon;
            bool $fresh = ($p->weapons & $bit) === 0;
            if (($p->availableWeapons & $bit) === 0) {
                $p->message = "Weapon art missing from this WAD";
                return false;
            }
            $p->weapons |= $bit;
            int $amount = 2;
            if ($dropped) {
                $amount = 1;
            }
            bool $ammo = $this->ammo($p, $weapon, $amount);
            if (!$fresh && !$ammo && $weapon !== 8) {
                return false;
            }
            if ($fresh || $weapon === 3) {
                $p->weapon = $weapon;
            }
            $p->message = $this->weaponName($weapon);
        } else if ($type === 2007 || $type === 2048 || $type === 2008 || $type === 2049 || $type === 2010 || $type === 2046 || $type === 2047 || $type === 17) {
            int $weapon = 2;
            if ($type === 2008 || $type === 2049) {
                $weapon = 3;
            } else if ($type === 2010 || $type === 2046) {
                $weapon = 5;
            } else if ($type === 2047 || $type === 17) {
                $weapon = 6;
            }
            int $amount = 1;
            if ($type === 2048 || $type === 2049 || $type === 2046 || $type === 17) {
                $amount = 5;
            }
            if (!$this->ammo($p, $weapon, $amount, $dropped)) {
                return false;
            }
            $p->message = "Picked up ammunition";
        } else if ($type === 5 || $type === 40 || $type === 6 || $type === 39 || $type === 13 || $type === 38) {
            int $bit = 1;
            $p->message = "Blue key";
            if ($type === 6 || $type === 39) {
                $bit = 2;
                $p->message = "Yellow key";
            } else if ($type === 13 || $type === 38) {
                $bit = 4;
                $p->message = "Red key";
            }
            $p->keys |= $bit;
        } else if ($type === 8) {
            if (!$p->backpack) {
                $p->backpack = true;
                $p->maxBullets = 400;
                $p->maxShells = 100;
                $p->maxRockets = 100;
                $p->maxCells = 600;
            }
            $this->ammo($p, 2, 1);
            $this->ammo($p, 3, 1);
            $this->ammo($p, 5, 1);
            $this->ammo($p, 6, 1);
            $p->message = "Backpack";
        } else if ($type === 2022) {
            $p->invulnerable = 1050;
            $p->message = "Invulnerability!";
        } else if ($type === 2023) {
            $p->berserk = true;
            $p->damageCount = 100;
            $p->health = max(100, $p->health);
            $p->weapon = 1;
            $p->message = "Berserk!";
        } else if ($type === 2024) {
            $p->invisible = 2100;
            $p->message = "Partial invisibility";
        } else if ($type === 2025) {
            $p->suit = 2100;
            $p->message = "Radiation shielding suit";
        } else if ($type === 2026) {
            $p->fullMap = true;
            $p->message = "Computer area map";
        } else if ($type === 2045) {
            $p->lightAmp = 4200;
            $p->message = "Light amplification visor";
        } else {
            return false;
        }
        $p->bonusCount = 6;
        return true;
    }

    // Amount is measured in clips, four shells, one rocket or twenty cells.
    public function ammo(Player $p, int $weapon, int $amount, bool $dropped = false): bool {
        if ($p->skill === 1 || $p->skill === 5) { $amount *= 2; }
        int $before = $p->bullets;
        if ($weapon === 3) {
            $before = $p->shells;
            $p->shells = min($p->maxShells, $before + $amount * 4);
            return $p->shells > $before;
        }
        if ($weapon === 5) {
            $before = $p->rockets;
            $p->rockets = min($p->maxRockets, $before + $amount);
            return $p->rockets > $before;
        }
        if ($weapon === 6 || $weapon === 7) {
            $before = $p->cells;
            $p->cells = min($p->maxCells, $before + $amount * 20);
            return $p->cells > $before;
        }
        if ($weapon === 8) {
            return false;
        }
        int $clip = 10;
        if ($dropped) {
            $clip = 5;
        }
        $p->bullets = min($p->maxBullets, $before + $amount * $clip);
        return $p->bullets > $before;
    }

    public function weaponFor(int $type): int {
        if ($type === 2001) { return 3; }
        if ($type === 2002) { return 4; }
        if ($type === 2003) { return 5; }
        if ($type === 2004) { return 6; }
        if ($type === 2006) { return 7; }
        if ($type === 2005) { return 8; }
        return 0;
    }

    public function weaponName(int $weapon): string {
        if ($weapon === 1) { return "Fists"; }
        if ($weapon === 2) { return "Pistol"; }
        if ($weapon === 3) { return "Shotgun"; }
        if ($weapon === 4) { return "Chaingun"; }
        if ($weapon === 5) { return "Rocket launcher"; }
        if ($weapon === 6) { return "Plasma rifle"; }
        if ($weapon === 7) { return "BFG 9000"; }
        return "Chainsaw";
    }

    public function grant(Player $p, bool $keys): void {
        $p->weapons = $p->availableWeapons;
        $p->bullets = $p->maxBullets;
        $p->shells = $p->maxShells;
        $p->rockets = $p->maxRockets;
        $p->cells = $p->maxCells;
        $p->armor = 200;
        $p->armorType = 2;
        if ($keys) {
            $p->keys = 7;
        }
    }
}
