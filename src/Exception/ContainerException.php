<?php

declare(strict_types=1);

namespace FiveOrbs\Registry\Exception;

use Psr\Container\ContainerExceptionInterface;
use RuntimeException;

class ContainerException extends RuntimeException implements ContainerExceptionInterface {}
