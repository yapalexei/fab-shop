<?php

use App\Enums\Feature;
use App\Enums\JobStatus;
use App\Models\BusinessPartner;
use App\Models\Job;
use App\Models\JobMaterialUsage;
use App\Models\Material;
use App\Models\Plan;
use App\Models\Shop;

/**
 * Creates a usage row directly (bypassing the HTTP endpoint) for test setup
 * - BelongsToShop only auto-fills shop_id from an authenticated request, so
 * outside one, forceFill it explicitly, same as MarkupDefault's own tests do.
 */
function usageFor(Job $job, array $attributes): JobMaterialUsage
{
    $usage = new JobMaterialUsage;
    $usage->forceFill(['shop_id' => $job->shop_id, 'job_id' => $job->id, ...$attributes]);
    $usage->save();

    return $usage;
}

test('logging material usage records the entry against the job', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);
    $job = jobForShop($shop, JobStatus::Cutting);

    $this->actingAs($user)->post(route('jobs.material-usage.store', $job), [
        'description' => '1/4in Steel Plate', 'quantity' => 3, 'unit' => 'sheet',
        'unit_cost' => 45, 'type' => 'used',
    ])->assertRedirect();

    $usage = $job->materialUsages()->sole();
    expect($usage->shop_id)->toBe($shop->id)
        ->and($usage->description)->toBe('1/4in Steel Plate')
        ->and($usage->recorded_by)->toBe($user->id)
        ->and($usage->cost)->toBe(135.0);
});

test('logging material usage records a matching activity entry', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);
    $job = jobForShop($shop, JobStatus::Cutting);

    $this->actingAs($user)->post(route('jobs.material-usage.store', $job), [
        'description' => 'Scrap plate', 'quantity' => 1, 'unit_cost' => 20, 'type' => 'scrap',
    ]);

    expect($job->activitiesAsSubject()->latest()->first()->description)
        ->toContain('Scrap plate')
        ->toContain('Scrap');
});

test('a material usage entry can reference a catalog material', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);
    $job = jobForShop($shop, JobStatus::Cutting);
    $material = Material::factory()->for($shop)->create();

    $this->actingAs($user)->post(route('jobs.material-usage.store', $job), [
        'material_id' => $material->id, 'description' => $material->name,
        'quantity' => 1, 'unit_cost' => 10, 'type' => 'used',
    ])->assertSessionHasNoErrors();

    expect($job->materialUsages()->sole()->material_id)->toBe($material->id);
});

test('a usage entry cannot reference another shop\'s material', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);
    $job = jobForShop($shop, JobStatus::Cutting);
    $foreignMaterial = Material::factory()->for(Shop::factory()->create())->create();

    $this->actingAs($user)->post(route('jobs.material-usage.store', $job), [
        'material_id' => $foreignMaterial->id, 'description' => 'x',
        'quantity' => 1, 'unit_cost' => 10, 'type' => 'used',
    ])->assertSessionHasErrors('material_id');
});

test('a usage entry can be removed, and it logs why', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);
    $job = jobForShop($shop, JobStatus::Cutting);
    $usage = usageFor($job, [
        'description' => 'Extra bracket', 'quantity' => 1, 'unit_cost' => 15, 'type' => 'used',
    ]);

    $this->actingAs($user)->delete(route('jobs.material-usage.destroy', [$job, $usage]))
        ->assertRedirect();

    expect($job->materialUsages()->count())->toBe(0)
        ->and($job->activitiesAsSubject()->latest()->first()->description)->toContain('Extra bracket');
});

test('a usage entry from a different job cannot be deleted through this job', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);
    $jobA = jobForShop($shop, JobStatus::Cutting);
    $jobB = jobForShop($shop, JobStatus::Cutting);
    $usage = usageFor($jobB, [
        'description' => 'x', 'quantity' => 1, 'unit_cost' => 1, 'type' => 'used',
    ]);

    $this->actingAs($user)->delete(route('jobs.material-usage.destroy', [$jobA, $usage]))
        ->assertNotFound();

    expect($jobB->materialUsages()->count())->toBe(1);
});

test('a user cannot see or log material usage on another shop\'s job', function () {
    $shop = Shop::factory()->create();
    $otherShop = Shop::factory()->create();
    $user = actingAsShopMember($shop);
    $foreignJob = jobForShop($otherShop, JobStatus::Cutting);

    $this->actingAs($user)->get(route('jobs.show', $foreignJob))->assertNotFound();
    $this->actingAs($user)->post(route('jobs.material-usage.store', $foreignJob), [
        'description' => 'x', 'quantity' => 1, 'unit_cost' => 1, 'type' => 'used',
    ])->assertNotFound();
});

