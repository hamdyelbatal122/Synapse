<?php

declare(strict_types=1);

namespace Hamzi\Synapse\Exceptions;

use RuntimeException;

final class SynapseException extends RuntimeException
{
    public static function driverNotFound(string $name): self
    {
        return new self("Synapse driver [{$name}] is not registered. Ensure it is listed under 'drivers' in config/synapse.php.");
    }

    public static function encodingFailed(string $driver, string $reason): self
    {
        return new self("Synapse driver [{$driver}] failed to encode payload: {$reason}");
    }
}
