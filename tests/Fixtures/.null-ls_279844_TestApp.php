<?php

declare(strict_types=1);

namespace Conia\Registry\Tests\Fixtures;

class TestApp
{
    /**
     * @param array<never, never>|array<string, mixed> -- Stores additional user defined settings
     */
    public function __construct(
        public readonly string $app = 'chuck',
        public readonly bool $debug = false,
        public readonly string $env = '',
    ) {
    }

    public function app(): string
    {
        return $this->app;
    }

    public function debug(): bool
    {
        return $this->debug;
    }

    public function env(): string
    {
        return $this->env;
    }
}
