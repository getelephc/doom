<?php

namespace Showcases\Doom\Tests\Fixture;

use Showcases\Doom\App\Config;
use Showcases\Doom\App\Session;
use Showcases\Doom\SDL\Input;

// Extended synthetic map: three enemy types, two barrels, weapon art and PCM.
abstract class CombatCase extends SessionCase {
    protected bool $full = false;
    protected function boot(): Session {
        TestWad $wad = new TestWad(true, $this->full);
        $this->wadPath = $wad->write();
        Config $config = new Config();
        $config->wadPath = $this->wadPath;
        $config->verbose = false;
        Session $session = new Session($config);
        $this->assertTrue($session->boot());
        return $session;
    }

    protected function isolate(Session $session, int $keep): void {
        for ($i = 0; $i < $session->things->count; $i++) {
            if ($i !== $keep) {
                $session->things->mobjs[$i]->active = 0;
            }
        }
    }

    protected function advance(Session $session, int $count, int $key = -1): void {
        buffer<u8> $keys = buffer_new<u8>(512);
        if ($key >= 0) {
            $keys[$key] = 1;
        }
        for ($t = 0; $t < $count; $t++) {
            $session->runTic(new Input(), buffer_ptr($keys));
        }
        buffer_free($keys);
    }
}
