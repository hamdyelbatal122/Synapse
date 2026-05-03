<?php

declare(strict_types=1);

namespace Hamzi\Synapse\Facades;

use Illuminate\Support\Facades\Facade;

final class Synapse extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'synapse';
    }
}
