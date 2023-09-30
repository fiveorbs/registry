<?php

declare(strict_types=1);

namespace Conia\Registry\Tests\Fixtures;

class TestClassRegistryArgs
{
    public function __construct(
        public readonly TestClass $tc,
        public readonly string $test,
        public readonly ?TestConfig $config = null,
    ) {
    }

    public static function fromDefaults(): static
    {
        return new self(new TestClass(), 'fromDefaults', new TestConfig('fromDefaults'));
    }

    public static function fromArgs(TestClass $tc, string $test, string $app): static
    {
        return new self($tc, $test, new TestConfig($app));
    }
}
