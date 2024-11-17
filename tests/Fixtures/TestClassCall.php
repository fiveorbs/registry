<?php

declare(strict_types=1);

namespace FiveOrbs\Registry\Tests\Fixtures;

use FiveOrbs\Registry\Call;
use FiveOrbs\Registry\Registry;

#[Call('method1'), Call('method2', arg2: 'arg2', arg1: 'arg1')]
class TestClassCall
{
	public ?Registry $registry = null;
	public ?TestClassApp $app = null;
	public ?TestClassRequest $request = null;
	public string $arg1 = '';
	public string $arg2 = '';

	public function method1(Registry $registry, TestClassApp $app): void
	{
		$this->registry = $registry;
		$this->app = $app;
	}

	public function method2(string $arg1, TestClassRequest $request, string $arg2): void
	{
		$this->request = $request;
		$this->arg1 = $arg1;
		$this->arg2 = $arg2;
	}
}
