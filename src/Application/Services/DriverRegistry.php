<?php

declare(strict_types=1);

namespace Hamzi\Synapse\Application\Services;

use Hamzi\Synapse\Domain\Contracts\SerialDriver;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

final class DriverRegistry
{
    /**
     * @var array<string, class-string<SerialDriver>>
     */
    private array $drivers = [];

    public function __construct(private readonly Container $container)
    {
        /** @var array<string, class-string<SerialDriver>> $configured */
        $configured = (array) config('synapse.drivers', []);
        $this->drivers = $configured;
    }

    public function register(string $name, string $driverClass): void
    {
        $this->drivers[$name] = $driverClass;
    }

    public function resolve(string $name): SerialDriver
    {
        $className = $this->drivers[$name] ?? null;

        if ($className === null) {
            throw new InvalidArgumentException("Synapse driver [{$name}] is not registered.");
        }

        /** @var SerialDriver $driver */
        $driver = $this->container->make($className);
        $driver->configure((array) config("synapse.driver_options.{$name}", []));

        return $driver;
    }

    /**
     * @return array<string, class-string<SerialDriver>>
     */
    public function all(): array
    {
        return $this->drivers;
    }
}
