<?php

namespace Showcases\Doom\Tests;

require_once __DIR__ . '/../src/engine.php';

use Elephc\Testing\TestCase;
use Showcases\Doom\SDL\Events;

final class InputEventsTest extends TestCase {
    public function testTextEventUsesTheSdl2OffsetAndStopsAtNul(): void {
        $events = new Events();
        $record = buffer_new<u8>(64);
        $record[0] = 3; $record[1] = 3;
        ptr_write_string(ptr_offset(buffer_ptr($record), 12), "iddqd\0ignored");
        $events->consume($record);
        $this->assertSameString("iddqd", $events->text);
        $this->assertFalse($events->quit);
        $events->begin();
        $this->assertSameString("", $events->text);
        buffer_free($record);
    }

    public function testTextAggregationIsBoundedAndQuitPersistsAcrossFrames(): void {
        $events = new Events();
        $record = buffer_new<u8>(64);
        $record[0] = 3; $record[1] = 3;
        for ($i = 12; $i < 44; $i++) { $record[$i] = 97; }
        for ($n = 0; $n < 10; $n++) { $events->consume($record); }
        $this->assertSameInt(128, strlen($events->text));
        $record[0] = 0; $record[1] = 1;
        $events->consume($record);
        $events->begin();
        $this->assertTrue($events->quit);
        buffer_free($record);
    }
}
