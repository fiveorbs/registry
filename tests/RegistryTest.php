<?php

declare(strict_types=1);

namespace Conia\Registry\Tests;

use Closure;
use Conia\Registry\Exception\ContainerException;
use Conia\Registry\Exception\NotFoundException;
use Conia\Registry\Registry;
use Conia\Registry\Resolver;
use Conia\Registry\Tests\Fixtures\TestClass;
use Conia\Registry\Tests\Fixtures\TestClassApp;
use Conia\Registry\Tests\Fixtures\TestClassIntersectionTypeConstructor;
use Conia\Registry\Tests\Fixtures\TestClassRegistryArgs;
use Conia\Registry\Tests\Fixtures\TestClassRegistrySingleArg;
use Conia\Registry\Tests\Fixtures\TestClassUnionTypeConstructor;
use Conia\Registry\Tests\Fixtures\TestClassUntypedConstructor;
use Conia\Registry\Tests\Fixtures\TestClassWithConstructor;
use Conia\Registry\Tests\TestCase;
use ReflectionClass;
use ReflectionFunction;
use stdClass;

/**
 * @internal
 *
 * @covers \Conia\Registry\Call
 * @covers \Conia\Registry\Entry
 * @covers \Conia\Registry\Inject
 * @covers \Conia\Registry\Registry
 * @covers \Conia\Registry\Resolver
 */
final class RegistryTest extends TestCase
{
    public function testAddKeyWithoutValue(): void
    {
        $registry = new Registry();
        $registry->add(TestClassApp::class);

        $this->assertEquals(TestClassApp::class, $registry->entry(TestClassApp::class)->definition());
    }

    public function testEntryInstanceAndValue(): void
    {
        $registry = new Registry();
        $registry->add(stdClass::class);

        $this->assertEquals(stdClass::class, $registry->entry(stdClass::class)->definition());
        $this->assertEquals(null, $registry->entry(stdClass::class)->instance());
        $this->assertEquals(stdClass::class, $registry->entry(stdClass::class)->get());

        $obj = $registry->get(stdClass::class);

        $this->assertEquals(true, $obj instanceof stdClass);
        $this->assertEquals(stdClass::class, $registry->entry(stdClass::class)->definition());
        $this->assertEquals($obj, $registry->entry(stdClass::class)->instance());
        $this->assertEquals($obj, $registry->entry(stdClass::class)->get());
    }

    public function testCheckIfRegistered(): void
    {
        $registry = new Registry();
        $registry->add(Registry::class, $registry);

        $this->assertEquals(true, $registry->has(Registry::class));
        $this->assertEquals(false, $registry->has('registry'));
    }

    public function testInstantiate(): void
    {
        $registry = new Registry();
        $registry->add('registry', Registry::class);
        $registry->add('test', TestClass::class);
        $reg = $registry->new('registry');
        $req = $registry->new('test');

        $this->assertEquals(true, $reg instanceof Registry);
        $this->assertEquals(true, $req instanceof TestClass);
    }

    public function testInstantiateWithCall(): void
    {
        $registry = new Registry();
        $registry->add(TestClass::class)->call('init', value: 'testvalue');
        $tc = $registry->get(TestClass::class);

        $this->assertEquals(true, $tc instanceof TestClass);
        $this->assertEquals('testvalue', $tc->value);
    }

    public function testChainedInstantiation(): void
    {
        $registry = new Registry();
        $registry->add(
            \Psr\Container\ContainerExceptionInterface::class,
            \Psr\Container\NotFoundExceptionInterface::class
        );
        $registry->add(
            \Psr\Container\NotFoundExceptionInterface::class,
            NotFoundException::class
        );
        $exception = $registry->new(
            \Psr\Container\ContainerExceptionInterface::class,
            'The message',
            13
        );

        $this->assertEquals(true, $exception instanceof NotFoundException);
        $this->assertEquals('The message', $exception->getMessage());
        $this->assertEquals(13, $exception->getCode());
    }

