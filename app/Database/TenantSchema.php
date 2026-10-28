<?php

declare(strict_types=1);

namespace App\Database;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ForeignKeyDefinition;

/**
 * Migration helpers for the conventions in docs/database/db-schema.md section 1.
 *
 *   $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();   // always written out
 *   TenantSchema::key($table);                                  // UNIQUE(tenant_id, id): target of composite FKs
 *   TenantSchema::foreign($table, 'customer_id', 'customers');  // (tenant_id, customer_id) → customers(tenant_id, id)
 *   TenantSchema::unique($table, ['branch_id', 'code']);        // UNIQUE(tenant_id, branch_id, code)
 *   TenantSchema::index($table, ['branch_id', 'starts_at']);    // INDEX(tenant_id, branch_id, starts_at)
 *   TenantSchema::datetimes($table);                            // created_at / updated_at as DATETIME, not TIMESTAMP
 *
 * Index / constraint names are shortened to MySQL's 64-character limit.
 */
final class TenantSchema
{
    public static function key(Blueprint $table): void
    {
        $table->unique(['tenant_id', 'id'], self::name($table, ['tenant_id', 'id'], 'unique'));
    }

    /**
     * @param  string|list<string>  $columns
     * @param  string|list<string>  $references
     */
    public static function foreign(Blueprint $table, string|array $columns, string $on, string|array $references = 'id'): ForeignKeyDefinition
    {
        $columns = ['tenant_id', ...(array) $columns];
        $references = ['tenant_id', ...(array) $references];

        return $table->foreign($columns, self::name($table, $columns, 'tfk'))->references($references)->on($on);
    }

    /**
     * @param  string|list<string>  $columns
     */
    public static function unique(Blueprint $table, string|array $columns, ?string $name = null): void
    {
        $columns = ['tenant_id', ...(array) $columns];

        $table->unique($columns, $name ?? self::name($table, $columns, 'unique'));
    }

    /**
     * @param  string|list<string>  $columns
     */
    public static function index(Blueprint $table, string|array $columns, ?string $name = null): void
    {
        $columns = ['tenant_id', ...(array) $columns];

        $table->index($columns, $name ?? self::name($table, $columns, 'index'));
    }

    public static function datetimes(Blueprint $table): void
    {
        $table->dateTime('created_at')->nullable();
        $table->dateTime('updated_at')->nullable();
    }

    /**
     * @param  list<string>  $columns
     */
    public static function name(Blueprint $table, array $columns, string $suffix): string
    {
        $name = strtolower($table->getTable().'_'.implode('_', $columns).'_'.$suffix);

        return strlen($name) <= 64 ? $name : substr($name, 0, 55).'_'.substr(md5($name), 0, 8);
    }
}
