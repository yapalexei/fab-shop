<?php

namespace App\Jobs;

use App\Models\DataExport;
use App\Models\Shop;
use App\Models\User;
use App\Notifications\DataExportReady;
use App\Support\ShopData\ModelTables;
use App\Support\ShopData\TenantTables;
use App\Support\TwoFactorPolicy;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use ZipArchive;

/**
 * Builds the zip a shop owner downloads: one CSV per table of the shop's data
 * (found by following foreign keys - see TenantTables), the people in the
 * shop, and the uploaded files. Left out on purpose: anything that works as a
 * credential (AI provider key, QuickBooks tokens, invitation tokens, password
 * hashes) and the internal role tables.
 *
 * Runs with no signed-in user, so every query names the shop explicitly.
 */
class BuildShopDataExport implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public int $tries = 1;

    /** Tables that are internal plumbing or hold credentials. */
    private const SKIP_TABLES = [
        'shop_ai_settings', 'quickbooks_connections', 'roles', 'model_has_roles',
        'model_has_permissions', 'role_has_permissions', 'data_exports', 'imports',
    ];

    private const SECRET_COLUMN = '/(token|secret|password|api_key|refresh)/i';

    public function __construct(public int $exportId) {}

    public function handle(): void
    {
        $export = DataExport::withoutGlobalScopes()->findOrFail($this->exportId);
        $shop = Shop::findOrFail($export->shop_id);
        $dir = storage_path('app/private/export-'.Str::uuid());
        File::ensureDirectoryExists($dir);

        try {
            $zipPath = "{$dir}/export.zip";
            $zip = new ZipArchive;
            $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

            $this->addTables($zip, $shop, $dir);
            $this->addPeople($zip, $shop, $dir);
            $this->addFiles($zip, $shop, $dir);
            $zip->addFromString('README.txt', $this->readme($shop));
            $zip->close();

            $path = "data-exports/{$shop->id}/".Str::uuid().'.zip';
            $stream = fopen($zipPath, 'r');
            Storage::put($path, $stream);
            fclose($stream);

            $export->forceFill([
                'status' => 'ready',
                'path' => $path,
                'size_bytes' => filesize($zipPath),
                'expires_at' => now()->addDays((int) config('shop.export_retention_days')),
            ])->save();
        } catch (\Throwable $e) {
            report($e);
            $export->forceFill(['status' => 'failed', 'error' => 'The export could not be built. Please try again.'])->save();

            return;
        } finally {
            File::deleteDirectory($dir);
        }

        User::find($export->requested_by)?->notify(new DataExportReady($shop));
    }

    private function addTables(ZipArchive $zip, Shop $shop, string $dir): void
    {
        $zip->addEmptyDir('data');

        foreach (TenantTables::forShop($shop->id) as $table => $info) {
            if (in_array($table, self::SKIP_TABLES, true)) {
                continue;
            }

            $columns = array_values(array_filter(Schema::getColumnListing($table), fn ($c) => ! preg_match(self::SECRET_COLUMN, $c)));
            $this->addCsv($zip, $dir, "data/{$table}.csv", $columns, DB::table($table)->select($columns)->whereRaw($info['where'])->cursor());
        }

        $this->addCsv($zip, $dir, 'data/shop.csv',
            ['id', 'name', 'timezone', 'created_at'],
            [(object) ['id' => $shop->id, 'name' => $shop->name, 'timezone' => $shop->localTimezone(), 'created_at' => $shop->created_at]]);
    }

    private function addPeople(ZipArchive $zip, Shop $shop, string $dir): void
    {
        $roles = TwoFactorPolicy::rolesInShop($shop);
        $rows = $shop->members()->get()->map(fn (User $u) => (object) [
            'name' => $u->name,
            'email' => $u->email,
            'role' => $roles[$u->id] ?? '',
            'joined' => $u->pivot->created_at,
            'hourly_rate' => $u->pivot->hourly_rate,
        ]);

        $this->addCsv($zip, $dir, 'data/members.csv', ['name', 'email', 'role', 'joined', 'hourly_rate'], $rows);
    }

    private function addFiles(ZipArchive $zip, Shop $shop, string $dir): void
    {
        $tables = TenantTables::forShop($shop->id);
        $index = [];

        foreach (ModelTables::withMedia() as $table => $class) {
            if (! isset($tables[$table])) {
                continue; // e.g. User avatars: personal, not shop data.
            }

            Media::query()
                ->where('model_type', (new $class)->getMorphClass())
                ->whereRaw('model_id IN ('.TenantTables::idsSubquery($table, $tables[$table]['where']).')')
                ->each(function (Media $media) use ($zip, $dir, $table, &$index) {
                    $tmp = "{$dir}/media-{$media->id}";

                    try {
                        $in = $media->stream();
                        $out = fopen($tmp, 'w');
                        stream_copy_to_stream($in, $out);
                        fclose($out);
                    } catch (\Throwable) {
                        return; // A file missing from storage must not sink the whole export.
                    }

                    $name = "files/{$table}/{$media->model_id}/{$media->id}-{$media->file_name}";
                    $zip->addFile($tmp, $name);
                    $index[] = (object) ['file' => $name, 'table' => $table, 'record_id' => $media->model_id, 'collection' => $media->collection_name, 'original_name' => $media->file_name];
                });
        }

        $this->addCsv($zip, $dir, 'files/index.csv', ['file', 'table', 'record_id', 'collection', 'original_name'], $index);
    }

    /**
     * @param  list<string>  $columns
     * @param  iterable<object>  $rows
     */
    private function addCsv(ZipArchive $zip, string $dir, string $name, array $columns, iterable $rows): void
    {
        $file = "{$dir}/".Str::uuid().'.csv';
        $handle = fopen($file, 'w');
        fputcsv($handle, $columns);

        foreach ($rows as $row) {
            fputcsv($handle, array_map(function ($column) use ($row) {
                $value = $row->{$column} ?? null;

                // Spreadsheet formula injection: a cell that starts with one of
                // these is run as a formula by Excel / Sheets.
                if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) {
                    return "'".$value;
                }

                return is_array($value) || is_object($value) ? json_encode($value) : $value;
            }, $columns));
        }

        fclose($handle);
        $zip->addFile($file, $name);
    }

    private function readme(Shop $shop): string
    {
        return "Data export for {$shop->name}\nCreated ".now()->toDateTimeString()." UTC\n\n"
            ."data/   one CSV per kind of record (customers, quotes, jobs, ...) plus members.csv and shop.csv.\n"
            ."files/  uploaded files (job photos, attachments, logos), with index.csv saying which record each belongs to.\n\n"
            ."Not included: your AI provider key, QuickBooks connection tokens, invitation tokens and password hashes.\n"
            ."Dates are UTC. The security log is kept by the platform and is not part of this export.\n";
    }
}