    public function testFactoryMethodInstantiation(): void
    {
        $registry = new Registry();
        $registry->add(TestClassRegistryArgs::class)->constructor('fromDefaults');
        $instance = $registry->get(TestClassRegistryArgs::class);

        $this->assertEquals(true, $instance->tc instanceof TestClass);
        $this->assertEquals(true, $instance->app instanceof TestClassApp);
        $this->assertEquals('fromDefaults', $instance->app->app());
        $this->assertEquals('fromDefaults', $instance->test);
    }

    public function testFactoryMethodInstantiationWithArgs(): void
    {
        $registry = new Registry();
        $registry
            ->add(TestClassRegistryArgs::class)
            ->constructor('fromArgs')
            ->args(test: 'passed', app: 'passed');
        $instance = $registry->get(TestClassRegistryArgs::class);

        $this->assertEquals(true, $instance->tc instanceof TestClass);
        $this->assertEquals(true, $instance->app instanceof TestClassApp);
        $this->assertEquals('passed', $instance->app->app());
        $this->assertEquals('passed', $instance->test);
    }

    public function testAutowiredInstantiation(): void
    {
        $registry = new Registry();

        $this->assertEquals(true, $registry->new(NotFoundException::class) instanceof NotFoundException);
    }

    public function testAutowiredInstantiationFails(): void
    {
        $this->throws(NotFoundException::class, 'Cannot instantiate Conia\Registry\Tests\NoValidClass');

        $registry = new Registry();

        $this->assertEquals(true, $registry->new(NoValidClass::class) instanceof NotFoundException);
    }

    public function testResolveInstance(): void
    {
        $registry = new Registry();
        $object = new stdClass();
        $registry->add('object', $object);

        $this->assertEquals($object, $registry->get('object'));
    }

    public function testResolveSimpleClass(): void
    {
        $registry = new Registry();
        $registry->add('class', stdClass::class);

        $this->assertEquals(true, $registry->get('class') instanceof stdClass);
    }

    public function testResolveChainedEntry(): void
    {
        $registry = new Registry();
        $registry->add(
            Psr\Container\ContainerExceptionInterface::class,
            Psr\Container\NotFoundExceptionInterface::class
        );
        $registry->add(
            Psr\Container\NotFoundExceptionInterface::class,
            NotFoundException::class
        );

        $this->assertEquals(true, $registry->get(Psr\Container\ContainerExceptionInterface::class) instanceof NotFoundException);
    }

    public function testResolveClassWithConstructor(): void
    {
        $registry = new Registry();

        $object = $registry->get(TestClassWithConstructor::class);

        $this->assertEquals($object::class, TestClassWithConstructor::class);
        $this->assertEquals(TestClass::class, $object->tc::class);
    }

    public function testResolveClosureClass(): void
    {
        $registry = new Registry();
        $registry->add(TestClassApp::class, new TestClassApp('chuck'));
        $registry->add('class', function (TestClassApp $app) {
            return new TestClassRegistryArgs(new TestClass(), 'chuck', $app);
        });
        $instance = $registry->get('class');

        $this->assertEquals(true, $instance->tc instanceof TestClass);
        $this->assertEquals(true, $instance->app instanceof TestClassApp);
        $this->assertEquals('chuck', $instance->test);
    }

    public function testRejectClassWithUntypedConstructor(): void
    {
        $this->throws(ContainerException::class, 'typed constructor parameters');

        $registry = new Registry();

        $registry->get(TestClassUntypedConstructor::class);
    }

    public function testRejectClassWithUnsupportedConstructorUnionTypes(): void
    {
        $this->throws(ContainerException::class, 'union or intersection');

        $registry = new Registry();

        $registry->get(TestClassUnionTypeConstructor::class);
    }

    public function testRejectClassWithUnsupportedConstructorIntersectionTypes(): void
    {
        $this->throws(ContainerException::class, 'union or intersection');

        $registry = new Registry();

        $registry->get(TestClassIntersectionTypeConstructor::class);
    }

    public function testRejectUnresolvableClass(): void
    {
        $this->throws(ContainerException::class, 'Unresolvable');

        $registry = new Registry();

        $registry->get(GdImage::class);
    }

