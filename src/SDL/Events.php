<?php

namespace Showcases\Doom\SDL;

// SDL2's LP64 event layout, decoded without calling SDL so text input can be
// tested on hosts whose compatibility layer cannot inject synthetic text events.
final class Events {
    public string $text = "";
    public bool $quit = false;

    public function begin(): void { $this->text = ""; }

    public function consume(buffer<u8> $event): void {
        int $type = $event[0] | ($event[1] << 8) | ($event[2] << 16) | ($event[3] << 24);
        if ($type === 256) { $this->quit = true; }
        else if ($type === 771) {
            for ($i = 12; $i < 44; $i++) {
                int $byte = $event[$i];
                if ($byte === 0) { break; }
                if (strlen($this->text) < 128) { $this->text .= chr($byte); }
            }
        }
    }
}
