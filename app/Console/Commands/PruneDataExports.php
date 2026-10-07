<?php

namespace App\Console\Commands;

use App\Models\DataExport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneDataExports extends Command
{
    protected $signature = 'app:prune-data-exports';

    protected $description = 'Delete data export files past their expiry';

    public function handle(): int
    {
        DataExport::withoutGlobalScopes()
            ->whereNotNull('path')->where('expires_at', '<', now())
            ->each(function (DataExport $export) {
                Storage::delete($export->path);
                $export->delete();
            });

        return self::SUCCESS;
    }
}