    public function testGettingNonExistentClassFails(): void
    {
        $this->throws(NotFoundException::class, 'NonExistent');

        $registry = new Registry();

        $registry->get('NonExistent');
    }

    public function testGettingNonResolvableEntryFails(): void
    {
        $this->throws(NotFoundException::class, 'Unresolvable id: Conia\Registry\Tests\InvalidClass');

        $registry = new Registry();
        $registry->add('unresolvable', InvalidClass::class);

        $registry->get('unresolvable');
    }

    public function testRejectingClassWithNonResolvableParams(): void
    {
        $this->throws(ContainerException::class, 'Unresolvable id: string');

        $registry = new Registry();
        $registry->add('unresolvable', TestClassRegistryArgs::class);

        $registry->get('unresolvable');
    }

    public function testResolveWithArgsArray(): void
    {
        $registry = new Registry();
        $registry->add('class', TestClassRegistryArgs::class)->args([
            'test' => 'chuck',
            'tc' => new TestClass(),
        ]);
        $instance = $registry->get('class');

        $this->assertEquals(true, $instance instanceof TestClassRegistryArgs);
        $this->assertEquals(true, $instance->tc instanceof TestClass);
        $this->assertEquals('chuck', $instance->test);
    }

    public function testResolveWithSingleNamedArgArray(): void
    {
        $registry = new Registry();
        $registry->add('class', TestClassRegistrySingleArg::class)->args(
            test: 'chuck',
        );
        $instance = $registry->get('class');

        $this->assertEquals(true, $instance instanceof TestClassRegistrySingleArg);
        $this->assertEquals('chuck', $instance->test);
    }

    public function testResolveWithNamedArgsArray(): void
    {
        $registry = new Registry();
        $registry->add('class', TestClassRegistryArgs::class)->args(
            test: 'chuck',
            tc: new TestClass(),
        );
        $instance = $registry->get('class');

        $this->assertEquals(true, $instance instanceof TestClassRegistryArgs);
        $this->assertEquals(true, $instance->tc instanceof TestClass);
        $this->assertEquals('chuck', $instance->test);
    }

    public function testResolveClosureClassWithArgs(): void
    {
        $registry = new Registry();
        $registry->add(TestClassApp::class, new TestClassApp('chuck'));
        $registry->add('class', function (TestClassApp $app, string $name, TestClass $tc) {
            return new TestClassRegistryArgs($tc, $name, $app);
        })->args(app: new TestClassApp('chuck'), tc: new TestClass(), name: 'chuck');
        $instance = $registry->get('class');

        $this->assertEquals(true, $instance->tc instanceof TestClass);
        $this->assertEquals(true, $instance->app instanceof TestClassApp);
        $this->assertEquals('chuck', $instance->test);
    }

    public function testResolveWithArgsClosure(): void
    {
        $registry = new Registry();
        $registry->add(TestClassApp::class, new TestClassApp('chuck'));
        $registry->add('class', TestClassRegistryArgs::class)->args(function (TestClassApp $app) {
            return [
                'test' => 'chuck',
                'tc' => new TestClass(),
                'app' => $app,
            ];
        });
        $instance = $registry->get('class');

        $this->assertEquals(true, $instance instanceof TestClassRegistryArgs);
        $this->assertEquals(true, $instance->tc instanceof TestClass);
        $this->assertEquals(true, $instance->app instanceof TestClassApp);
        $this->assertEquals('chuck', $instance->test);
    }

    public function testResolveClosureClassWithArgsClosure(): void
    {
        $registry = new Registry();
        $registry->add('class', function (TestClassApp $app, string $name, TestClass $tc) {
            return new TestClassRegistryArgs($tc, $name, $app);
        })->args(function () {
            return [
                'app' => new TestClassApp('chuck'),
                'tc' => new TestClass(),
                'name' => 'chuck',
            ];
        });
        $instance = $registry->get('class');

        $this->assertEquals(true, $instance instanceof TestClassRegistryArgs);
        $this->assertEquals(true, $instance->tc instanceof TestClass);
        $this->assertEquals(true, $instance->app instanceof TestClassApp);
        $this->assertEquals('chuck', $instance->test);
    }

