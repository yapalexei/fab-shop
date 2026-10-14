<?php

namespace App\Console\Commands\Demo;

use App\Enums\JobStageRole;
use App\Enums\LineItemType;
use App\Enums\QuoteStatus;
use App\Models\BusinessPartner;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Job;
use App\Models\JobMaterialUsage;
use App\Models\JobStage;
use App\Models\MarkupDefault;
use App\Models\Material;
use App\Models\MaterialType;
use App\Models\Plan;
use App\Models\Quote;
use App\Models\Service;
use App\Models\Shop;
use App\Models\Tool;
use App\Models\User;
use App\Support\ShopPermissions;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * One shop, a full team, and a full catalog - everything a quote line item
 * can reference (materials + suppliers, services + outsourced partners,
 * equipment, an internal employee with an hourly rate), used on a quote
 * that's already been won and converted to a job mid-progress - plus a few
 * months of invoiced work, jobs at other stations and open/stale/lost quotes
 * so the dashboard has something to show. Log in as
 * any of the printed users to explore the same shop from a different role.
 */
class SeedTeamJobDemo extends DemoSeedCommand
{
    protected $signature = 'demo:seed-team {--fresh : Delete this demo shop first if it already exists}';

    protected $description = '[DEMO] Seed one shop with a full team, catalog, and an in-progress job';

    private const SHOP_NAME = 'Ironclad Fabrication (Demo)';

    public function handle(): int
    {
        if (! $this->guardDemoMode()) {
            return self::FAILURE;
        }

        $existing = Shop::where('name', self::SHOP_NAME)->first();

        if ($existing && ! $this->option('fresh')) {
            $this->components->error('"'.self::SHOP_NAME.'" already exists. Re-run with --fresh to replace it.');

            return self::FAILURE;
        }

        if ($existing) {
            $this->components->task('Removing previous demo data', fn () => $this->deleteShopAndMembers($existing));
        }

        $this->call(PlanSeeder::class);

        DB::transaction(function () {
            $shop = $this->createShop();
            [$owner, $welder, $fitter, $admin] = $this->createTeam($shop);
            $customer = $this->createCustomer($shop);
            [$material, $partner] = $this->createMaterialCatalog($shop);
            [$service] = $this->createServiceCatalog($shop);
            $equipment = $this->createEquipment($shop);
            $this->createTooling($shop);
            $this->createMarkupDefaults($shop);
            $quote = $this->createWonQuote($shop, $customer, $material, $partner, $service, $welder, $equipment);
            $this->createInProgressJob($shop, $quote, $customer, $material);
            $this->createShopHistory($shop, $material);

            $this->printCredentials([
                ['name' => $owner->name, 'email' => $owner->email, 'role' => 'Owner'],
                ['name' => $admin->name, 'email' => $admin->email, 'role' => 'Admin (sees financials + settings)'],
                ['name' => $welder->name, 'email' => $welder->email, 'role' => 'Staff (welder, $32/hr)'],
                ['name' => $fitter->name, 'email' => $fitter->email, 'role' => 'Staff (fitter, $28/hr)'],
            ]);
        });

        $this->components->info('Seeded "'.self::SHOP_NAME.'" with a full catalog, an in-progress job, and six months of job/quote history.');

        return self::SUCCESS;
    }

    private function createShop(): Shop
    {
        $owner = User::factory()->create(['name' => 'Alex Rivera', 'email' => 'owner@ironclad.demo']);

        $shop = Shop::create([
            'name' => self::SHOP_NAME,
            'owner_id' => $owner->id,
            'plan_id' => Plan::current()->where('key', 'pro')->value('id'),
            'trial_ends_at' => null,
            'onboarded_at' => now(),
            'timezone' => 'America/Los_Angeles',
            'job_board_drag_enabled' => true,
            'quote_emailing_enabled' => true,
        ]);

        $owner->current_shop_id = $shop->id;
        $owner->save();

        $shop->members()->attach($owner);

        app(PermissionRegistrar::class)->setPermissionsTeamId($shop->id);
        // Owner/Admin/Staff for this shop, with Admin/Staff's default grants.
        ShopPermissions::seedRoles($shop);
        $owner->assignRole('Owner');

        return $shop;
    }

