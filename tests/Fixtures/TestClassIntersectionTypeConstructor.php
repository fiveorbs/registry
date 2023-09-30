<?php

declare(strict_types=1);

namespace Conia\Registry\Tests\Fixtures;

class TestClassIntersectionTypeConstructor
{
    public function __construct(TestClassApp&TestClassRequest $param)
    {
    }
}