    public function testRejectMultipleUnnamedArgs(): void
    {
        $this->throws(ContainerException::class, 'Registry entry arguments');

        $registry = new Registry();
        $registry->add('class', function () {
            return new stdClass();
        })->args('chuck', 13);
    }

    public function testRejectSingleUnnamedArgWithWrongType(): void
    {
        $this->throws(ContainerException::class, 'Registry entry arguments');

        $registry = new Registry();
        $registry->add('class', function () {
            return new stdClass();
        })->args('chuck');
    }

    public function testIsReified(): void
    {
        $registry = new Registry();
        $registry->add('class', stdClass::class);
        $obj1 = $registry->get('class');
        $obj2 = $registry->get('class');

        $this->assertEquals($obj1 === $obj2, true);
    }

    public function testAsIs(): void
    {
        $registry = new Registry();
        $registry->add('closure1', fn () => 'called');
        $registry->add('closure2', fn () => 'notcalled')->asIs();
        $value1 = $registry->get('closure1');
        $value2 = $registry->get('closure2');

        $this->assertEquals('called', $value1);
        $this->assertEquals(true, $value2 instanceof Closure);
    }

    public function testIsNotReified(): void
    {
        $registry = new Registry();
        $registry->add('class', stdClass::class)->reify(false);
        $obj1 = $registry->get('class');
        $obj2 = $registry->get('class');

        $this->assertEquals(false, $obj1 === $obj2);
    }

    public function testFetchEntriesList(): void
    {
        $registry = new Registry();
        $registry->add('class', stdClass::class)->reify(false);

        $this->assertEquals(['class'], $registry->entries());
        $this->assertEquals(
            ['Psr\Container\ContainerInterface', 'Conia\Registry\Registry', 'class'],
            $registry->entries(includeRegistry: true)
        );
    }

    public function testAddAndReceiveTaggedEntries(): void
    {
        $registry = new Registry();
        $registry->tag('tag')->add('class', stdClass::class);
        $obj = $registry->tag('tag')->get('class');
        $entry = $registry->tag('tag')->entry('class');

        $this->assertEquals(true, $obj instanceof stdClass);
        $this->assertEquals(stdClass::class, $entry->definition());
        $this->assertEquals(true, $obj === $entry->instance());
        $this->assertEquals(true, $obj === $entry->get());
        $this->assertEquals(true, $registry->tag('tag')->has('class'));
        $this->assertEquals(false, $registry->tag('tag')->has('wrong'));
        $this->assertEquals(false, $registry->has('class'));
    }

    public function testAddTaggedKeyWithoutValue(): void
    {
        $registry = new Registry();
        $registry->tag('tag')->add(TestClassApp::class);

        $this->assertEquals(TestClassApp::class, $registry->tag('tag')->entry(TestClassApp::class)->definition());
    }

    public function testParameterInfoClass(): void
    {
        $rc = new ReflectionClass(TestClassUnionTypeConstructor::class);
        $c = $rc->getConstructor();
        $p = $c->getParameters()[0];
        $resolver = new Resolver(new Registry());
        $s = 'Conia\Registry\Tests\Fixtures\TestClassUnionTypeConstructor::__construct(' .
            '..., Conia\Registry\Tests\Fixtures\TestClassApp|Conia\Registry\Tests\Fixtures\TestClassRequest $param, ...)';

        $this->assertEquals($s, $resolver->getParamInfo($p));
    }

    public function testParameterInfoFunction(): void
    {
        $rf = new ReflectionFunction(function (TestClassApp $app) {
            $app->debug();
        });
        $p = $rf->getParameters()[0];
        $resolver = new Resolver(new Registry());
        $s = 'Conia\Registry\Tests\RegistryTest::Conia\Registry\Tests\{closure}(..., Conia\Registry\Tests\Fixtures\TestClassApp $app, ...)';

        $this->assertEquals($s, $resolver->getParamInfo($p));
    }

    public function testGettingNonExistentTaggedEntryFails(): void
    {
        $this->throws(NotFoundException::class, 'Unresolvable id: NonExistent');

        $registry = new Registry();

        $registry->tag('tag')->get('NonExistent');
    }
}
