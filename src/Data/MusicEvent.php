<?php

namespace Showcases\Doom\Data;

// Decoded MUS event at an absolute 22050 Hz sample frame.
packed class MusicEvent {
    public int $sample;
    public int $type;
    public int $channel;
    public int $a;
    public int $b;
}
