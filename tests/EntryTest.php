<?php

declare(strict_types=1);

namespace Conia\Registry\Tests;

use Conia\Registry\Entry;
use Conia\Registry\Tests\TestCase;
use stdClass;

/**
 * @internal
 *
 * @covers \Conia\Registry\Entry
 */
final class EntryTest extends TestCase
{
    public function testEntryMethods(): void
    {
        $entry = new Entry('key', stdClass::class);

        $this->assertEquals(stdClass::class, $entry->definition());
        $this->assertEquals(stdClass::class, $entry->get());
        $this->assertEquals(null, $entry->instance());
        $this->assertEquals(null, $entry->getConstructor());
        $this->assertEquals(true, $entry->shouldReify());
        $this->assertEquals(false, $entry->shouldReturnAsIs());
        $this->assertEquals(null, $entry->getArgs());

        $obj = new stdClass();
        $entry
            ->constructor('factoryMethod')
            ->reify(false)
            ->asIs(true)
            ->args(arg1: 13, arg2: 'test')
            ->set($obj);

        $this->assertEquals(stdClass::class, $entry->definition());
        $this->assertEquals($obj, $entry->get());
        $this->assertEquals($obj, $entry->instance());
        $this->assertEquals('factoryMethod', $entry->getConstructor());
        $this->assertEquals(false, $entry->shouldReify());
        $this->assertEquals(true, $entry->shouldReturnAsIs());
        $this->assertEquals(['arg1' => 13, 'arg2' => 'test'], $entry->getArgs());
    }

    public function testEntryCallMethod(): void
    {
        $entry = new Entry('key', stdClass::class);
        $entry->call('method', arg1: 13, arg2: 'arg2');
        $entry->call('next');

        $call1 = $entry->getCalls()[0];
        $call2 = $entry->getCalls()[1];

        $this->assertEquals('method', $call1->method);
        $this->assertEquals(['arg1' => 13, 'arg2' => 'arg2'], $call1->args);
        $this->assertEquals('next', $call2->method);
        $this->assertEquals([], $call2->args);
    }

    public function testReifyNegotiation(): void
    {
        $entry = new Entry('key', stdClass::class);
        $this->assertEquals(true, $entry->shouldReify());

        $entry = new Entry('key', 'string');
        $this->assertEquals(false, $entry->shouldReify());

        $entry = new Entry('key', fn () => true);
        $this->assertEquals(true, $entry->shouldReify());

        $entry = new Entry('key', new stdClass());
        $this->assertEquals(false, $entry->shouldReify());

        $entry = new Entry('key', 73);
        $this->assertEquals(false, $entry->shouldReify());

        $entry = new Entry('key', []);
        $this->assertEquals(false, $entry->shouldReify());

        $entry = new Entry('key', '_global_test_function');
        $this->assertEquals(true, $entry->shouldReify());
    }
}
