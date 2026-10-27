<?php

namespace Showcases\Doom\Data;

// A bounded software-synth voice, with a sample-rate envelope and phase.
packed class MusicVoice {
    public int $active;
    public int $channel;
    public int $note;
    public int $velocity;
    public int $phase;
    public int $step;
    public int $envelope;
    public int $released;
    public int $wave;
    public int $age;
    public int $serial;
    public int $noise;
}
