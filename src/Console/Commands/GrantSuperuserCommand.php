<?php

declare(strict_types=1);

namespace Signl\Valkyrie\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Signl\Valkyrie\ValkyrieManager;

/**
 * Lockout guard: grants the configured superuser role with every ability. Idempotent.
 */
#[Signature('valkyrie:grant-superuser {email : Email address of the user}')]
#[Description('Grant the superuser role (every ability) to a user')]
final class GrantSuperuserCommand extends Command
{
    public function handle(ValkyrieManager $valkyrie): int
    {
        /** @var class-string<Model> $model */
        $model = config()->string('auth.providers.users.model');
        $email = (string) $this->argument('email');
        $user = $model::query()->where('email', $email)->first();

        if ($user === null) {
            $this->components->error("No user with email [{$email}].");

            return self::FAILURE;
        }

        $name = config()->string('valkyrie.superuser.role', 'system-admin');
        $role = $valkyrie->role($name, config()->string('valkyrie.superuser.title', 'System Administrator'));
        $valkyrie->allow($role)->everything();
        $valkyrie->assign($role, $user);

        $this->components->info("[{$email}] holds the {$name} role.");

        return self::SUCCESS;
    }
}
