<?php

namespace Showcases\Doom\SDL;

use Showcases\Doom\Audio\Sounds;

// Main-thread queued audio avoids callbacks into PHP from SDL's audio thread.
final class Audio {
    public int $device;

    public function __construct() {
        $this->device = 0;
    }

    public function boot(): bool {
        if (SDL_InitSubSystem(16) !== 0) {
            return false;
        }
        // SDL_AudioSpec has the same 32-byte LP64 layout on supported targets.
        // Zero callback and userdata select SDL_QueueAudio rather than a callback.
        buffer<u8> $spec = buffer_new<u8>(32);
        ptr $p = buffer_ptr($spec);
        ptr_write32($p, 22050);
        ptr_write16(ptr_offset($p, 4), 32784);
        ptr_write8(ptr_offset($p, 6), 2);
        ptr_write16(ptr_offset($p, 8), 512);
        $this->device = SDL_OpenAudioDevice(ptr_null(), 0, $p, ptr_null(), 0);
        buffer_free($spec);
        if ($this->device === 0) {
            return false;
        }
        SDL_PauseAudioDevice($this->device, 0);
        return true;
    }

    public function submit(Sounds $sounds): void {
        $sounds->mix();
        if ($this->device === 0) {
            return;
        }
        // Bound latency and memory even after stalls or many catch-up tics.
        if (SDL_GetQueuedAudioSize($this->device) > 10080) {
            SDL_ClearQueuedAudio($this->device);
        }
        SDL_QueueAudio($this->device, buffer_ptr($sounds->output), 2520);
    }

    public function shutdown(): void {
        if ($this->device !== 0) {
            SDL_CloseAudioDevice($this->device);
            $this->device = 0;
        }
    }
}
