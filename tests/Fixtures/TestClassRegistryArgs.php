<?php

declare(strict_types=1);

namespace Conia\Registry\Tests\Fixtures;

class TestClassRegistryArgs
{
    public function __construct(
        public readonly TestClass $tc,
        public readonly string $test,
        public readonly ?TestClassApp $app = null,
    ) {
    }

    public static function fromDefaults(): static
    {
        return new self(new TestClass(), 'fromDefaults', new TestClassApp('fromDefaults'));
    }

    public static function fromArgs(TestClass $tc, string $test, string $app): static
    {
        return new self($tc, $test, new TestClassApp($app));
    }
}
