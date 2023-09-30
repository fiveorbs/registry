<?php

declare(strict_types=1);

use Conia\Registry\Call;
use Conia\Registry\Exception\ContainerException;
use Conia\Registry\Inject;
use Conia\Registry\Registry;
use Conia\Registry\Resolver;
use Conia\Registry\Tests\Fixtures\TestClass;
use Conia\Registry\Tests\Fixtures\TestClassApp;
use Conia\Registry\Tests\Fixtures\TestClassCall;
use Conia\Registry\Tests\Fixtures\TestClassInject;
use Conia\Registry\Tests\Fixtures\TestClassRegistryArgs;
use Conia\Registry\Tests\Fixtures\TestClassRequest;
use Conia\Registry\Tests\Fixtures\TestClassResolver;
use Conia\Registry\Tests\Fixtures\TestClassResolverDefault;
use Conia\Registry\Tests\Fixtures\TestClassWithConstructor;
use Conia\Registry\Tests\TestCase;

uses(TestCase::class);

test('Simple autowiring', function () {
    $resolver = new Resolver($this->registry());

    expect($resolver->autowire(TestClassWithConstructor::class))
        ->toBeInstanceOf(TestClassWithConstructor::class);
});

test('Autowiring with partial args', function () {
    $resolver = new Resolver($this->registry());
    $tc = $resolver->autowire(TestClassResolver::class, ['name' => 'chuck', 'number' => 73]);

    expect($tc)->toBeInstanceOf(TestClassResolver::class);
    expect($tc->name)->toBe('chuck');
    expect($tc->number)->toBe(73);
    expect($tc->tc)->toBeInstanceOf(TestClass::class);
});

test('Autowiring with partial args and default values', function () {
    $resolver = new Resolver($this->registry());
    $tc = $resolver->autowire(TestClassResolverDefault::class, ['number' => 73]);

    expect($tc)->toBeInstanceOf(TestClassResolverDefault::class);
    expect($tc->name)->toBe('default');
    expect($tc->number)->toBe(73);
    expect($tc->tc)->toBeInstanceOf(TestClass::class);
});

test('Autowiring with simple factory method', function () {
    $resolver = new Resolver($this->registry());
    $tc = $resolver->autowire(TestClassRegistryArgs::class, [], 'fromDefaults');

    expect($tc->tc instanceof TestClass)->toBe(true);
    expect($tc->config instanceof TestClassApp)->toBe(true);
    expect($tc->config->app())->toBe('fromDefaults');
    expect($tc->test)->toBe('fromDefaults');
});

test('Autowiring with factory method and args', function () {
    $resolver = new Resolver($this->registry());
    $tc = $resolver->autowire(TestClassRegistryArgs::class, ['test' => 'passed', 'app' => 'passed'], 'fromArgs');

    expect($tc->tc instanceof TestClass)->toBe(true);
    expect($tc->config instanceof TestClassApp)->toBe(true);
    expect($tc->config->app())->toBe('passed');
    expect($tc->test)->toBe('passed');
});

test('Get constructor args', function () {
    $resolver = new Resolver($this->registry());
    $args = $resolver->resolveConstructorArgs(TestClassWithConstructor::class);

    expect($args[0])->toBeInstanceOf(TestClass::class);
});

test('Get closure args', function () {
    $resolver = new Resolver($this->registry());
    $args = $resolver->resolveCallableArgs(function (Testclass $tc, int $number = 13) {});

    expect($args[0])->toBeInstanceOf(TestClass::class);
    expect($args[1])->toBe(13);
});

test('Get callable object args', function () {
    $resolver = new Resolver($this->registry());
    $tc = $resolver->autowire(TestClass::class);
    $args = $resolver->resolveCallableArgs($tc);

    expect($args[0])->toBe('default');
    expect($args[1])->toBe(13);
});

test('Call attributes', function () {
    $resolver = new Resolver($this->registry());
    $attr = $resolver->autowire(TestClassCall::class);

    expect($attr->registry)->toBeInstanceOf(Registry::class);
    expect($attr->app)->toBeInstanceOf(TestClassApp::class);
    expect($attr->request)->toBeInstanceOf(TestClassRequest::class);
    expect($attr->arg1)->toBe('arg1');
    expect($attr->arg2)->toBe('arg2');
});

test('Call attribute does not allow unnamed args', function () {
    new Call('method', 'arg');
})->throws(RuntimeException::class, 'Arguments for Call');

test('Fail when autowire is turned off', function () {
    $resolver = new Resolver($this->registry(autowire: false));
    $resolver->autowire(Response::class);
})->throws(ContainerException::class, 'Autowiring is turned off');

test('Inject closure with attribute', function () {
    $registry = $this->registry();
    $resolver = new Resolver($registry);
    $registry->add('injected', new TestClassApp('injected'));

    $func = #[Inject(name: 'Chuck', app: 'injected')] function (
        Registry $r,
        TestClassApp $app,
        string $name
    ): array {
        return [$app->app, $name, $r];
    };

    $result = $func(...$resolver->resolveCallableArgs($func));

    expect($result[0])->toBe('injected');
    expect($result[1])->toBe('Chuck');
    expect($result[2])->toBeInstanceOf(Registry::class);
});

test('Inject constructor with attribute', function () {
    $registry = $this->registry();
    $resolver = new Resolver($registry);
    $registry->add('injected', new TestClassApp('injected'));

    $args = $resolver->resolveConstructorArgs(TestClassInject::class);
    $obj = new TestClassInject(...$args);

    expect($obj->app->app())->toBe('injected');
    expect($obj->arg1)->toBe('arg1');
    expect($obj->arg2)->toBe(13);
    expect($obj->registry)->toBeInstanceOf(Registry::class);
    expect((string)$obj->tc)->toBe('Stringable extended');
});

test('Inject attribute does not allow unnamed args', function () {
    new Inject('arg');
})->throws(RuntimeException::class, 'Arguments for Inject');

test('Inject and Call combined', function () {
    $registry = $this->registry();
    $registry->add('injected', new TestClassApp('injected'));
    $resolver = new Resolver($registry);

    $obj = $resolver->autowire(TestClassInject::class);

    expect($obj->app->app())->toBe('injected');
    expect($obj->arg1)->toBe('arg1');
    expect($obj->arg2)->toBe(13);
    expect($obj->registry)->toBeInstanceOf(Registry::class);
    expect((string)$obj->tc)->toBe('Stringable extended');
    expect($obj->calledArg1)->toBe('calledArg1');
    expect($obj->calledArg2)->toBe(73);
});
