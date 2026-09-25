<?php

use App\Enums\JobStageRole;
use App\Models\JobStage;
use App\Models\Shop;
use App\Support\JobStages;

test('every new shop starts with Not Started, a starter custom stage, Shipped and Invoiced, in that order', function () {
    $shop = Shop::factory()->create();

    $stages = JobStage::withoutGlobalScopes()->where('shop_id', $shop->id)->orderBy('position')->get();

    expect($stages->pluck('name')->all())->toBe(['Not Started', JobStages::STARTER_STAGE_NAME, 'Shipped', 'Invoiced'])
        ->and($stages->pluck('role')->map(fn ($role) => $role?->value)->all())->toBe(['not_started', null, 'shipped', 'invoiced']);
});

test('seeding the defaults again adds nothing', function () {
    $shop = Shop::factory()->create();

    JobStages::seedDefaults($shop);

    expect(JobStage::withoutGlobalScopes()->where('shop_id', $shop->id)->count())->toBe(4);
});

test('a shop can add, rename and delete its own custom stages', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->post(route('job-stages.store'), ['name' => 'Coating'])->assertSessionHasNoErrors();
    $stage = JobStage::where('name', 'Coating')->sole();
    expect($stage->isBuiltIn())->toBeFalse()->and($stage->shop_id)->toBe($shop->id);

    // Appended just before Shipped.
    $shipped = JobStage::where('shop_id', $shop->id)->where('role', JobStageRole::Shipped)->sole();
    expect($stage->fresh()->position)->toBe($shipped->fresh()->position - 1);

    $this->actingAs($user)->patch(route('job-stages.update', $stage), ['name' => 'Powder coating'])->assertSessionHasNoErrors();
    expect($stage->fresh()->name)->toBe('Powder coating');

    $this->actingAs($user)->delete(route('job-stages.destroy', $stage))->assertSessionHas('status');
    expect(JobStage::find($stage->id))->toBeNull();
});

test('names are unique per shop regardless of case, but two shops can share one', function () {
    $shop = Shop::factory()->create();
    $otherShop = Shop::factory()->create();
    $user = actingAsShopMember($shop);
    $otherUser = actingAsShopMember($otherShop);

    $this->actingAs($user)->post(route('job-stages.store'), ['name' => 'shipped'])->assertSessionHasErrors('name');
    $this->actingAs($user)->post(route('job-stages.store'), ['name' => 'Coating'])->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('job-stages.store'), ['name' => 'COATING'])->assertSessionHasErrors('name');
    $this->actingAs($otherUser)->post(route('job-stages.store'), ['name' => 'Coating'])->assertSessionHasNoErrors();
});

test('built-in stages cannot be renamed or deleted', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);
    $shipped = JobStage::where('shop_id', $shop->id)->where('role', JobStageRole::Shipped)->sole();

    $this->actingAs($user)->patch(route('job-stages.update', $shipped), ['name' => 'Delivered'])->assertForbidden();
    $this->actingAs($user)->delete(route('job-stages.destroy', $shipped))->assertSessionHas('error');

    expect($shipped->fresh()->name)->toBe('Shipped');
});

test('a stage with jobs in it cannot be deleted', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);
    $job = jobForShop($shop, 'in_progress');
    $setup = $job->jobStage;

    $this->actingAs($user)->delete(route('job-stages.destroy', $setup))
        ->assertSessionHas('error', "{$setup->name} still has 1 job(s) in it - move them first.");

    expect(JobStage::find($setup->id))->not->toBeNull();
});

test('reordering only moves custom stages - the built-ins stay put', function () {
    $shop = Shop::factory()->create();
    $user = actingAsShopMember($shop);
    $this->actingAs($user)->post(route('job-stages.store'), ['name' => 'Coating']);

    $setup = JobStage::where('shop_id', $shop->id)->whereNull('role')->where('name', JobStages::STARTER_STAGE_NAME)->sole();
    $coating = JobStage::where('name', 'Coating')->sole();

    $this->actingAs($user)->patch(route('job-stages.reorder'), [
        'ordered_ids' => [$coating->id, $setup->id],
    ])->assertRedirect();

    $notStarted = JobStage::where('shop_id', $shop->id)->where('role', JobStageRole::NotStarted)->sole();
    $shipped = JobStage::where('shop_id', $shop->id)->where('role', JobStageRole::Shipped)->sole();
    $invoiced = JobStage::where('shop_id', $shop->id)->where('role', JobStageRole::Invoiced)->sole();

    expect($notStarted->fresh()->position)->toBe(0)
        ->and($coating->fresh()->position)->toBe(1)
        ->and($setup->fresh()->position)->toBe(2)
        ->and($shipped->fresh()->position)->toBe(3)
        ->and($invoiced->fresh()->position)->toBe(4);
});

test('a user cannot see or change another shop\'s job stages', function () {
    $shop = Shop::factory()->create();
    $otherShop = Shop::factory()->create();
    $foreign = JobStage::withoutGlobalScopes()->where('shop_id', $otherShop->id)->whereNull('role')->first();
    $user = actingAsShopMember($shop);

    $this->actingAs($user)->get(route('job-stages.index'))
        ->assertInertia(fn ($page) => $page->has('stages', 4));
    $this->actingAs($user)->patch(route('job-stages.update', $foreign), ['name' => 'Hijacked'])->assertNotFound();
    $this->actingAs($user)->delete(route('job-stages.destroy', $foreign))->assertNotFound();
    $this->actingAs($user)->patch(route('job-stages.reorder'), ['ordered_ids' => [$foreign->id]])->assertSessionHasErrors('ordered_ids.0');
});

test('staff can see job stages but not manage them by default', function () {
    $shop = Shop::factory()->create();
    $staff = actingAsShopMember($shop, 'Staff');

    $this->actingAs($staff)->get(route('job-stages.index'))->assertOk();
    $this->actingAs($staff)->post(route('job-stages.store'), ['name' => 'Coating'])->assertForbidden();
});
