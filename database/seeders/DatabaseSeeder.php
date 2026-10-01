<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\FeatureSetting;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\User;
use App\Support\JobStages;
use App\Support\MaterialTypes;
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
        $this->call(PlanSeeder::class);
        FeatureSetting::ensureDefaults();

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $shop = Shop::create([
            'name' => "Test User's Shop",
            'owner_id' => $user->id,
            'plan_id' => Plan::where('key', 'free')->value('id'),
            'trial_ends_at' => now()->addMinutes(5),
            'onboarded_at' => now(),
        ]);

        // WithoutModelEvents suppresses Shop::created, which is what normally
        // gives a shop its material types and job board stages - do it here.
        MaterialTypes::seedDefaults($shop);
        JobStages::seedDefaults($shop);

        $shop->members()->attach($user);
        $user->update(['current_shop_id' => $shop->id]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($shop->id);
        foreach (['Owner', 'Admin', 'Staff'] as $role) {
            Role::findOrCreate($role);
        }
        $user->assignRole('Owner');

        Customer::factory()->for($shop)->create([
            'name' => 'Riverside Metal Co',
            'email' => 'orders@riversidemetal.test',
            'phone' => '555-0100',
        ]);
    }
}
