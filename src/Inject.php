<?php

declare(strict_types=1);

namespace Conia\Registry;

use Attribute;
use Conia\Registry\Exception\ContainerException;

/** @psalm-api */
#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD)]
class Inject
{
    public array $args;

    public function __construct(mixed ...$args)
    {
        if (count($args) > 0) {
            if (is_int(array_key_first($args))) {
                throw new ContainerException('Arguments for Inject must be named arguments');
            }
        }

        $this->args = $args;
    }
}
