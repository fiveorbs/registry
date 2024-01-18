<?php

declare(strict_types=1);

namespace Conia\Registry\Tests;

use Closure;
use Conia\Registry\Entry;
use Conia\Registry\Exception\ContainerException;
use Conia\Registry\Exception\NotFoundException;
use Conia\Registry\Registry;
use Conia\Registry\Tests\Fixtures\TestClass;
use Conia\Registry\Tests\Fixtures\TestClassApp;
use Conia\Registry\Tests\Fixtures\TestClassRegistryArgs;
use Conia\Registry\Tests\Fixtures\TestClassRegistrySingleArg;
use Conia\Registry\Tests\Fixtures\TestClassWithConstructor;
use Conia\Registry\Tests\TestCase;
use stdClass;

final class RegistryTest extends TestCase
{
    public function testAddKeyWithoutValue(): void
    {
        $registry = new Registry();
        $registry->add('value');

        $this->assertSame('value', $registry->entry('value')->definition());
    }

    public function testAddEntryInstance(): void
    {
        $registry = new Registry();
        $registry->addEntry(new Entry('key', 'value'));

        $this->assertSame('value', $registry->entry('key')->definition());
    }

    public function testEntryInstanceAndValue(): void
    {
        $registry = new Registry();
        $registry->add(stdClass::class);

        $this->assertSame(stdClass::class, $registry->entry(stdClass::class)->definition());
        $this->assertSame(null, $registry->entry(stdClass::class)->instance());
        $this->assertSame(stdClass::class, $registry->entry(stdClass::class)->get());

        $obj = $registry->get(stdClass::class);

        $this->assertSame(true, $obj instanceof stdClass);
        $this->assertSame(stdClass::class, $registry->entry(stdClass::class)->definition());
        $this->assertSame($obj, $registry->entry(stdClass::class)->instance());
        $this->assertSame($obj, $registry->entry(stdClass::class)->get());
    }

    public function testCheckIfRegistered(): void
    {
        $registry = new Registry();
        $registry->add('registry', $registry);

        $this->assertSame(true, $registry->has('registry'));
        $this->assertSame(false, $registry->has('wrong'));
    }

    public function testCheckIfRegisteredOnParentFromTag(): void
    {
        $registry = new Registry();
        $registry->add('registry', $registry);
        $tag = $registry->tag('test');

        $this->assertSame(true, $tag->has('registry'));
        $this->assertSame(false, $tag->has('wrong'));
    }

    public function testInstantiate(): void
    {
        $registry = new Registry();
        $registry->add('registry', Registry::class);
        $registry->add('test', TestClass::class);
        $reg = $registry->new('registry');
        $req = $registry->new('test');

        $this->assertSame(true, $reg instanceof Registry);
        $this->assertSame(true, $req instanceof TestClass);
    }

    public function testInstantiateWithCall(): void
    {
        $registry = new Registry();
        $registry->add(TestClass::class)->call('init', value: 'testvalue');
        $tc = $registry->get(TestClass::class);

        $this->assertSame(true, $tc instanceof TestClass);
        $this->assertSame('testvalue', $tc->value);
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

        $this->assertSame(true, $exception instanceof NotFoundException);
        $this->assertSame('The message', $exception->getMessage());
        $this->assertSame(13, $exception->getCode());
    }

    public function testFactoryMethodInstantiation(): void
    {
        $registry = new Registry();
        $registry->add(TestClassRegistryArgs::class)->constructor('fromDefaults');
        $instance = $registry->get(TestClassRegistryArgs::class);

        $this->assertSame(true, $instance->tc instanceof TestClass);
        $this->assertSame(true, $instance->app instanceof TestClassApp);
        $this->assertSame('fromDefaults', $instance->app->app());
        $this->assertSame('fromDefaults', $instance->test);
    }

