<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

declare(strict_types=1);

namespace ILIAS\ApiGateway\Logging;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use ilLogger;
use ilLogLevel;
use Override;
use Stringable;

readonly class WebserviceLogger implements LoggerInterface
{
    public function __construct(private ilLogger $logger)
    {
    }

    #[Override]
    public function emergency(string|Stringable $message, array $context = []): void
    {
        $this->logger->emergency((string) $message, $context);
    }

    #[Override]
    public function alert(string|Stringable $message, array $context = []): void
    {
        $this->logger->alert((string) $message, $context);
    }

    #[Override]
    public function critical(string|Stringable $message, array $context = []): void
    {
        $this->logger->critical((string) $message, $context);
    }

    #[Override]
    public function error(string|Stringable $message, array $context = []): void
    {
        $this->logger->error((string) $message, $context);
    }

    #[Override]
    public function warning(string|Stringable $message, array $context = []): void
    {
        $this->logger->warning((string) $message, $context);
    }

    #[Override]
    public function notice(string|Stringable $message, array $context = []): void
    {
        $this->logger->notice((string) $message, $context);
    }

    #[Override]
    public function info(string|Stringable $message, array $context = []): void
    {
        $this->logger->info((string) $message, $context);
    }

    #[Override]
    public function debug(string|Stringable $message, array $context = []): void
    {
        $this->logger->debug((string) $message, $context);
    }

    #[Override]
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $ilLevel = $this->mapPsrToIlLogLevel($level);

        $this->logger->log((string) $message, $ilLevel, $context);
    }

    /**
     * Maps a PSR-3 log level string to an ilLogLevel integer.
     *
     * @param mixed $psrLevel The PSR-3 log level string (e.g., 'error', 'info').
     * @return int The corresponding ilLogLevel integer.
     */
    private function mapPsrToIlLogLevel($psrLevel): int
    {
        $levelAsString = '';

        if (is_string($psrLevel)) {
            $levelAsString = $psrLevel;
        } elseif (is_scalar($psrLevel)) { // Handles int, float, bool
            $levelAsString = (string) $psrLevel;
        } elseif (is_object($psrLevel) && method_exists($psrLevel, '__toString')) {
            $levelAsString = (string) $psrLevel;
        }

        return match ($levelAsString) {
            LogLevel::EMERGENCY => ilLogLevel::EMERGENCY,
            LogLevel::ALERT => ilLogLevel::ALERT,
            LogLevel::CRITICAL => ilLogLevel::CRITICAL,
            LogLevel::ERROR => ilLogLevel::ERROR,
            LogLevel::WARNING => ilLogLevel::WARNING,
            LogLevel::NOTICE => ilLogLevel::NOTICE,
            LogLevel::INFO => ilLogLevel::INFO,
            LogLevel::DEBUG => ilLogLevel::DEBUG,
            default => ilLogLevel::INFO, // Fallback for unknown levels
        };
    }
}
