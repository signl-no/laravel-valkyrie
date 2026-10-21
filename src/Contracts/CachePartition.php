<?php

declare(strict_types=1);

namespace Signl\Valkyrie\Contracts;

/**
 * Partitions ability cache keys. Return a distinct value per tenant when the
 * cache store is shared across tenants, so cached ability sets never leak.
 */
interface CachePartition
{
    public function key(): string;
}