    public function testFactoryMethodInstantiationWithArgs(): void
    {
        $registry = new Registry();
        $registry
            ->add(TestClassRegistryArgs::class)
            ->constructor('fromArgs')
            ->args(test: 'passed', app: 'passed');
        $instance = $registry->get(TestClassRegistryArgs::class);

        $this->assertSame(true, $instance->tc instanceof TestClass);
        $this->assertSame(true, $instance->app instanceof TestClassApp);
        $this->assertSame('passed', $instance->app->app());
        $this->assertSame('passed', $instance->test);
    }

    public function testAutowiredInstantiation(): void
    {
        $registry = new Registry();

        $this->assertSame(true, $registry->new(NotFoundException::class) instanceof NotFoundException);
    }

    public function testAutowiredInstantiationFails(): void
    {
        $this->throws(NotFoundException::class, 'Cannot instantiate Conia\Registry\Tests\NoValidClass');

        $registry = new Registry();

        $this->assertSame(true, $registry->new(NoValidClass::class) instanceof NotFoundException);
    }

    public function testResolveInstance(): void
    {
        $registry = new Registry();
        $object = new stdClass();
        $registry->add('object', $object);

        $this->assertSame($object, $registry->get('object'));
    }

    public function testResolveSimpleClass(): void
    {
        $registry = new Registry();
        $registry->add('class', stdClass::class);

        $this->assertSame(true, $registry->get('class') instanceof stdClass);
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

        $this->assertSame(
            true,
            $registry->get(Psr\Container\ContainerExceptionInterface::class) instanceof NotFoundException
        );
    }

    public function testResolveClassWithConstructor(): void
    {
        $registry = new Registry();

        $object = $registry->get(TestClassWithConstructor::class);

        $this->assertSame($object::class, TestClassWithConstructor::class);
        $this->assertSame(TestClass::class, $object->tc::class);
    }

