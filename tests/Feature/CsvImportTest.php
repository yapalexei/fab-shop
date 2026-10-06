<?php

use App\Enums\ImportStatus;
use App\Jobs\ProcessCsvImport;
use App\Models\BusinessPartner;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Import;
use App\Models\Material;
use App\Models\MaterialType;
use App\Models\Plan;
use App\Models\Service;
use App\Models\Shop;
use App\Models\Tool;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

/**
 * @return TestResponse
 */
function uploadCsv(User $user, string $resource, string $content, bool $overwrite = false)
{
    return test()->actingAs($user)->post(route('imports.store', $resource), [
        'file' => UploadedFile::fake()->createWithContent('import.csv', $content),
        'overwrite' => $overwrite,
    ]);
}

test('templates are downloadable and match the resource\'s expected columns', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');

    $response = $this->actingAs($owner)->get(route('imports.template', 'materials'));

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
        ->assertSee("name,unit,material_type,notes\n", false);
});

test('a non-owner cannot download a template or upload a file', function () {
    $shop = Shop::factory()->create();
    $admin = actingAsShopMember($shop, 'Admin');

    $this->actingAs($admin)->get(route('imports.template', 'customers'))->assertForbidden();
    $this->actingAs($admin)->post(route('imports.store', 'customers'), [
        'file' => UploadedFile::fake()->createWithContent('x.csv', "name,email,phone,address,notes\n"),
    ])->assertForbidden();
});

test('an unknown resource key 404s', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');

    $this->actingAs($owner)->get(route('imports.template', 'not-a-real-resource'))->assertNotFound();
});

test('importing customers creates new records', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');

    $response = $this->actingAs($owner)->post(route('imports.store', 'customers'), [
        'file' => UploadedFile::fake()->createWithContent('customers.csv', implode("\n", [
            'name,email,phone,address,notes',
            'Harbor Point Marine,orders@harborpoint.test,555-0142,,Net 30',
            'Oakmont HOA,,,123 Main St,',
        ])),
    ]);

    $response->assertOk();
    $importId = $response->json('id');
    $import = Import::find($importId);

    expect($import->status)->toBe(ImportStatus::Completed)
        ->and($import->created_count)->toBe(2)
        ->and($import->updated_count)->toBe(0)
        ->and($import->skipped_count)->toBe(0)
        ->and($import->errors)->toBeNull();

    $customer = Customer::where('name', 'Harbor Point Marine')->sole();
    expect($customer->shop_id)->toBe($shop->id)
        ->and($customer->email)->toBe('orders@harborpoint.test')
        ->and($customer->notes)->toBe('Net 30');
});

test('a row matching an existing name is skipped unless overwrite is checked', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');
    $existing = Customer::factory()->for($shop)->create(['name' => 'Existing Co', 'notes' => 'Original']);

    $csv = "name,email,phone,address,notes\nExisting Co,,,,\"Updated notes\"\n";

    $response = uploadCsv($owner, 'customers', $csv, overwrite: false);
    $import = Import::find($response->json('id'));
    expect($import->skipped_count)->toBe(1)
        ->and($import->updated_count)->toBe(0)
        ->and($existing->fresh()->notes)->toBe('Original');

    $response = uploadCsv($owner, 'customers', $csv, overwrite: true);
    $import = Import::find($response->json('id'));
    expect($import->updated_count)->toBe(1)
        ->and($import->skipped_count)->toBe(0)
        ->and($existing->fresh()->notes)->toBe('Updated notes')
        ->and(Customer::count())->toBe(1);
});

test('two rows in the same file with the same name, differing only in case, resolve as one record', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');

    $csv = implode("\n", [
        'name,email,phone,address,notes',
        'Acme Co,first@example.com,,,',
        'ACME CO,second@example.com,,,',
    ]);

    $response = uploadCsv($owner, 'customers', $csv, overwrite: true);
    $import = Import::find($response->json('id'));

    expect($import->created_count)->toBe(1)
        ->and($import->updated_count)->toBe(1)
        ->and(Customer::count())->toBe(1)
        ->and(Customer::sole()->email)->toBe('second@example.com');
});

test('a file missing a required column is rejected wholesale, before any row is touched', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');

    $response = uploadCsv($owner, 'customers', "email,phone\nx@example.com,555\n");
    $import = Import::find($response->json('id'));

    expect($import->status)->toBe(ImportStatus::Failed)
        ->and($import->errors[0]['row'])->toBe(0)
        ->and($import->errors[0]['messages'][0])->toContain('name')
        ->and(Customer::count())->toBe(0);
});

