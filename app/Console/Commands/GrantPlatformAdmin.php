<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:grant-platform-admin {email}')]
#[Description('Grant the platform admin console flag to a user by email')]
class GrantPlatformAdmin extends Command
{
    /**
     * is_platform_admin is deliberately not fillable (see its migration) -
     * this command, direct forceFill, or tinker are the only ways to grant
     * it, on purpose. There is no in-app way to self-elevate.
     */
    public function handle(): int
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("No user found with email {$email}.");

            return self::FAILURE;
        }

        $user->forceFill(['is_platform_admin' => true])->save();

        $this->info("Granted platform admin access to {$user->name} ({$user->email}).");

        return self::SUCCESS;
    }
}