    /**
     * @return array{0: User, 1: User, 2: User, 3: User} owner, welder, fitter, admin
     */
    private function createTeam(Shop $shop): array
    {
        $owner = $shop->owner;

        $welder = User::factory()->create(['name' => 'Jamie Chen', 'email' => 'welder@ironclad.demo', 'current_shop_id' => $shop->id]);
        $fitter = User::factory()->create(['name' => 'Morgan Lee', 'email' => 'fitter@ironclad.demo', 'current_shop_id' => $shop->id]);
        $admin = User::factory()->create(['name' => 'Sam Patel', 'email' => 'admin@ironclad.demo', 'current_shop_id' => $shop->id]);

        $shop->members()->attach($welder, ['hourly_rate' => 32]);
        $shop->members()->attach($fitter, ['hourly_rate' => 28]);
        $shop->members()->attach($admin);

        app(PermissionRegistrar::class)->setPermissionsTeamId($shop->id);
        $welder->assignRole('Staff');
        $fitter->assignRole('Staff');
        $admin->assignRole('Admin');

        // Shows delegation off: on top of the Admin defaults, this shop's
        // owner has also trusted Admins with financials and shop settings.
        // Log in as admin@ to see those, as welder@/fitter@ (Staff) for the
        // counts-only dashboard and read-only catalog.
        Role::findOrCreate('Admin')->givePermissionTo(['financials.view', 'shop-settings.update']);

        return [$owner, $welder, $fitter, $admin];
    }

    private function createCustomer(Shop $shop): Customer
    {
        return Customer::factory()->for($shop)->create([
            'name' => 'Harbor Point Marine',
            'email' => 'orders@harborpointmarine.test',
            'phone' => '555-0142',
        ]);
    }

    /**
     * @return array{0: Material, 1: BusinessPartner}
     */
    private function createMaterialCatalog(Shop $shop): array
    {
        // Every shop is created with the built-in material types.
        $typeId = fn (string $name) => MaterialType::withoutGlobalScopes()->where('shop_id', $shop->id)->where('name', $name)->value('id');

        $material = Material::factory()->for($shop)->create(['name' => '1/4in Steel Plate', 'unit' => 'sheet', 'material_type_id' => $typeId('Carbon steel')]);
        Material::factory()->for($shop)->create(['name' => '2in Square Tube', 'unit' => 'ft', 'material_type_id' => $typeId('Carbon steel')]);
        Material::factory()->for($shop)->create(['name' => '1/8in Aluminum Sheet', 'unit' => 'sheet', 'material_type_id' => $typeId('Aluminum')]);

        $partner = BusinessPartner::factory()->for($shop)->create(['name' => 'Coastal Metal Supply']);
        $partner->materials()->attach($material->id, ['price' => 45.00]);

        return [$material, $partner];
    }

    /**
     * @return array{0: Service}
     */
    private function createServiceCatalog(Shop $shop): array
    {
        $service = Service::factory()->for($shop)->create(['name' => 'Powder Coating']);
        Service::factory()->for($shop)->create(['name' => 'Anodizing']);

        $partner = BusinessPartner::factory()->for($shop)->create(['name' => 'ProCoat Finishing']);
        $partner->services()->attach($service->id, ['price' => 85.00]);

        return [$service];
    }

    private function createEquipment(Shop $shop): Equipment
    {
        Equipment::factory()->for($shop)->create(['name' => 'Box Brake', 'billing_method' => 'flat', 'rate' => 75]);

        return Equipment::factory()->for($shop)->create(['name' => 'CNC Plasma Table', 'billing_method' => 'hourly', 'rate' => 45]);
    }

    /**
     * The same 1/4in bit twice - once rated for steel, once for aluminum -
     * to show why a tool records what it can cut, plus one custom material
     * type (on top of the built-ins every shop gets) and a tool rated for it.
     */
    private function createTooling(Shop $shop): void
    {
        $chromoly = new MaterialType(['name' => 'Chromoly (4130)']);
        $chromoly->shop_id = $shop->id;
        $chromoly->save();

        $tools = [
            ['1/4in cobalt drill bit', ['Carbon steel', 'Stainless steel', 'Chromoly (4130)'], 6.50, 'each'],
            ['1/4in drill bit', ['Aluminum'], 3.25, 'each'],
            ['2in bi-metal hole saw', ['Carbon steel', 'Aluminum', 'Wood'], 1.40, 'hole'],
            ['4.5in cut-off wheel', ['Carbon steel', 'Stainless steel'], 0.35, 'cut'],
        ];

        foreach ($tools as [$name, $cuts, $cost, $unit]) {
            Tool::factory()->for($shop)->cutting(...$cuts)->create(['name' => $name, 'cost' => $cost, 'unit' => $unit]);
        }
    }

