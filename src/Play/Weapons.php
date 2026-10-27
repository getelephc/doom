<?php

namespace Showcases\Doom\Play;

use Showcases\Doom\Audio\Sounds;
use Showcases\Doom\SDL\Input;

// Original DOOM weapon mechanics. Slot 1 toggles fists/chainsaw; BFG has a charge
// stage, and every firing path uses shared ammo, traces and projectile damage.
final class Weapons {
    public function tick(Player $p, Combat $c, Input $input, ptr $keys): void {
        if ($p->health <= 0) { return; }
        int $selected = $input->weapon($keys);
        if ($selected > 0 && $selected !== $p->selectionHeld) {
            if ($selected === 1 && ($p->weapons & 256) !== 0 && $p->weapon !== 8) { $selected = 8; }
            if (($p->weapons & (1 << $selected)) !== 0) { $p->pendingWeapon = $selected; }
        }
        $p->selectionHeld = $input->weapon($keys);
        bool $fire = $input->fire($keys) !== 0;
        bool $repeat = $p->fireHeld;
        $p->fireHeld = $fire;
        if ($p->weaponCooldown > 0) {
            $p->weaponCooldown--;
            $p->weaponAge++;
            if ($p->weapon === 3 && $p->weaponAge === 14) { $c->sounds->play(Sounds::COCK); }
            if ($p->weapon === 7 && $p->weaponAge === 20) { $this->launch($p, $c, 6004); }
            if ($p->weaponCooldown > 0) { return; }
        }
        if ($p->pendingWeapon > 0) {
            $p->weapon = $p->pendingWeapon;
            $p->pendingWeapon = 0;
        }
        if (!$fire) {
            $p->weaponAge = 0;
            $p->sawClock++;
            if ($p->weapon === 8 && $p->sawClock % 35 === 0) { $c->sounds->play(Sounds::SAW_IDLE); }
            return;
        }
        if (!$this->hasAmmo($p, $p->weapon)) {
            $p->weapon = $this->fallback($p);
        }
        $p->weaponAge = 0;
        int $w = $p->weapon;
        if ($w !== 1 && $w !== 8) { $c->noise(); }
        if ($w === 1 || $w === 8) {
            $p->weaponCooldown = 16;
            if ($w === 8) { $p->weaponCooldown = 4; }
            int $damage = ($c->random() % 10 + 1) * 2;
            if ($w === 1 && $p->berserk) { $damage *= 10; }
            int $hit = $c->playerShot($p->angle, $damage, 64);
            if ($w === 8) {
                $c->sounds->play($hit >= 0 ? Sounds::SAW_HIT : Sounds::SAW_FULL);
            } else if ($hit >= 0) { $c->sounds->play(Sounds::PUNCH); }
            if ($hit >= 0) { $c->noise(); }
        } else if ($w === 2 || $w === 4) {
            $p->bullets--;
            $p->weaponCooldown = $w === 2 ? 14 : 4;
            int $angle = $p->angle;
            if ($repeat) { $angle = ($angle + ($c->random() - $c->random()) * 2) & 8191; }
            $c->playerShot($angle, ($c->random() % 3 + 1) * 5, 2048);
            $c->sounds->play(Sounds::PISTOL);
        } else if ($w === 3) {
            $p->shells--;
            $p->weaponCooldown = 35;
            for ($pellet = 0; $pellet < 7; $pellet++) {
                int $angle = ($p->angle + ($c->random() - $c->random()) * 2) & 8191;
                $c->playerShot($angle, ($c->random() % 3 + 1) * 5, 2048);
            }
            $c->sounds->play(Sounds::SHOTGUN);
        } else if ($w === 5) {
            $p->rockets--;
            $p->weaponCooldown = 20;
            $this->launch($p, $c, 6002);
            $c->sounds->play(Sounds::ROCKET);
        } else if ($w === 6) {
            $p->cells--;
            $p->weaponCooldown = 3;
            $this->launch($p, $c, 6003);
            $c->sounds->play(Sounds::PLASMA);
        } else if ($w === 7) {
            $p->cells -= 40;
            $p->weaponCooldown = 60;
            $c->sounds->play(Sounds::BFG);
        }
    }

    public function hasAmmo(Player $p, int $weapon): bool {
        if (($p->weapons & (1 << $weapon)) === 0) { return false; }
        if ($weapon === 1 || $weapon === 8) { return true; }
        if ($weapon === 2 || $weapon === 4) { return $p->bullets > 0; }
        if ($weapon === 3) { return $p->shells > 0; }
        if ($weapon === 5) { return $p->rockets > 0; }
        if ($weapon === 6) { return $p->cells > 0; }
        return $p->cells >= 40;
    }

    public function fallback(Player $p): int {
        $choices = [6, 4, 3, 2, 8, 5, 1];
        for ($i = 0; $i < 7; $i++) {
            int $weapon = (int)$choices[$i];
            if ($this->hasAmmo($p, $weapon)) { return $weapon; }
        }
        return 1;
    }

    public function launch(Player $p, Combat $c, int $type): void {
        int $x = $p->unitX();
        int $y = $p->unitY();
        int $z = $p->unitZ() + 32;
        int $dx = ($c->trig->cos($p->angle) * 2048) >> 16;
        int $dy = ($c->trig->sin($p->angle) * 2048) >> 16;
        int $hit = $c->trace->aim($x, $y, $z, $dx, $dy, -1);
        int $tx = $x + $dx;
        int $ty = $y + $dy;
        int $tz = $z;
        if ($hit >= 0) {
            $tx = $c->things->mobjs[$hit]->x;
            $ty = $c->things->mobjs[$hit]->y;
            $tz = $c->things->mobjs[$hit]->z + ($c->things->mobjs[$hit]->height >> 1);
        }
        $c->projectiles->launch($c, -1, $x, $y, $z, $tx, $ty, $tz, $type);
    }
}
