<?php

declare(strict_types=1);

namespace Hamzi\PortFlow\Exceptions;

use RuntimeException;

final class PortFlowException extends RuntimeException
{
    public static function driverNotFound(string $name): self
    {
        return new self("PortFlow driver [{$name}] is not registered. Ensure it is listed under 'drivers' in config/portflow.php.");
    }

    public static function encodingFailed(string $driver, string $reason): self
    {
        return new self("PortFlow driver [{$driver}] failed to encode payload: {$reason}");
    }

    public static function invalidDriver(string $name, string $reason): self
    {
        return new self("PortFlow configuration error — driver [{$name}]: {$reason}.");
    }

    public static function invalidConfiguration(string $reason): self
    {
        return new self("PortFlow configuration error: {$reason}.");
    }
}