    private function createMarkupDefaults(Shop $shop): void
    {
        $defaults = [LineItemType::Material->value => 25, LineItemType::Labor->value => 40, LineItemType::Equipment->value => 15, LineItemType::Tooling->value => 20];

        foreach ($defaults as $type => $percent) {
            (new MarkupDefault)->forceFill([
                'shop_id' => $shop->id,
                'line_item_type' => $type,
                'default_markup_percent' => $percent,
            ])->save();
        }
    }

    private function createWonQuote(
        Shop $shop,
        Customer $customer,
        Material $material,
        BusinessPartner $materialPartner,
        Service $service,
        User $welder,
        Equipment $equipment,
    ): Quote {
        $servicePartner = BusinessPartner::where('shop_id', $shop->id)->where('name', 'ProCoat Finishing')->firstOrFail();

        $quote = Quote::factory()->for($shop)->create([
            'customer_id' => $customer->id,
            'status' => QuoteStatus::Won,
            'sent_at' => now()->subDays(3),
        ]);

        $lines = [
            [
                'type' => LineItemType::Material, 'description' => $material->name,
                'quantity' => 10, 'unit_cost' => 45.00, 'markup_percent' => 25,
                'material_id' => $material->id, 'business_partner_id' => $materialPartner->id, 'sort_order' => 0,
            ],
            [
                'type' => LineItemType::Labor, 'description' => 'Welding - hull bracket assembly',
                'quantity' => 8, 'unit_cost' => 32.00, 'markup_percent' => 40,
                'user_id' => $welder->id, 'sort_order' => 1,
            ],
            [
                'type' => LineItemType::Labor, 'description' => $service->name,
                'quantity' => 1, 'unit_cost' => 85.00, 'markup_percent' => 40,
                'service_id' => $service->id, 'business_partner_id' => $servicePartner->id, 'sort_order' => 2,
            ],
            [
                'type' => LineItemType::Equipment, 'description' => 'CNC Plasma Table',
                'quantity' => 3, 'unit_cost' => 45.00, 'markup_percent' => 15,
                'equipment_id' => $equipment->id, 'sort_order' => 3,
            ],
        ];

        foreach ($lines as $line) {
            $quote->lineItems()->create($line);
        }

        return $quote;
    }

    private function createInProgressJob(Shop $shop, Quote $quote, Customer $customer, Material $material): Job
    {
        $jobNumber = 'J-'.str_pad((string) (Job::where('shop_id', $shop->id)->count() + 1), 4, '0', STR_PAD_LEFT);
        $setup = JobStage::query()->where('shop_id', $shop->id)->whereNull('role')->firstOrFail();

        $job = new Job;
        $job->forceFill([
            'shop_id' => $shop->id,
            'quote_id' => $quote->id,
            'customer_id' => $customer->id,
            'job_number' => $jobNumber,
            'job_stage_id' => $setup->id,
            // A real move out of Not started sets this (JobStatusController::
            // recordPeriod); seeding bypasses that, so set it here or the
            // job has no period and never appears on the timeline/calendar.
            'started_at' => now()->subDays(3),
        ])->save();

        activity()->performedOn($job)->causedBy($shop->owner)->log("Status changed to {$setup->name}");

        // Shows off material tracking (F5): the quote estimated 10 sheets at
        // $45 - the shop actually used 9 and scrapped 1 to a cutting error,
        // at a slightly higher real price than quoted.
        $usages = [
            ['description' => $material->name, 'unit' => $material->unit, 'quantity' => 9, 'unit_cost' => 47.50, 'type' => 'used'],
            ['description' => $material->name, 'unit' => $material->unit, 'quantity' => 1, 'unit_cost' => 47.50, 'type' => 'scrap', 'notes' => 'Mis-cut on the plasma table - replaced.'],
        ];

        foreach ($usages as $usage) {
            // Console context has no authenticated user, so BelongsToShop's
            // creating hook won't fill shop_id - set it explicitly, same as
            // the Job itself just above.
            (new JobMaterialUsage)->forceFill([
                ...$usage,
                'shop_id' => $shop->id,
                'job_id' => $job->id,
                'material_id' => $material->id,
                'recorded_by' => $shop->owner->id,
            ])->save();
        }

        return $job;
    }