test('one bad row is reported by row number while the rest of the file still imports', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');

    $csv = implode("\n", [
        'name,email,phone,address,notes',
        'Good Co,,,,',
        ',bad-row-no-name,,,',
        'Also Good Co,,,,',
    ]);

    $response = uploadCsv($owner, 'customers', $csv);
    $import = Import::find($response->json('id'));

    expect($import->created_count)->toBe(2)
        ->and($import->errors)->toHaveCount(1)
        ->and($import->errors[0]['row'])->toBe(3)
        ->and(Customer::count())->toBe(2);
});

test('importing materials resolves the material_type column by name', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');
    $steel = MaterialType::where('shop_id', $shop->id)->where('name', 'Carbon steel')->sole();

    $csv = implode("\n", [
        'name,unit,material_type,notes',
        '1/4in Steel Plate,sheet,Carbon steel,',
        'Mystery Metal,each,Unobtainium,',
    ]);

    $response = uploadCsv($owner, 'materials', $csv);
    $import = Import::find($response->json('id'));

    expect($import->created_count)->toBe(1)
        ->and($import->errors)->toHaveCount(1)
        ->and($import->errors[0]['messages'][0])->toContain('Unobtainium');

    $material = Material::where('name', '1/4in Steel Plate')->sole();
    expect($material->material_type_id)->toBe($steel->id);
});

test('an unrecognized material type is never auto-created by an import', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');
    $before = MaterialType::where('shop_id', $shop->id)->count();

    uploadCsv($owner, 'materials', "name,unit,material_type,notes\nX,each,Not A Real Type,\n");

    expect(MaterialType::where('shop_id', $shop->id)->count())->toBe($before);
});

test('importing tooling resolves a semicolon-separated cuts column into material types', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');

    $csv = "name,cuts,cost,unit,notes\n1/4in cobalt drill bit,Carbon steel;Stainless steel,6.50,each,\n";
    $response = uploadCsv($owner, 'tools', $csv);
    $import = Import::find($response->json('id'));

    expect($import->created_count)->toBe(1);

    $tool = Tool::sole();
    expect($tool->cutsLabel())->toBe('Carbon steel, Stainless steel');
});

test('tooling with no resolvable cuts is rejected with a clear reason', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');

    $response = uploadCsv($owner, 'tools', "name,cuts,cost,unit,notes\nBad Tool,,6.50,each,\n");
    $import = Import::find($response->json('id'));

    expect($import->created_count)->toBe(0)
        ->and($import->errors[0]['messages'][0])->toContain('at least one material');
});

test('importing material types respects the shop\'s custom-type cap', function () {
    config(['shop.max_custom_material_types' => 1]);
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');

    $response = uploadCsv($owner, 'material-types', "name\nChromoly\nInconel\n");
    $import = Import::find($response->json('id'));

    expect($import->created_count)->toBe(1)
        ->and($import->errors)->toHaveCount(1)
        ->and($import->errors[0]['messages'][0])->toContain('maximum');
});

test('equipment requires a valid billing method', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');

    $csv = implode("\n", [
        'name,billing_method,rate,notes',
        'Plasma cutter,hourly,45,',
        'Bad Row,weekly,10,',
    ]);

    $response = uploadCsv($owner, 'equipment', $csv);
    $import = Import::find($response->json('id'));

    expect($import->created_count)->toBe(1)
        ->and($import->errors)->toHaveCount(1);
    expect(Equipment::sole()->billing_method->value)->toBe('hourly');
});

test('importing services and business partners works with just a name', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');

    uploadCsv($owner, 'services', "name,notes\nPowder Coating,\n");
    uploadCsv($owner, 'business-partners', "name,email,phone,address,notes\nCoastal Metal Supply,,,,\n");

    expect(Service::sole()->name)->toBe('Powder Coating')
        ->and(BusinessPartner::sole()->name)->toBe('Coastal Metal Supply');
});

test('a shop without the resource\'s feature cannot import it', function () {
    $plan = Plan::factory()->create();
    $plan->syncFeatures([]);
    $shop = Shop::factory()->create(['trial_ends_at' => now()->subDay(), 'plan_id' => $plan->id]);
    $owner = actingAsShopMember($shop, 'Owner');

    $this->actingAs($owner)->get(route('imports.template', 'materials'))->assertForbidden();
    $this->actingAs($owner)->post(route('imports.store', 'materials'), [
        'file' => UploadedFile::fake()->createWithContent('x.csv', "name,unit,material_type,notes\n"),
    ])->assertForbidden();
});

