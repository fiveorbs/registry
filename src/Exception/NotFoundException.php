<?php

declare(strict_types=1);

namespace FiveOrbs\Registry\Exception;

use Psr\Container\NotFoundExceptionInterface;

class NotFoundException extends ContainerException implements NotFoundExceptionInterface {}
