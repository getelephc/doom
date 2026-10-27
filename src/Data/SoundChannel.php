<?php

namespace Showcases\Doom\Data;

// One resampled WAD PCM voice. Position and step use 16.16 sample coordinates.
packed class SoundChannel {
    public int $offset;
    public int $length;
    public int $position;
    public int $step;
    public int $left;
    public int $right;
    public int $serial;
}