test('an import never reaches into another shop\'s catalog or names', function () {
    $shop = Shop::factory()->create();
    $otherShop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');
    Customer::factory()->for($otherShop)->create(['name' => 'Shared Name']);

    uploadCsv($owner, 'customers', "name,email,phone,address,notes\nShared Name,,,,\n");

    expect(Customer::where('name', 'Shared Name')->count())->toBe(1)
        ->and(Customer::withoutGlobalScopes()->where('shop_id', $otherShop->id)->count())->toBe(1);
});

test('the status endpoint only exposes an import belonging to the current shop', function () {
    $shop = Shop::factory()->create();
    $otherShop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');
    $otherOwner = actingAsShopMember($otherShop, 'Owner');

    $response = $this->actingAs($otherOwner)->post(route('imports.store', 'customers'), [
        'file' => UploadedFile::fake()->createWithContent('x.csv', "name,email,phone,address,notes\nX,,,,\n"),
    ]);
    $importId = $response->json('id');

    $this->actingAs($owner)->get(route('imports.show', $importId))->assertNotFound();
    $this->actingAs($otherOwner)->get(route('imports.show', $importId))
        ->assertOk()
        ->assertJson(['status' => 'completed', 'created_count' => 1]);
});

test('processing is actually queued, not run inline in the request', function () {
    Queue::fake();
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');

    $this->actingAs($owner)->post(route('imports.store', 'customers'), [
        'file' => UploadedFile::fake()->createWithContent('x.csv', "name,email,phone,address,notes\nX,,,,\n"),
    ]);

    Queue::assertPushed(ProcessCsvImport::class);
});

test('the preview step returns headers, sample rows and a best-guess mapping without storing anything', function () {
    Queue::fake();
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');

    $csv = "Part Number,Description,UOM,Price\n8910K12,Steel bar,ft,20\n8910K13,Steel tube,ft,25\n";

    $this->actingAs($owner)->post(route('imports.preview', 'materials'), [
        'file' => UploadedFile::fake()->createWithContent('mcmaster.csv', $csv),
    ])->assertOk()
        ->assertJsonPath('headers', ['Part Number', 'Description', 'UOM', 'Price'])
        ->assertJsonPath('sample.0', ['8910K12', 'Steel bar', 'ft', '20'])
        ->assertJsonPath('suggested.name', 1)
        ->assertJsonPath('suggested.unit', 2)
        ->assertJsonPath('suggested.notes', null)
        ->assertJsonPath('columns.0', ['name' => 'name', 'required' => true]);

    Queue::assertNothingPushed();
    expect(Import::count())->toBe(0);
});

test('an exact synonym never steals a column that another field matches by name', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');

    $this->actingAs($owner)->post(route('imports.preview', 'tools'), [
        'file' => UploadedFile::fake()->createWithContent('t.csv', "Price,Cost,Name\n1,2,Bit\n"),
    ])->assertJsonPath('suggested.cost', 1)->assertJsonPath('suggested.name', 2);
});

test('a file with its own headers imports once its columns are mapped, leaving unmapped ones blank', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');

    $response = $this->actingAs($owner)->post(route('imports.store', 'materials'), [
        'file' => UploadedFile::fake()->createWithContent('mcmaster.csv', "Part Number,Description,UOM\n8910K12,Steel bar,ft\n"),
        'mapping' => ['name' => 1, 'unit' => 2, 'material_type' => '', 'notes' => ''],
    ])->assertOk();

    $import = Import::find($response->json('id'));
    $material = Material::sole();

    expect($import->status)->toBe(ImportStatus::Completed)
        ->and($material->name)->toBe('Steel bar')
        ->and($material->unit)->toBe('ft')
        ->and($material->notes)->toBeNull();
});

test('a mapping must cover every required column and use each file column once', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');
    $file = fn () => UploadedFile::fake()->createWithContent('t.csv', "a,b,c\nBit,1,each\n");

    $this->actingAs($owner)->post(route('imports.store', 'tools'), [
        'file' => $file(), 'mapping' => ['name' => 0, 'cuts' => '', 'cost' => 1, 'unit' => 2],
    ])->assertSessionHasErrors('mapping.cuts');

    $this->actingAs($owner)->post(route('imports.store', 'tools'), [
        'file' => $file(), 'mapping' => ['name' => 0, 'cuts' => 0, 'cost' => 1, 'unit' => 2],
    ])->assertSessionHasErrors('mapping');

    expect(Import::count())->toBe(0);
});

