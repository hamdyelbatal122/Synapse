<?php

declare(strict_types=1);

namespace Hamzi\Synapse\Domain\Events;

final class ProductScanned
{
    public function __construct(
        public readonly string $barcode,
        public readonly array $context = [],
    ) {}
}
