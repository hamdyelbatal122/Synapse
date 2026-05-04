<?php

declare(strict_types=1);

namespace Hamzi\PortFlow\Domain\Events;

use Hamzi\PortFlow\Domain\Contracts\SerialEvent;

final class ProductScanned implements SerialEvent
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $barcode,
        public readonly array $context = [],
    ) {}
}