test('a mapping pointing past the end of the file fails the import with a clear reason', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');

    $response = $this->actingAs($owner)->post(route('imports.store', 'customers'), [
        'file' => UploadedFile::fake()->createWithContent('t.csv', "a,b\nX,y\n"),
        'mapping' => ['name' => 9],
    ]);

    expect(Import::find($response->json('id'))->status)->toBe(ImportStatus::Failed)
        ->and(Customer::count())->toBe(0);
});

test('only an owner can preview a file, and only for a feature the shop has', function () {
    $shop = Shop::factory()->create();
    $admin = actingAsShopMember($shop, 'Admin');

    $this->actingAs($admin)->post(route('imports.preview', 'customers'), [
        'file' => UploadedFile::fake()->createWithContent('x.csv', "name\nX\n"),
    ])->assertForbidden();
});

// --- supplier price lists and link columns ------------------------------------------

function priceList(User $user, BusinessPartner $partner, string $csv, array $extra = [])
{
    return test()->actingAs($user)->post(route('imports.store', 'supplier-prices'), [
        'file' => UploadedFile::fake()->createWithContent('prices.csv', $csv),
        'business_partner_id' => $partner->id,
        ...$extra,
    ]);
}

test('a supplier price list adds prices with part numbers and links, creating materials it lacks', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');
    $partner = BusinessPartner::factory()->for($shop)->create();
    $plate = Material::factory()->for($shop)->create(['name' => '1/4in A36 plate']);

    $response = priceList($owner, $partner, "material,size,price,unit,part_number,source_url\n"
        .'1/4in a36 PLATE,4x8 ft,"$1,210.50",sheet,8910K12,https://www.mcmaster.com/8910K12/'."\n"
        ."Brand new tube,,15,ft,,\n");

    $import = Import::find($response->json('id'));
    $prices = $partner->fresh()->materials()->get();

    expect($import->status)->toBe(ImportStatus::Completed)
        ->and($import->created_count)->toBe(2)
        ->and(Material::count())->toBe(2)
        ->and($prices)->toHaveCount(2);

    $platePrice = $prices->firstWhere('id', $plate->id)->pivot;
    expect((float) $platePrice->price)->toBe(1210.5)
        ->and($platePrice->dimension)->toBe('4x8 ft')
        ->and($platePrice->part_number)->toBe('8910K12')
        ->and($platePrice->price_checked_at)->not->toBeNull()
        ->and(Material::where('name', 'Brand new tube')->value('unit'))->toBe('ft');
});

test('a price already on file is skipped unless overwrite is on, and an overwrite counts as a fresh check', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');
    $partner = BusinessPartner::factory()->for($shop)->create();
    $material = Material::factory()->for($shop)->create(['name' => 'Bar']);
    $partner->materials()->attach($material->id, ['price' => 10, 'dimension' => '4 ft', 'price_checked_at' => now()->subYear()]);

    $csv = "material,size,price\nBar,4 FT,12\nBar,6 ft,18\n";

    $skip = Import::find(priceList($owner, $partner, $csv)->json('id'));
    expect($skip->skipped_count)->toBe(1)->and($skip->created_count)->toBe(1);
    expect((float) $partner->fresh()->materials()->wherePivot('dimension', '4 ft')->first()->pivot->price)->toBe(10.0);

    $over = Import::find(priceList($owner, $partner, $csv, ['overwrite' => true])->json('id'));
    expect($over->updated_count)->toBe(2)->and($over->created_count)->toBe(0);

    $row = $partner->fresh()->materials()->wherePivot('dimension', '4 ft')->first()->pivot;
    expect((float) $row->price)->toBe(12.0)
        ->and(Carbon::parse($row->price_checked_at)->isToday())->toBeTrue()
        ->and($partner->fresh()->materials()->count())->toBe(2);
});

test('a price list reports bad rows and repeats of the same material and size', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');
    $partner = BusinessPartner::factory()->for($shop)->create();

    $import = Import::find(priceList($owner, $partner, "material,size,price,source_url\n"
        ."Bar,,10,\nbar,,11,\nTube,,abc,\nRod,,5,javascript:alert(1)\n")->json('id'));

    expect($import->created_count)->toBe(1)
        ->and(collect($import->errors)->pluck('row')->all())->toBe([3, 4, 5])
        ->and($import->errors[0]['messages'][0])->toContain('row 2');
});