test('someone without jobs.update cannot log or remove material usage', function () {
    $shop = Shop::factory()->create();
    $staff = actingAsShopMember($shop, 'Staff');
    $shop->members()->updateExistingPivot($staff->id, ['permission_overrides' => ['jobs.update' => false]]);
    $job = jobForShop($shop, JobStatus::Cutting);
    $usage = usageFor($job, [
        'description' => 'x', 'quantity' => 1, 'unit_cost' => 1, 'type' => 'used',
    ]);

    $this->actingAs($staff->fresh())->post(route('jobs.material-usage.store', $job), [
        'description' => 'x', 'quantity' => 1, 'unit_cost' => 1, 'type' => 'used',
    ])->assertForbidden();
    $this->actingAs($staff->fresh())->delete(route('jobs.material-usage.destroy', [$job, $usage]))
        ->assertForbidden();
});

test('material tracking is locked for a shop without the feature', function () {
    $plan = Plan::factory()->create();
    $plan->syncFeatures([Feature::JobBoard]);
    $shop = Shop::factory()->create(['trial_ends_at' => now()->subDay(), 'plan_id' => $plan->id]);
    $user = actingAsShopMember($shop);
    $job = jobForShop($shop, JobStatus::Cutting);

    $this->actingAs($user)->post(route('jobs.material-usage.store', $job), [
        'description' => 'x', 'quantity' => 1, 'unit_cost' => 1, 'type' => 'used',
    ])->assertForbidden();

    // The job page itself still loads (job-board is included), just
    // without the material-tracking panel's data.
    $this->actingAs($user)->get(route('jobs.show', $job))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('materialTracking', null));
});

test('the job page includes material tracking data for a shop with the feature', function () {
    $shop = Shop::factory()->create(); // trial shop - has every feature
    $user = actingAsShopMember($shop);
    $job = jobForShop($shop, JobStatus::Cutting);
    usageFor($job, ['description' => 'Used plate', 'quantity' => 2, 'unit_cost' => 10, 'type' => 'used']);
    usageFor($job, ['description' => 'Scrap plate', 'quantity' => 1, 'unit_cost' => 5, 'type' => 'scrap']);

    $this->actingAs($user)->get(route('jobs.show', $job))
        ->assertInertia(fn ($page) => $page
            ->has('materialTracking.usages', 2)
            ->where('materialTracking.cost_summary.actual', 20)
            ->where('materialTracking.cost_summary.scrap', 5));
});

test('estimated material cost comes from the quote\'s material line items, before markup', function () {
    $shop = Shop::factory()->create();
    $job = jobForShop($shop, JobStatus::Cutting);
    $job->quote->lineItems()->create([
        'type' => 'material', 'description' => 'Steel', 'quantity' => 2, 'unit_cost' => 50, 'markup_percent' => 25,
    ]);
    $job->quote->lineItems()->create([
        'type' => 'labor', 'description' => 'Welding', 'quantity' => 4, 'unit_cost' => 30, 'markup_percent' => 40,
    ]);

    expect($job->fresh()->estimated_material_cost)->toBe(100.0);
});

test('material cost variance combines used and scrap against the estimate', function () {
    $shop = Shop::factory()->create();
    $job = jobForShop($shop, JobStatus::Cutting);
    $job->quote->lineItems()->create([
        'type' => 'material', 'description' => 'Steel', 'quantity' => 2, 'unit_cost' => 50, 'markup_percent' => 0,
    ]);
    usageFor($job, ['description' => 'Steel', 'quantity' => 2, 'unit_cost' => 55, 'type' => 'used']);
    usageFor($job, ['description' => 'Waste', 'quantity' => 1, 'unit_cost' => 20, 'type' => 'scrap']);

    $fresh = $job->fresh();
    expect($fresh->estimated_material_cost)->toBe(100.0)
        ->and($fresh->actual_material_cost)->toBe(110.0)
        ->and($fresh->scrap_cost)->toBe(20.0)
        ->and($fresh->material_cost_variance)->toBe(30.0);
});

test('the material picker suggests the cheapest known supplier price', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);
    $job = jobForShop($shop, JobStatus::Cutting);
    $material = Material::factory()->for($shop)->create();
    $cheap = BusinessPartner::factory()->for($shop)->create();
    $pricey = BusinessPartner::factory()->for($shop)->create();
    $material->businessPartners()->attach($cheap->id, ['price' => 30]);
    $material->businessPartners()->attach($pricey->id, ['price' => 45]);

    $this->actingAs($user)->get(route('jobs.show', $job))
        ->assertInertia(fn ($page) => $page->where(
            'materialTracking.materials',
            fn ($materials) => collect($materials)->firstWhere('id', $material->id)['suggested_cost'] == 30,
        ));
});
