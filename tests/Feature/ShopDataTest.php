<?php

use App\Actions\CreateShopAgent;
use App\Actions\PurgeShop;
use App\Jobs\BuildShopDataExport;
use App\Models\Customer;
use App\Models\DataExport;
use App\Models\Job;
use App\Models\JobStage;
use App\Models\Quote;
use App\Models\QuoteLineItem;
use App\Models\Shop;
use App\Models\ShopAiSetting;
use App\Models\User;
use App\Notifications\DataExportReady;
use App\Notifications\ShopDeletionChanged;
use App\Support\ShopData\TenantTables;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
});

/** A shop with a customer, quote + line item, job and a job photo. */
function shopWithData(string $customerName): array
{
    $shop = Shop::factory()->create();
    $customer = Customer::factory()->for($shop)->create();
    $quote = Quote::factory()->for($shop)->create(['customer_id' => $customer->id]);
    $job = Job::factory()->for($shop)->create([
        'quote_id' => $quote->id,
        'customer_id' => $customer->id,
        'job_stage_id' => JobStage::where('shop_id', $shop->id)->orderBy('id')->value('id'),
    ]);
    $job->customer->update(['name' => $customerName]);
    QuoteLineItem::factory()->create(['quote_id' => $job->quote_id]);
    $job->addMedia(UploadedFile::fake()->image('weld.jpg'))->toMediaCollection('photos');

    return [$shop, $job];
}

function makeExport(Shop $shop, ?User $by = null, array $attributes = []): DataExport
{
    $export = (new DataExport)->forceFill(['shop_id' => $shop->id, 'requested_by' => $by?->id, ...$attributes]);
    $export->save();

    return $export;
}

function rowCount(string $table, Shop $shop): int
{
    $where = TenantTables::forShop($shop->id)[$table]['where'];

    return DB::table($table)->whereRaw($where)->count();
}

test('table discovery finds children that have no shop_id of their own', function () {
    [$shop] = shopWithData('Acme');

    $tables = array_keys(TenantTables::forShop($shop->id));

    expect($tables)->toContain('customers', 'quotes', 'fabrication_jobs', 'quote_line_items')
        ->not->toContain('users', 'shop_user', 'consent_records', 'shops');
});

test('an export contains this shop\'s data only, and no credentials', function () {
    Notification::fake();
    [$shop, $job] = shopWithData('Acme Fabrication');
    [$other] = shopWithData('Rival Welding');
    ShopAiSetting::factory()->create(['shop_id' => $shop->id]);
    $owner = actingAsShopMember($shop);

    $export = makeExport($shop, $owner);
    (new BuildShopDataExport($export->id))->handle();

    $export->refresh();
    expect($export->status)->toBe('ready');
    Storage::assertExists($export->path);

    $zip = new ZipArchive;
    $zip->open(Storage::path($export->path));
    $names = collect(range(0, $zip->numFiles - 1))->map(fn ($i) => $zip->getNameIndex($i));
    $customers = $zip->getFromName('data/customers.csv');

    expect($customers)->toContain('Acme Fabrication')->not->toContain('Rival Welding')
        ->and($names)->toContain('data/quote_line_items.csv', 'data/members.csv', 'README.txt')
        ->and($names->contains(fn ($n) => str_contains($n, 'shop_ai_settings')))->toBeFalse()
        ->and($names->contains(fn ($n) => str_starts_with($n, 'files/fabrication_jobs/') && str_ends_with($n, 'weld.jpg')))->toBeTrue();

    Notification::assertSentTo($owner, DataExportReady::class);
});

test('spreadsheet formula text in a cell is neutralised', function () {
    [$shop, $job] = shopWithData('=HYPERLINK("http://evil")');
    $owner = actingAsShopMember($shop);
    $export = makeExport($shop, $owner);

    (new BuildShopDataExport($export->id))->handle();

    $zip = new ZipArchive;
    $zip->open(Storage::path($export->refresh()->path));
    expect($zip->getFromName('data/customers.csv'))->toContain("'=HYPERLINK")->not->toContain(',=HYPERLINK');
});

test('the owner can request and download an export; nobody else can', function () {
    Queue::fake();
    [$shop] = shopWithData('Acme');
    [$otherShop] = shopWithData('Rival');
    $owner = actingAsShopMember($shop);
    $admin = actingAsShopMember($shop, 'Admin');
    $rival = actingAsShopMember($otherShop);

    $this->actingAs($admin)->post(route('shop.data.export'))->assertForbidden();

    $this->actingAs($owner)->post(route('shop.data.export'))->assertRedirect();
    Queue::assertPushed(BuildShopDataExport::class);

    $export = DataExport::withoutGlobalScopes()->where('shop_id', $shop->id)->firstOrFail();
    Storage::put('exports/x.zip', 'zip');
    $export->forceFill(['status' => 'ready', 'path' => 'exports/x.zip', 'expires_at' => now()->addDay()])->save();

    $this->actingAs($owner)->get(route('shop.data.download', $export))->assertOk();
    $this->actingAs($admin)->get(route('shop.data.download', $export))->assertForbidden();
    $this->actingAs($rival)->get(route('shop.data.download', $export))->assertNotFound();

    $export->forceFill(['expires_at' => now()->subMinute()])->save();
    $this->actingAs($owner)->get(route('shop.data.download', $export))->assertNotFound();
});

