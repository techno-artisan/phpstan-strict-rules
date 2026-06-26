<?php

declare(strict_types=1);

use Nette\Neon\Exception as NeonException;
use Nette\Neon\Neon;

require __DIR__ . '/../vendor/autoload.php';

$files = [
    __DIR__ . '/../rules.neon',
    __DIR__ . '/../phpstan.neon',
];

$failed = false;

foreach ($files as $file) {
    try {
        Neon::decode((string) file_get_contents($file));
    } catch (NeonException $e) {
        fwrite(STDERR, $file . ': ' . $e->getMessage() . "\n");
        $failed = true;
    }
}

exit($failed ? 1 : 0);
