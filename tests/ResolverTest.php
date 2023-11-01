<?php

declare(strict_types=1);

namespace Conia\Registry\Tests;

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

final class ResolverTest extends TestCase
{
    public function testSimpleAutowiring(): void
    {
        $resolver = new Resolver($this->registry());

        $this->assertInstanceOf(TestClassWithConstructor::class, $resolver->autowire(TestClassWithConstructor::class));
    }

    public function testAutowiringWithPartialArgs(): void
    {
        $resolver = new Resolver($this->registry());
        $tc = $resolver->autowire(TestClassResolver::class, ['name' => 'chuck', 'number' => 73]);

        $this->assertInstanceOf(TestClassResolver::class, $tc);
        $this->assertEquals('chuck', $tc->name);
        $this->assertEquals(73, $tc->number);
        $this->assertInstanceOf(TestClass::class, $tc->tc);
    }

    public function testAutowiringWithPartialArgsAndDefaultValues(): void
    {
        $resolver = new Resolver($this->registry());
        $tc = $resolver->autowire(TestClassResolverDefault::class, ['number' => 73]);

        $this->assertInstanceOf(TestClassResolverDefault::class, $tc);
        $this->assertEquals('default', $tc->name);
        $this->assertEquals(73, $tc->number);
        $this->assertInstanceOf(TestClass::class, $tc->tc);
    }

    public function testAutowiringWithSimpleFactoryMethod(): void
    {
        $resolver = new Resolver($this->registry());
        $tc = $resolver->autowire(TestClassRegistryArgs::class, [], 'fromDefaults');

        $this->assertEquals(true, $tc->tc instanceof TestClass);
        $this->assertEquals(true, $tc->app instanceof TestClassApp);
        $this->assertEquals('fromDefaults', $tc->app->app());
        $this->assertEquals('fromDefaults', $tc->test);
    }

    public function testAutowiringWithFactoryMethodAndArgs(): void
    {
        $resolver = new Resolver($this->registry());
        $tc = $resolver->autowire(TestClassRegistryArgs::class, ['test' => 'passed', 'app' => 'passed'], 'fromArgs');

        $this->assertEquals(true, $tc->tc instanceof TestClass);
        $this->assertEquals(true, $tc->app instanceof TestClassApp);
        $this->assertEquals('passed', $tc->app->app());
        $this->assertEquals('passed', $tc->test);
    }

    public function testAutowiringWithNonAssocArgsArray(): void
    {
        $resolver = new Resolver($this->registry());
        $tc = $resolver->autowire(TestClassRegistryArgs::class, [new TestClass('non assoc'), 'passed']);

        $this->assertEquals(true, $tc->tc instanceof TestClass);
        $this->assertEquals('non assoc', $tc->tc->value);
        $this->assertEquals('passed', $tc->test);
        $this->assertEquals(true, $tc->app instanceof TestClassApp);
    }

    public function testGetConstructorArgs(): void
    {
        $resolver = new Resolver($this->registry());
        $args = $resolver->resolveConstructorArgs(TestClassWithConstructor::class);

        $this->assertInstanceOf(TestClass::class, $args[0]);
    }

    public function testGetClosureArgs(): void
    {
        $resolver = new Resolver($this->registry());
        $args = $resolver->resolveCallableArgs(function (Testclass $tc, int $number = 13) {});

        $this->assertInstanceOf(TestClass::class, $args[0]);
        $this->assertEquals(13, $args[1]);
    }

    public function testGetCallableObjectArgs(): void
    {
        $resolver = new Resolver($this->registry());
        $tc = $resolver->autowire(TestClass::class);
        $args = $resolver->resolveCallableArgs($tc);

        $this->assertEquals('default', $args[0]);
        $this->assertEquals(13, $args[1]);
    }

    public function testCallAttributes(): void
    {
        $resolver = new Resolver($this->registry());
        $attr = $resolver->autowire(TestClassCall::class);

        $this->assertInstanceOf(Registry::class, $attr->registry);
        $this->assertInstanceOf(TestClassApp::class, $attr->app);
        $this->assertInstanceOf(TestClassRequest::class, $attr->request);
        $this->assertEquals('arg1', $attr->arg1);
        $this->assertEquals('arg2', $attr->arg2);
    }

    public function testCallAttributeDoesNotAllowUnnamedArgs(): void
    {
        $this->throws(ContainerException::class, 'Arguments for Call');

        new Call('method', 'arg');
    }

    public function testFailWhenAutowireIsTurnedOff(): void
    {
        $this->throws(ContainerException::class, 'Autowiring is turned off');

        $resolver = new Resolver($this->registry(autowire: false));
        $resolver->autowire(Response::class);
    }

    public function testInjectClosureWithAttribute(): void
    {
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

        $this->assertEquals('injected', $result[0]);
        $this->assertEquals('Chuck', $result[1]);
        $this->assertInstanceOf(Registry::class, $result[2]);
    }

    public function testInjectConstructorWithAttribute(): void
    {
        $registry = $this->registry();
        $resolver = new Resolver($registry);
        $registry->add('injected', new TestClassApp('injected'));

        $args = $resolver->resolveConstructorArgs(TestClassInject::class);
        $obj = new TestClassInject(...$args);

        $this->assertEquals('injected', $obj->app->app());
        $this->assertEquals('arg1', $obj->arg1);
        $this->assertEquals(13, $obj->arg2);
        $this->assertInstanceOf(Registry::class, $obj->registry);
        $this->assertEquals('Stringable extended', (string)$obj->tc);
    }

    public function testInjectAttributeDoesNotAllowUnnamedArgs(): void
    {
        $this->throws(ContainerException::class, 'Arguments for Inject');

        new Inject('arg');
    }

    public function testInjectAndCallCombined(): void
    {
        $registry = $this->registry();
        $registry->add('injected', new TestClassApp('injected'));
        $resolver = new Resolver($registry);

        $obj = $resolver->autowire(TestClassInject::class);

        $this->assertEquals('injected', $obj->app->app());
        $this->assertEquals('arg1', $obj->arg1);
        $this->assertEquals(13, $obj->arg2);
        $this->assertInstanceOf(Registry::class, $obj->registry);
        $this->assertEquals('Stringable extended', (string)$obj->tc);
        $this->assertEquals('calledArg1', $obj->calledArg1);
        $this->assertEquals(73, $obj->calledArg2);
    }
}
