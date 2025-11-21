<?php

declare(strict_types=1);

namespace Zupolgec\GDown;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Stringable;

/**
 * Simple logger that writes to STDERR
 * Used by CLI tool and when quiet=false with no custom logger
 */
class StderrLogger extends AbstractLogger
{
    public function log($level, string|Stringable $message, array $context = []): void
    {
        // Only output info and above (not debug)
        if ($level === LogLevel::DEBUG) {
            return;
        }
        
        fwrite(\STDERR, $message . "\n");
    }
}