    /**
     * Back-dated jobs and quotes across other customers, so the dashboard's
     * revenue trend, stage counts, "needs attention" list and win rate all
     * have realistic data rather than a single job.
     */
    private function createShopHistory(Shop $shop, Material $material): void
    {
        $customers = collect(['Ridgeline Ranch Supply', 'Oakmont HOA', 'Delgado Brewing Co.', 'Cascade Stair & Rail'])
            ->map(fn (string $name) => Customer::factory()->for($shop)->create(['name' => $name]));

        $stages = JobStage::query()->where('shop_id', $shop->id)->get();
        $notStarted = $stages->firstWhere('role', JobStageRole::NotStarted);
        $setup = $stages->first(fn (JobStage $stage) => $stage->role === null);
        $shipped = $stages->firstWhere('role', JobStageRole::Shipped);
        $invoiced = $stages->firstWhere('role', JobStageRole::Invoiced);

        // [customer index, stage, sell price, days ago it was sent/invoiced, cancelled]
        $jobs = [
            [0, $invoiced, 4200, 150, false],
            [1, $invoiced, 2800, 120, false],
            [2, $invoiced, 6100, 95, false],
            [3, $invoiced, 3900, 62, false],
            [0, $invoiced, 5200, 35, false],
            [1, $invoiced, 1850, 20, false],
            [2, $invoiced, 2400, 1, false],
            [3, $shipped, 3300, 4, false],
            [2, $setup, 2750, 9, false],
            [1, $notStarted, 1600, 1, false],
            [0, $notStarted, 900, 40, true],
        ];

        foreach ($jobs as [$customerIndex, $stage, $price, $daysAgo, $cancelled]) {
            $customer = $customers[$customerIndex];
            $quote = $this->pricedQuote($shop, $customer, $material, QuoteStatus::Won, $price, now()->subDays($daysAgo + 7));

            $job = new Job;
            $job->forceFill([
                'shop_id' => $shop->id,
                'quote_id' => $quote->id,
                'customer_id' => $customer->id,
                'job_number' => 'J-'.str_pad((string) (Job::where('shop_id', $shop->id)->count() + 1), 4, '0', STR_PAD_LEFT),
                'job_stage_id' => $stage->id,
                'invoiced_at' => $stage->role === JobStageRole::Invoiced ? now()->subDays($daysAgo) : null,
                'cancelled_at' => $cancelled ? now()->subDays($daysAgo) : null,
                ...$this->period($stage, $daysAgo),
            ])->save();

            $description = $cancelled ? 'Job cancelled' : "Status changed to {$stage->name}";

            activity()->performedOn($job)->causedBy($shop->owner)
                ->tap(fn ($activity) => $activity->created_at = now()->subDays($daysAgo))
                ->log($description);
        }

        // Open (one fresh, one stale enough to flag for follow-up) and lost quotes.
        $this->pricedQuote($shop, $customers[3], $material, QuoteStatus::Sent, 4800, now()->subDays(2));
        $this->pricedQuote($shop, $customers[1], $material, QuoteStatus::Sent, 2200, now()->subDays(12));
        $this->pricedQuote($shop, $customers[0], $material, QuoteStatus::Lost, 3100, now()->subDays(30));
        $this->pricedQuote($shop, $customers[2], $material, QuoteStatus::Lost, 1400, now()->subDays(50));
    }

    /**
     * started_at / finished_at for a seeded job, the way real moves would
     * have left them: a job that left Not started has a start, one that
     * reached Shipped (or Invoiced, which comes after) has a finish. Jobs
     * were worked for roughly one to two weeks - varied by $daysAgo so the
     * timeline's bars aren't all the same length. A job still in progress
     * started $daysAgo ago.
     *
     * @return array{started_at: ?Carbon, finished_at: ?Carbon}
     */
    private function period(JobStage $stage, int $daysAgo): array
    {
        if ($stage->role === JobStageRole::NotStarted) {
            return ['started_at' => null, 'finished_at' => null];
        }

        if ($stage->role === null) {
            return ['started_at' => now()->subDays($daysAgo), 'finished_at' => null];
        }

        // Invoiced happens a couple of days after shipping.
        $finished = now()->subDays($daysAgo + ($stage->role === JobStageRole::Invoiced ? 2 : 0));

        return ['started_at' => $finished->copy()->subDays(7 + $daysAgo % 8), 'finished_at' => $finished];
    }

    private function pricedQuote(Shop $shop, Customer $customer, Material $material, QuoteStatus $status, float $price, \DateTimeInterface $sentAt): Quote
    {
        $quote = Quote::factory()->for($shop)->create([
            'customer_id' => $customer->id,
            'status' => $status,
            'sent_at' => $sentAt,
        ]);

        // A single material line priced to land on exactly $price after the
        // shop's default 25% material markup.
        $quote->lineItems()->create([
            'type' => LineItemType::Material, 'description' => 'Fabricated assembly',
            'quantity' => 1, 'unit_cost' => round($price / 1.25, 2), 'markup_percent' => 25,
            'material_id' => $material->id, 'sort_order' => 0,
        ]);

        return $quote;
    }
}
