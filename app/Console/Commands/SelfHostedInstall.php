<?php

namespace App\Console\Commands;

use App\Actions\InstallSelfHostedShop;
use App\Support\Edition;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

#[Signature('selfhosted:install {--name= : Owner name} {--email= : Owner email} {--password= : Owner password} {--shop= : Shop name} {--timezone= : IANA timezone, e.g. America/Chicago}')]
#[Description('Create the owner account and the shop for a self-hosted install (the same thing the /setup page does)')]
class SelfHostedInstall extends Command
{
    public function handle(InstallSelfHostedShop $install): int
    {
        if (! Edition::selfHosted()) {
            $this->components->error('Refusing to run: this is not a self-hosted install (set APP_MODE=selfhosted).');

            return self::FAILURE;
        }

        if (Edition::installed()) {
            $this->components->error('This install is already set up.');

            return self::FAILURE;
        }

        $password = $this->option('password') ?? $this->secret('Password');

        $data = InstallSelfHostedShop::prepare([
            'name' => $this->option('name') ?? $this->ask('Your name'),
            'email' => $this->option('email') ?? $this->ask('Your email'),
            'password' => $password,
            // The command takes the password once; the web form asks twice.
            'password_confirmation' => $password,
            'shop_name' => $this->option('shop') ?? $this->ask('Shop name'),
            'timezone' => $this->option('timezone') ?? config('shop.defaults.timezone'),
        ]);

        $validator = Validator::make($data, InstallSelfHostedShop::rules());

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user = $install([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'shop_name' => $data['shop_name'],
            'timezone' => $data['timezone'],
        ]);

        $this->components->info("Created {$user->email} as the owner of {$data['shop_name']}. Sign in to finish setting up.");

        return self::SUCCESS;
    }
}
