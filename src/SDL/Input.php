<?php

namespace Showcases\Doom\SDL;

// Keyboard state by SDL scancode: WASD or arrows to move and turn, Shift to run,
// Space or E to use, Tab for the automap, V for vsync, Esc for the menu.
final class Input {
    public int $mouseX = 0;
    public bool $alwaysRun = false;
    public int $mouseButtons = 0;
    public function fire(ptr $keys): int {
        if (($this->mouseButtons & 1) !== 0) { return 1; }
        return $this->either($keys, 224, 228);
    }

    public function confirm(ptr $keys): int {
        return $this->either($keys, 40, 44);
    }

    public function weapon(ptr $keys): int {
        for ($slot = 1; $slot < 8; $slot++) {
            if ($this->keyPressed($keys, 29 + $slot) !== 0) {
                return $slot;
            }
        }
        return 0;
    }

    public function keyPressed(ptr $keys, int $scanCode): int {
        return ptr_read8(ptr_offset($keys, $scanCode));
    }

    public function either(ptr $keys, int $first, int $second): int {
        if ($this->keyPressed($keys, $first) !== 0) {
            return 1;
        }
        return $this->keyPressed($keys, $second);
    }

    public function shouldQuit(ptr $keys): int {
        return $this->keyPressed($keys, 41);
    }

    public function toggleMap(ptr $keys): int {
        return $this->keyPressed($keys, 43);
    }

    public function toggleVsync(ptr $keys): int {
        return $this->keyPressed($keys, 25);
    }

    public function moveForward(ptr $keys): int {
        return $this->either($keys, 26, 82);
    }

    public function moveBackward(ptr $keys): int {
        return $this->either($keys, 22, 81);
    }

    public function moveLeft(ptr $keys): int {
        return $this->keyPressed($keys, 4);
    }

    public function moveRight(ptr $keys): int {
        return $this->keyPressed($keys, 7);
    }

    public function turnLeft(ptr $keys): int {
        return $this->keyPressed($keys, 80);
    }

    public function turnRight(ptr $keys): int {
        return $this->keyPressed($keys, 79);
    }

    public function run(ptr $keys): int {
        if ($this->alwaysRun) { return 1; }
        return $this->either($keys, 225, 229);
    }

    public function use(ptr $keys): int {
        return $this->either($keys, 44, 8);
    }
}