test('erasing a shop removes all of its data and files, and nothing of another shop', function () {
    [$shop, $job] = shopWithData('Acme');
    [$other, $otherJob] = shopWithData('Rival');
    $member = actingAsShopMember($shop);
    $roamer = actingAsShopMember($other);
    $shop->members()->attach($roamer);
    $roamer->forceFill(['current_shop_id' => $shop->id])->save();
    $agent = (new CreateShopAgent)($shop);
    $mediaPath = $job->photos()->first()->getPathRelativeToRoot();
    Storage::disk('public')->assertExists($mediaPath);
    $otherMediaPath = $otherJob->photos()->first()->getPathRelativeToRoot();
    $shopId = $shop->id;
    $tablesBefore = TenantTables::forShop($shopId);

    app(PurgeShop::class)($shop->fresh());

    foreach ($tablesBefore as $table => $info) {
        expect(DB::table($table)->whereRaw($info['where'])->count())->toBe(0, "{$table} still has rows");
    }
    expect(Shop::find($shopId))->toBeNull()
        ->and(Storage::disk('public')->exists($mediaPath))->toBeFalse()
        ->and(DB::table('activity_log')->where('subject_type', 'like', '%Job')->where('subject_id', $job->id)->count())->toBe(0)
        // People survive; the AI agent doesn't.
        ->and(User::find($member->id))->not->toBeNull()
        ->and(User::find($agent->id))->toBeNull()
        // The roamer, who also belongs to the other shop, is moved there.
        ->and($roamer->fresh()->current_shop_id)->toBe($other->id)
        // The other shop is untouched.
        ->and(rowCount('customers', $other))->toBe(1)
        ->and(rowCount('quote_line_items', $other))->toBe(1)
        ->and(Storage::disk('public')->exists($otherMediaPath))->toBeTrue()
        // The audit trail records it.
        ->and(DB::table('activity_log')->where('log_name', 'security')->where('description', 'shop deleted')->count())->toBe(1);
});

test('deleting a shop is scheduled, needs the password and exact name, and can be cancelled', function () {
    Notification::fake();
    $shop = Shop::factory()->create(['name' => 'Acme Fab']);
    $owner = actingAsShopMember($shop);
    $staff = actingAsShopMember($shop, 'Staff');

    $this->actingAs($staff)->post(route('shop.data.deletion'), ['password' => 'password', 'confirm_name' => 'Acme Fab'])->assertForbidden();

    $this->actingAs($owner)->post(route('shop.data.deletion'), ['password' => 'wrong', 'confirm_name' => 'Acme Fab'])
        ->assertSessionHasErrors('password');
    $this->actingAs($owner)->post(route('shop.data.deletion'), ['password' => 'password', 'confirm_name' => 'acme fab'])
        ->assertSessionHasErrors('confirm_name');
    expect($shop->fresh()->deletion_scheduled_for)->toBeNull();

    $this->actingAs($owner)->post(route('shop.data.deletion'), ['password' => 'password', 'confirm_name' => 'Acme Fab'])
        ->assertSessionHasNoErrors();

    $shop->refresh();
    expect($shop->deletion_scheduled_for->isFuture())->toBeTrue();
    Notification::assertSentTo([$owner, $staff], ShopDeletionChanged::class);

    // Still fully usable, and the layout is told about it.
    $this->actingAs($owner)->get(route('dashboard'))->assertOk()
        ->assertInertia(fn ($page) => $page->has('shop.deletion_scheduled_for'));

    $this->actingAs($owner)->delete(route('shop.data.deletion.cancel'))->assertRedirect();
    expect($shop->fresh()->deletion_scheduled_for)->toBeNull();
});

test('the scheduled purge erases only shops past their grace period', function () {
    [$due] = shopWithData('Due Co');
    [$waiting] = shopWithData('Waiting Co');
    [$normal] = shopWithData('Normal Co');
    $due->forceFill(['deletion_requested_at' => now()->subDays(15), 'deletion_scheduled_for' => now()->subHour()])->save();
    $waiting->forceFill(['deletion_requested_at' => now(), 'deletion_scheduled_for' => now()->addDays(3)])->save();

    $this->artisan('app:purge-deleted-shops')->assertSuccessful();

    expect(Shop::find($due->id))->toBeNull()
        ->and(Shop::find($waiting->id))->not->toBeNull()
        ->and(Shop::find($normal->id))->not->toBeNull();
});

test('expired exports are pruned', function () {
    $shop = Shop::factory()->create();
    Storage::put('old.zip', 'x');
    makeExport($shop, null, ['status' => 'ready', 'path' => 'old.zip', 'expires_at' => now()->subDay()]);

    $this->artisan('app:prune-data-exports')->assertSuccessful();

    Storage::assertMissing('old.zip');
    expect(DataExport::withoutGlobalScopes()->count())->toBe(0);
});

test('the data controls are only sent to the owner', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop);
    $admin = actingAsShopMember($shop, 'Admin');

    $this->actingAs($owner)->get(route('shop.settings.edit'))
        ->assertInertia(fn ($page) => $page->has('ownerControls.deletion')->has('ownerControls.exports'));
    // The owner delegated settings to this admin - the data controls stay owner-only.
    $shop->members()->updateExistingPivot($admin->id, ['permission_overrides' => ['shop-settings.update' => true]]);

    $this->actingAs($admin->fresh())->get(route('shop.settings.edit'))->assertInertia(fn ($page) => $page->where('ownerControls', null));
});
