<?php

declare(strict_types=1);

namespace FiveOrbs\Registry\Tests\Fixtures;

class TestClass implements TestInterface
{
	public function __construct(public string $value = '') {}

	public function __toString(): string
	{
		return 'Stringable';
	}

	public function __invoke(string $name = 'default', int $number = 13): string
	{
		return '';
	}

	public function test(): string
	{
		return '';
	}

	public function init(string $value): void
	{
		$this->value = $value;
	}
}
