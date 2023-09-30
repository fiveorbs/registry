<?php

declare(strict_types=1);

namespace Conia\Registry\Tests\Fixtures;

class TestClassResolver
{
    public function __construct(
        public readonly string $name,
        public readonly TestClass $tc,
        public readonly int $number
    ) {
    }
}
