<?php

declare(strict_types=1);

namespace FiveOrbs\Registry\Tests\Fixtures;

class TestClassRegistrySingleArg
{
	public function __construct(
		public readonly string $test,
	) {}
}
