<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Authorization\PermissionCatalog;
use Illuminate\Console\Command;

class SyncPermissions extends Command
{
    protected $signature = 'permissions:sync';

    protected $description = 'Sync the permissions table with config/permissions.php (new permissions go to owner roles)';

    public function handle(): int
    {
        ['created' => $created, 'deleted' => $deleted] = PermissionCatalog::sync();

        $this->info(sprintf('Permissions synced: %d created, %d deleted.', count($created), count($deleted)));
        foreach ($created as $code) {
            $this->line("  + {$code}");
        }
        foreach ($deleted as $code) {
            $this->line("  - {$code}");
        }

        return self::SUCCESS;
    }
}
