<?php

declare(strict_types=1);

namespace FiveOrbs\Registry\Tests\Fixtures;

class TestClassWithConstructor
{
	public function __construct(public readonly TestClass $tc) {}
}
