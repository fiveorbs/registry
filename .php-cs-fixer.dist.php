<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()->in([__DIR__ . '/src', __DIR__ . '/tests']);
$config = new Conia\Development\PhpCsFixer\Config();

return $config->setFinder($finder);
