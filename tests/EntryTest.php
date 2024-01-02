<?php

declare(strict_types=1);

namespace Conia\Registry\Tests;

use Conia\Registry\Entry;
use Conia\Registry\Tests\TestCase;
use stdClass;

final class EntryTest extends TestCase
{
    public function testEntryMethods(): void
    {
        $entry = new Entry('key', stdClass::class);

        $this->assertSame(stdClass::class, $entry->definition());
        $this->assertSame(stdClass::class, $entry->get());
        $this->assertSame(null, $entry->instance());
        $this->assertSame(null, $entry->getConstructor());
        $this->assertSame(true, $entry->shouldReify());
        $this->assertSame(false, $entry->shouldReturnAsIs());
        $this->assertSame(null, $entry->getArgs());

        $obj = new stdClass();
        $entry
            ->constructor('factoryMethod')
            ->reify(false)
            ->asIs(true)
            ->args(arg1: 13, arg2: 'test')
            ->set($obj);

        $this->assertSame(stdClass::class, $entry->definition());
        $this->assertSame($obj, $entry->get());
        $this->assertSame($obj, $entry->instance());
        $this->assertSame('factoryMethod', $entry->getConstructor());
        $this->assertSame(false, $entry->shouldReify());
        $this->assertSame(true, $entry->shouldReturnAsIs());
        $this->assertSame(['arg1' => 13, 'arg2' => 'test'], $entry->getArgs());
    }

    public function testEntryCallMethod(): void
    {
        $entry = new Entry('key', stdClass::class);
        $entry->call('method', arg1: 13, arg2: 'arg2');
        $entry->call('next');

        $call1 = $entry->getCalls()[0];
        $call2 = $entry->getCalls()[1];

        $this->assertSame('method', $call1->method);
        $this->assertSame(['arg1' => 13, 'arg2' => 'arg2'], $call1->args);
        $this->assertSame('next', $call2->method);
        $this->assertSame([], $call2->args);
    }

    public function testReifyNegotiation(): void
    {
        $entry = new Entry('key', stdClass::class);
        $this->assertSame(true, $entry->shouldReify());

        $entry = new Entry('key', 'string');
        $this->assertSame(false, $entry->shouldReify());

        $entry = new Entry('key', fn () => true);
        $this->assertSame(true, $entry->shouldReify());

        $entry = new Entry('key', new stdClass());
        $this->assertSame(false, $entry->shouldReify());

        $entry = new Entry('key', 73);
        $this->assertSame(false, $entry->shouldReify());

        $entry = new Entry('key', []);
        $this->assertSame(false, $entry->shouldReify());

        $entry = new Entry('key', '_global_test_function');
        $this->assertSame(true, $entry->shouldReify());
    }
}
