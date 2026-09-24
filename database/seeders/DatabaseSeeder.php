<?php

namespace Database\Seeders;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $shop = Shop::create([
            'name' => "Test User's Shop",
            'owner_id' => $user->id,
        ]);

        $shop->members()->attach($user);
        $user->update(['current_shop_id' => $shop->id]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($shop->id);
        foreach (['Owner', 'Admin', 'Staff'] as $role) {
            Role::findOrCreate($role);
        }
        $user->assignRole('Owner');
    }
}