    public function testResolveClosureClass(): void
    {
        $registry = new Registry();
        $registry->add(TestClassApp::class, new TestClassApp('chuck'));
        $registry->add('class', function (TestClassApp $app) {
            return new TestClassRegistryArgs(new TestClass(), 'chuck', $app);
        });
        $instance = $registry->get('class');

        $this->assertSame(true, $instance->tc instanceof TestClass);
        $this->assertSame(true, $instance->app instanceof TestClassApp);
        $this->assertSame('chuck', $instance->test);
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

    public function testGettingNonResolvableAutowiringFails(): void
    {
        $this->throws(
            NotFoundException::class,
            'Unresolvable id: Conia\Registry\Tests\Fixtures\TestClassRegistryArgs'
        );

        $registry = new Registry(autowire: true);
        $registry->get(TestClassRegistryArgs::class);
    }

    public function testRejectingClassWithNonResolvableParams(): void
    {
        $this->throws(NotFoundException::class, 'Unresolvable:');

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

        $this->assertSame(true, $instance instanceof TestClassRegistryArgs);
        $this->assertSame(true, $instance->tc instanceof TestClass);
        $this->assertSame('chuck', $instance->test);
    }

    public function testResolveWithSingleNamedArgArray(): void
    {
        $registry = new Registry();
        $registry->add('class', TestClassRegistrySingleArg::class)->args(
            test: 'chuck',
        );
        $instance = $registry->get('class');

        $this->assertSame(true, $instance instanceof TestClassRegistrySingleArg);
        $this->assertSame('chuck', $instance->test);
    }

    public function testResolveWithNamedArgsArray(): void
    {
        $registry = new Registry();
        $registry->add('class', TestClassRegistryArgs::class)->args(
            test: 'chuck',
            tc: new TestClass(),
        );
        $instance = $registry->get('class');

        $this->assertSame(true, $instance instanceof TestClassRegistryArgs);
        $this->assertSame(true, $instance->tc instanceof TestClass);
        $this->assertSame('chuck', $instance->test);
    }

    public function testResolveClosureClassWithArgs(): void
    {
        $registry = new Registry();
        $registry->add(TestClassApp::class, new TestClassApp('chuck'));
        $registry->add('class', function (TestClassApp $app, string $name, TestClass $tc) {
            return new TestClassRegistryArgs($tc, $name, $app);
        })->args(app: new TestClassApp('chuck'), tc: new TestClass(), name: 'chuck');
        $instance = $registry->get('class');

        $this->assertSame(true, $instance->tc instanceof TestClass);
        $this->assertSame(true, $instance->app instanceof TestClassApp);
        $this->assertSame('chuck', $instance->test);
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

        $this->assertSame(true, $instance instanceof TestClassRegistryArgs);
        $this->assertSame(true, $instance->tc instanceof TestClass);
        $this->assertSame(true, $instance->app instanceof TestClassApp);
        $this->assertSame('chuck', $instance->test);
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

        $this->assertSame(true, $instance instanceof TestClassRegistryArgs);
        $this->assertSame(true, $instance->tc instanceof TestClass);
        $this->assertSame(true, $instance->app instanceof TestClassApp);
        $this->assertSame('chuck', $instance->test);
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

        $this->assertSame($obj1 === $obj2, true);
    }

    public function testAsIs(): void
    {
        $registry = new Registry();
        $registry->add('closure1', fn () => 'called');
        $registry->add('closure2', fn () => 'notcalled')->asIs();
        $value1 = $registry->get('closure1');
        $value2 = $registry->get('closure2');

        $this->assertSame('called', $value1);
        $this->assertSame(true, $value2 instanceof Closure);
    }

    public function testIsNotReified(): void
    {
        $registry = new Registry();
        $registry->add('class', stdClass::class)->reify(false);
        $obj1 = $registry->get('class');
        $obj2 = $registry->get('class');

        $this->assertSame(false, $obj1 === $obj2);
    }

    public function testFetchEntriesList(): void
    {
        $registry = new Registry();
        $registry->add('class', stdClass::class)->reify(false);

        $this->assertSame(['class'], $registry->entries());
        $this->assertSame(
            ['Psr\Container\ContainerInterface', 'Conia\Registry\Registry', 'class'],
            $registry->entries(includeRegistry: true)
        );
    }

    public function testAddAndReceiveTaggedEntries(): void
    {
        $registry = new Registry();
        $registry->tag('tag')->add('class', stdClass::class);
        $registry->tag('tag')->add('registry', Registry::class);
        $obj = $registry->tag('tag')->get('class');
        $entry = $registry->tag('tag')->entry('class');
        $entryReg = $registry->tag('tag')->entry('registry');

        $this->assertSame(['class', 'registry'], $registry->tag('tag')->entries());
        $this->assertSame([
            'Psr\Container\ContainerInterface',
            'Conia\Registry\Registry',
            'class',
            'registry',
        ], $registry->tag('tag')->entries(true));
        $this->assertSame(true, $obj instanceof stdClass);
        $this->assertSame(stdClass::class, $entry->definition());
        $this->assertSame(Registry::class, $entryReg->definition());
        $this->assertSame(true, $obj === $entry->instance());
        $this->assertSame(true, $obj === $entry->get());
        $this->assertSame(true, $registry->tag('tag')->has('class'));
        $this->assertSame(true, $registry->tag('tag')->has('registry'));
        $this->assertSame(false, $registry->tag('tag')->has('wrong'));
        $this->assertSame(false, $registry->has('class'));
        $this->assertSame(false, $registry->has('registry'));
    }

    public function testAddTaggedKeyWithoutValue(): void
    {
        $registry = new Registry();
        $registry->tag('tag')->add(TestClassApp::class);

        $this->assertSame(TestClassApp::class, $registry->tag('tag')->entry(TestClassApp::class)->definition());
    }

    public function testGettingNonExistentTaggedEntryFails(): void
    {
        $this->throws(NotFoundException::class, 'Unresolvable id: NonExistent');

        $registry = new Registry();

        $registry->tag('tag')->get('NonExistent');
    }
}
