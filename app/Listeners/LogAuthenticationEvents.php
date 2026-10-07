<?php

namespace App\Listeners;

use App\Models\User;
use App\Support\SecurityLog;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Events\Dispatcher;

/**
 * Writes the framework's authentication events to the security log. Failed
 * attempts record the email that was tried (needed to spot guessing) but
 * never the password.
 */
class LogAuthenticationEvents
{
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'login',
            Logout::class => 'logout',
            Failed::class => 'failed',
            Lockout::class => 'lockout',
            PasswordReset::class => 'passwordReset',
        ];
    }

    public function login(Login $event): void
    {
        $this->log('login', $event->user);
    }

    public function logout(Logout $event): void
    {
        $this->log('logout', $event->user);
    }

    public function failed(Failed $event): void
    {
        $this->log('login failed', $event->user, ['email' => $event->credentials['email'] ?? null]);
    }

    public function lockout(Lockout $event): void
    {
        $this->log('login locked out', null, ['email' => $event->request->input('email')]);
    }

    public function passwordReset(PasswordReset $event): void
    {
        $this->log('password reset', $event->user);
    }

    private function log(string $what, mixed $user, array $properties = []): void
    {
        SecurityLog::record($what, $user instanceof User ? $user : null, $properties);
    }
}