test('a price list needs a supplier from this shop', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');
    $foreign = BusinessPartner::factory()->for(Shop::factory()->create())->create();
    $csv = UploadedFile::fake()->createWithContent('p.csv', "material,price\nBar,1\n");

    $this->actingAs($owner)->post(route('imports.store', 'supplier-prices'), ['file' => $csv])
        ->assertSessionHasErrors('business_partner_id');
    $this->actingAs($owner)->post(route('imports.store', 'supplier-prices'), ['file' => $csv, 'business_partner_id' => $foreign->id])
        ->assertSessionHasErrors('business_partner_id');

    expect(Material::count())->toBe(0);
});

test('a supplier\'s own export is mapped on the preview step, then imported against the chosen supplier', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');
    $partner = BusinessPartner::factory()->for($shop)->create();
    $csv = "Part Number,Description,Length,List Price,Link\n8910K12,Steel bar,4 ft,20,https://example.com/a\n";

    $this->actingAs($owner)->post(route('imports.preview', 'supplier-prices'), [
        'file' => UploadedFile::fake()->createWithContent('export.csv', $csv),
    ])->assertOk()
        ->assertJsonPath('suggested.material', 1)
        ->assertJsonPath('suggested.size', 2)
        ->assertJsonPath('suggested.price', 3)
        ->assertJsonPath('suggested.part_number', 0)
        ->assertJsonPath('suggested.source_url', 4);

    $import = Import::find($this->actingAs($owner)->post(route('imports.store', 'supplier-prices'), [
        'file' => UploadedFile::fake()->createWithContent('export.csv', $csv),
        'business_partner_id' => $partner->id,
        'mapping' => ['material' => 1, 'size' => 2, 'price' => 3, 'unit' => '', 'part_number' => 0, 'source_url' => 4],
    ])->json('id'));

    expect($import->created_count)->toBe(1)
        ->and($partner->fresh()->materials()->first()->pivot->part_number)->toBe('8910K12');
});

test('a price list never touches another shop\'s supplier or materials', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');
    $partner = BusinessPartner::factory()->for($shop)->create();
    $otherShop = Shop::factory()->create();
    $theirs = Material::factory()->for($otherShop)->create(['name' => 'Bar']);

    priceList($owner, $partner, "material,price\nBar,10\n");

    expect(Material::withoutGlobalScopes()->where('shop_id', $shop->id)->count())->toBe(1)
        ->and(Material::withoutGlobalScopes()->find($theirs->id)->shop_id)->toBe($otherShop->id)
        ->and(DB::table('material_business_partner')->where('material_id', $theirs->id)->count())->toBe(0);
});

test('tooling imports part number and link columns, and files without them still import', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');

    $old = Import::find(uploadCsv($owner, 'tools', "name,cuts,cost,unit,notes\nOld bit,Carbon steel,5,each,\n")->json('id'));
    expect($old->status)->toBe(ImportStatus::Completed)->and($old->created_count)->toBe(1);

    $new = Import::find(uploadCsv($owner, 'tools', "name,cuts,cost,unit,notes,part_number,source_url\nNew bit,Carbon steel,6,each,,2985A21,https://www.mcmaster.com/2985A21/\n")->json('id'));
    $tool = Tool::where('name', 'New bit')->sole();

    expect($new->created_count)->toBe(1)
        ->and($tool->part_number)->toBe('2985A21')
        ->and($tool->source_url)->toBe('https://www.mcmaster.com/2985A21/')
        ->and($tool->price_checked_at)->not->toBeNull();
});

test('an overwrite import restamps a tool\'s check date only when its cost changed', function () {
    $shop = Shop::factory()->create();
    $owner = actingAsShopMember($shop, 'Owner');
    $tool = Tool::factory()->for($shop)->cutting('Carbon steel')->create(['name' => 'Bit', 'cost' => 5, 'unit' => 'each']);
    $tool->forceFill(['price_checked_at' => now()->subYear()])->save();
    $stamped = $tool->fresh()->price_checked_at;

    uploadCsv($owner, 'tools', "name,cuts,cost,unit,notes\nBit,Carbon steel,5,each,\n", overwrite: true);
    expect($tool->fresh()->price_checked_at->equalTo($stamped))->toBeTrue();

    uploadCsv($owner, 'tools', "name,cuts,cost,unit,notes\nBit,Carbon steel,7,each,\n", overwrite: true);
    expect($tool->fresh()->price_checked_at->greaterThan($stamped))->toBeTrue();
});
