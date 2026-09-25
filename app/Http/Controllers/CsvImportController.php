<?php

namespace App\Http\Controllers;

use App\Enums\ImportStatus;
use App\Http\Requests\StoreImportRequest;
use App\Jobs\ProcessCsvImport;
use App\Models\Import;
use App\Support\CsvImport\ImporterRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Owner-only CSV import, one controller shared by every importable resource
 * (see App\Support\CsvImport\ImporterRegistry) rather than one per resource -
 * the mechanics (template, upload, status poll) are identical; only the
 * column shape and per-row resolution differ, and those live on the
 * ResourceImporter itself.
 */
class CsvImportController extends Controller
{
    public function template(string $resource): HttpResponse
    {
        $importer = ImporterRegistry::for($resource);
        $shop = Auth::user()->currentShop;

        abort_unless($shop->hasFeature($importer->feature()), 403);

        $csv = implode(',', $importer->templateColumns())."\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$resource}-template.csv\"",
        ]);
    }

    /**
     * Returns plain JSON (not an Inertia redirect) so the upload dialog -
     * itself already inside a loaded page, not navigating anywhere - gets
     * the new import's id back immediately to start polling `show()` with.
     */
    public function store(StoreImportRequest $request, string $resource): JsonResponse
    {
        $importer = ImporterRegistry::for($resource);
        $shop = Auth::user()->currentShop;

        abort_unless($shop->hasFeature($importer->feature()), 403);

        $storedPath = $request->file('file')->store('imports');

        $import = Import::create([
            'resource' => $resource,
            'status' => ImportStatus::Pending,
            'overwrite' => $request->boolean('overwrite'),
            'original_filename' => $request->file('file')->getClientOriginalName(),
            'imported_by' => Auth::id(),
        ]);

        ProcessCsvImport::dispatch($import->id, $storedPath);

        return response()->json(['id' => $import->id]);
    }

    /**
     * Polled from the upload dialog while status is "pending" - plain JSON,
     * not an Inertia page, since this is read from inside an already-loaded
     * page rather than a navigation.
     */
    public function show(Import $import): JsonResponse
    {
        return response()->json([
            'id' => $import->id,
            'resource' => $import->resource,
            'status' => $import->status->value,
            'original_filename' => $import->original_filename,
            'created_count' => $import->created_count,
            'updated_count' => $import->updated_count,
            'skipped_count' => $import->skipped_count,
            'errors' => $import->errors,
        ]);
    }
}
