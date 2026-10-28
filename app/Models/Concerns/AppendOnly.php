<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use LogicException;

/**
 * Ledgers and logs are append-only: a mistake is corrected by writing a new row.
 */
trait AppendOnly
{
    public static function bootAppendOnly(): void
    {
        static::updating(function (): void {
            throw new LogicException(static::class.' is append-only: write a new entry instead of updating.');
        });

        static::deleting(function (): void {
            throw new LogicException(static::class.' is append-only and can never be deleted.');
        });
    }
}
