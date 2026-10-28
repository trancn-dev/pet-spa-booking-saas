<?php

declare(strict_types=1);

namespace App\Platform\Console;

use App\Enums\PlatformAdminRole;
use App\Platform\Actions\CreatePlatformAdmin;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * The only way to create the first platform admin (there is no admin sign-up page).
 * Later admins are invited from A6.
 */
class CreatePlatformAdminCommand extends Command
{
    protected $signature = 'platform:admin-create
        {email : Login email}
        {--role=super_admin : super_admin or support}
        {--name= : Display name (asked if omitted)}
        {--password= : Password, at least 12 characters (asked if omitted)}';

    protected $description = 'Create a platform admin; they must set up 2FA on first login at /admin/login';

    public function handle(CreatePlatformAdmin $create): int
    {
        $role = PlatformAdminRole::tryFrom((string) $this->option('role'));

        if ($role === null) {
            $this->error('Role must be one of: '.implode(', ', array_column(PlatformAdminRole::cases(), 'value')).'.');

            return self::FAILURE;
        }

        $name = (string) ($this->option('name') ?: $this->ask('Name'));
        $password = (string) ($this->option('password') ?: $this->secret('Password (min 12 characters)'));

        try {
            $admin = $create->handle($name, (string) $this->argument('email'), $password, $role);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }

            return self::FAILURE;
        }

        $this->info("Created {$role->value} {$admin->email}. Sign in at ".url('/admin/login').' and set up 2FA.');

        return self::SUCCESS;
    }
}
