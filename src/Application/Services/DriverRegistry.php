<?php

declare(strict_types=1);

namespace Hamzi\Synapse\Application\Services;

use Hamzi\Synapse\Domain\Contracts\SerialDriver;
use Hamzi\Synapse\Exceptions\SynapseException;
use Illuminate\Contracts\Container\Container;

final class DriverRegistry
{
    /**
     * @var array<string, string>
     */
    private array $drivers = [];

    public function __construct(private readonly Container $container)
    {
        foreach ((array) config('synapse.drivers', []) as $name => $class) {
            if (is_string($name) && is_string($class)) {
                $this->drivers[$name] = $class;
            }
        }
    }

    public function register(string $name, string $driverClass): void
    {
        $this->drivers[$name] = $driverClass;
    }

    public function resolve(string $name): SerialDriver
    {
        $className = $this->drivers[$name] ?? null;

        if ($className === null) {
            throw SynapseException::driverNotFound($name);
        }

        /** @var SerialDriver $driver */
        $driver = $this->container->make($className);
        $driver->configure((array) config("synapse.driver_options.{$name}", []));

        return $driver;
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->drivers;
    }
}
