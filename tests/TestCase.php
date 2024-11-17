<?php

declare(strict_types=1);

namespace FiveOrbs\Registry\Tests;

use FiveOrbs\Registry\Registry;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class TestCase extends BaseTestCase
{
	public function __construct(?string $name = null, array $data = [], $dataName = '')
	{
		parent::__construct($name, $data, $dataName);
	}

	public function registry(
		bool $autowire = true,
	): Registry {
		$registry = new Registry(autowire: $autowire);
		$registry->add(Registry::class, $registry);

		return $registry;
	}

	public function throws(string $exception, string $message = null): void
	{
		$this->expectException($exception);

		if ($message) {
			$this->expectExceptionMessage($message);
		}
	}
}
