<?php

namespace App\Jobs;

use App\Enums\ImportStatus;
use App\Models\Import;
use App\Models\Shop;
use App\Support\CsvImport\CsvFormatException;
use App\Support\CsvImport\CsvReader;
use App\Support\CsvImport\ImporterRegistry;
use App\Support\CsvImport\ResourceImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Parses and applies one uploaded CSV against a shop's catalog. Console/queue
 * context has no authenticated user, so - like the demo commands and
 * NotifyExpiredTrials - every query here filters by shop_id explicitly
 * rather than relying on the tenant global scope, which is a no-op here.
 *
 * A row's "already exists" check also looks at what this same run has
 * already created/updated (not just the database), so two rows in one file
 * with the same name (however they differ in case) resolve as one record,
 * not two.
 */
class ProcessCsvImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $importId,
        public string $storedPath,
    ) {}

    public function handle(): void
    {
        $import = Import::withoutGlobalScopes()->find($this->importId);

        if (! $import) {
            $this->cleanUp();

            return;
        }

        $shop = Shop::withoutGlobalScopes()->find($import->shop_id);
        $importer = ImporterRegistry::for($import->resource);

        try {
            $rows = CsvReader::parse(Storage::disk('local')->path($this->storedPath), $importer->templateColumns());
        } catch (CsvFormatException $e) {
            $import->update([
                'status' => ImportStatus::Failed,
                'errors' => [['row' => 0, 'messages' => [$e->getMessage()]]],
            ]);
            $this->cleanUp();

            return;
        }

        [$created, $updated, $skipped, $errors] = $this->processRows($rows, $importer, $shop, $import->overwrite);

        $import->update([
            'status' => ImportStatus::Completed,
            'created_count' => $created,
            'updated_count' => $updated,
            'skipped_count' => $skipped,
            'errors' => $errors === [] ? null : $errors,
        ]);
        $this->cleanUp();
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     * @return array{0: int, 1: int, 2: int, 3: array<int, array{row: int, messages: array<int, string>}>}
     */
    private function processRows(array $rows, ResourceImporter $importer, Shop $shop, bool $overwrite): array
    {
        $created = $updated = $skipped = 0;
        $errors = [];
        /** @var array<string, Model> $processedThisRun */
        $processedThisRun = [];
        $modelClass = $importer->modelClass();

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // header is row 1

            $resolved = $importer->resolveRow($row, $shop);
            $validator = Validator::make($resolved['attributes'], $importer->rules());
            $messages = [...$resolved['errors'], ...$validator->errors()->all()];

            if ($messages !== []) {
                $errors[] = ['row' => $rowNumber, 'messages' => $messages];

                continue;
            }

            $name = trim((string) $resolved['attributes']['name']);
            $key = mb_strtolower($name);

            $existing = $processedThisRun[$key] ?? $modelClass::query()
                ->where('shop_id', $shop->id)
                ->whereRaw('lower(name) = ?', [$key])
                ->first();

            if ($existing) {
                if ($overwrite) {
                    $existing->forceFill($resolved['attributes'])->save();
                    $importer->applyRelations($existing, $resolved['relations']);
                    $processedThisRun[$key] = $existing;
                    $updated++;
                } else {
                    $skipped++;
                }

                continue;
            }

            if ($blockReason = $importer->beforeCreate($shop)) {
                $errors[] = ['row' => $rowNumber, 'messages' => [$blockReason]];

                continue;
            }

            $model = new $modelClass;
            $model->forceFill(['shop_id' => $shop->id, ...$resolved['attributes']]);
            $model->save();
            $importer->applyRelations($model, $resolved['relations']);
            $processedThisRun[$key] = $model;
            $created++;
        }

        return [$created, $updated, $skipped, $errors];
    }

    public function failed(?Throwable $exception): void
    {
        Import::withoutGlobalScopes()->where('id', $this->importId)->update([
            'status' => ImportStatus::Failed,
            'errors' => [['row' => 0, 'messages' => [$exception?->getMessage() ?? 'The import failed unexpectedly.']]],
        ]);
        $this->cleanUp();
    }

    private function cleanUp(): void
    {
        Storage::disk('local')->delete($this->storedPath);
    }
}
