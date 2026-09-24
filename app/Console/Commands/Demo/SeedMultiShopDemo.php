<?php

namespace App\Console\Commands\Demo;

use App\Enums\LineItemType;
use App\Enums\QuoteStatus;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Quote;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Two independent shops, each with their own customer/quote, to click
 * through tenant isolation - log in as either shop's owner and you'll only
 * ever see that shop's data. One extra user (the "roamer") is a Staff
 * member of both shops and can flip between them with the shop switcher in
 * the nav, seeing each shop's data change as they do.
 */
class SeedMultiShopDemo extends DemoSeedCommand
{
    protected $signature = 'demo:seed-multi-shop {--fresh : Delete these demo shops first if they already exist}';

    protected $description = '[DEMO] Seed two isolated shops plus a user who belongs to both';

    private const SHOP_A_NAME = 'Bayview Welding (Demo)';

    private const SHOP_B_NAME = 'Summit Ornamental Iron (Demo)';

    public function handle(): int
    {
        if (! $this->guardDemoMode()) {
            return self::FAILURE;
        }

        $existingA = Shop::where('name', self::SHOP_A_NAME)->first();
        $existingB = Shop::where('name', self::SHOP_B_NAME)->first();

        if (($existingA || $existingB) && ! $this->option('fresh')) {
            $this->components->error('These demo shops already exist. Re-run with --fresh to replace them.');

            return self::FAILURE;
        }

        if ($existingA) {
            $this->components->task('Removing previous "'.self::SHOP_A_NAME.'"', fn () => $this->deleteShopAndMembers($existingA));
        }
        if ($existingB) {
            $this->components->task('Removing previous "'.self::SHOP_B_NAME.'"', fn () => $this->deleteShopAndMembers($existingB));
        }

        $this->call(PlanSeeder::class);

        DB::transaction(function () {
            $shopA = $this->createShopWithData(self::SHOP_A_NAME, 'bayview-owner@demo.test', 'Taylor Brooks', 'Northshore Fencing Co', 'Aluminum fence panel repair');
            $shopB = $this->createShopWithData(self::SHOP_B_NAME, 'summit-owner@demo.test', 'Casey Nguyen', 'Ridgeline HOA', 'Custom wrought iron gate');

            $roamer = User::factory()->create([
                'name' => 'Robin Ortiz',
                'email' => 'roamer@demo.test',
                'current_shop_id' => $shopA->id,
            ]);

            $shopA->members()->attach($roamer);
            $shopB->members()->attach($roamer);

            app(PermissionRegistrar::class)->setPermissionsTeamId($shopA->id);
            $roamer->assignRole('Staff');
            app(PermissionRegistrar::class)->setPermissionsTeamId($shopB->id);
            $roamer->assignRole('Staff');

            $this->printCredentials([
                ['name' => 'Taylor Brooks', 'email' => 'bayview-owner@demo.test', 'role' => 'Owner of '.self::SHOP_A_NAME],
                ['name' => 'Casey Nguyen', 'email' => 'summit-owner@demo.test', 'role' => 'Owner of '.self::SHOP_B_NAME],
                ['name' => $roamer->name, 'email' => $roamer->email, 'role' => 'Staff in both - use the shop switcher in the nav'],
            ]);
        });

        $this->components->info('Seeded two isolated demo shops and a user who belongs to both.');

        return self::SUCCESS;
    }

    private function createShopWithData(string $shopName, string $ownerEmail, string $ownerName, string $customerName, string $lineDescription): Shop
    {
        $owner = User::factory()->create(['name' => $ownerName, 'email' => $ownerEmail]);

        $shop = Shop::create([
            'name' => $shopName,
            'owner_id' => $owner->id,
            'plan_id' => Plan::where('key', 'start-up')->value('id'),
            'trial_ends_at' => null,
        ]);

        $owner->current_shop_id = $shop->id;
        $owner->save();

        $shop->members()->attach($owner);

        app(PermissionRegistrar::class)->setPermissionsTeamId($shop->id);
        foreach (['Owner', 'Admin', 'Staff'] as $role) {
            Role::findOrCreate($role);
        }
        $owner->assignRole('Owner');

        $customer = Customer::factory()->for($shop)->create(['name' => $customerName]);

        $quote = Quote::factory()->for($shop)->create([
            'customer_id' => $customer->id,
            'status' => QuoteStatus::Draft,
        ]);

        $quote->lineItems()->create([
            'type' => LineItemType::Material,
            'description' => $lineDescription,
            'quantity' => 1,
            'unit_cost' => 250,
            'markup_percent' => 20,
            'sort_order' => 0,
        ]);

        return $shop;
    }
}
