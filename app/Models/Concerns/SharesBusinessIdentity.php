<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Keeps a business's name and contact details the same on every role it
 * holds: saving one (from a form, the AI, an import - any path) copies the
 * changed fields to the other. Notes and logos are deliberately not shared;
 * what you note about a supplier is not what you want on a customer.
 *
 * Needs a `business_id` column and a `counterpart()` returning the other
 * role's record (or null).
 */
trait SharesBusinessIdentity
{
    /** @var array<int, string> */
    public const SHARED_FIELDS = ['name', 'email', 'phone', 'address'];

    protected static function bootSharesBusinessIdentity(): void
    {
        static::saved(function (Model $model) {
            $changes = Arr::only($model->getChanges(), self::SHARED_FIELDS);

            if ($changes === [] || ! $model->business_id) {
                return;
            }

            // Quietly, so the other side doesn't echo the change back.
            $model->counterpart()?->forceFill($changes)->saveQuietly();
        });
    }
}
