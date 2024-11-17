<?php

declare(strict_types=1);

use PhpCsFixer\Finder;
use FiveOrbs\Development\PhpCsFixer\Config;

$finder = Finder::create()->in([__DIR__ . '/src', __DIR__ . '/tests']);
$config = new Config();

return $config->setFinder($finder);
