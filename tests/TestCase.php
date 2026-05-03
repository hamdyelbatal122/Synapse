<?php

declare(strict_types=1);

namespace Hamzi\Synapse\Tests;

use Hamzi\Synapse\SynapseServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [SynapseServiceProvider::class];
    }
}
