<?php

declare(strict_types=1);

use Conia\Registry\Entry;

require __DIR__ . '/Fixtures/globalTestSymbols.php';

test('Entry methods', function () {
    $entry = new Entry('key', stdClass::class);

    expect($entry->definition())->toBe(stdClass::class);
    expect($entry->get())->toBe(stdClass::class);
    expect($entry->instance())->toBe(null);
    expect($entry->getConstructor())->toBe(null);
    expect($entry->shouldReify())->toBe(true);
    expect($entry->shouldReturnAsIs())->toBe(false);
    expect($entry->getArgs())->toBe(null);

    $obj = new stdClass();
    $entry
        ->constructor('factoryMethod')
        ->reify(false)
        ->asIs(true)
        ->args(arg1: 13, arg2: 'test')
        ->set($obj);

    expect($entry->definition())->toBe(stdClass::class);
    expect($entry->get())->toBe($obj);
    expect($entry->instance())->toBe($obj);
    expect($entry->getConstructor())->toBe('factoryMethod');
    expect($entry->shouldReify())->toBe(false);
    expect($entry->shouldReturnAsIs())->toBe(true);
    expect($entry->getArgs())->toBe(['arg1' => 13, 'arg2' => 'test']);
});


test('Entry call method', function () {
    $entry = new Entry('key', stdClass::class);
    $entry->call('method', arg1: 13, arg2: 'arg2');
    $entry->call('next');

    $call1 = $entry->getCalls()[0];
    $call2 = $entry->getCalls()[1];

    expect($call1->method)->toBe('method');
    expect($call1->args)->toBe([
        'arg1' => 13,
        'arg2' => 'arg2',
    ]);
    expect($call2->method)->toBe('next');
    expect($call2->args)->toBe([]);
});


test('Reify negotiation', function () {
    $entry = new Entry('key', stdClass::class);
    expect($entry->shouldReify())->toBe(true);

    $entry = new Entry('key', 'string');
    expect($entry->shouldReify())->toBe(false);

    $entry = new Entry('key', fn () => true);
    expect($entry->shouldReify())->toBe(true);

    $entry = new Entry('key', new stdClass());
    expect($entry->shouldReify())->toBe(false);

    $entry = new Entry('key', 73);
    expect($entry->shouldReify())->toBe(false);

    $entry = new Entry('key', []);
    expect($entry->shouldReify())->toBe(false);

    $entry = new Entry('key', '_global_test_function');
    expect($entry->shouldReify())->toBe(true);
});
