<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DraftCatalogItemWithAiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match ($this->route('kind')) {
            'tools' => Gate::allows('tooling.create'),
            'materials' => Gate::allows('materials.create'),
            // A price row is added while creating or editing a supplier.
            'supplier-prices' => Gate::any(['business-partners.create', 'business-partners.update']),
            default => false,
        };
    }

    /**
     * Over the limit is an error, never a silent cut-off - half a spec sheet
     * would look complete and quietly miss the price.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'min:10', 'max:'.config('ai.max_message_length')],
            // Typed by the owner and handed back untouched - the model never
            // sees it or writes one.
            'source_url' => ['nullable', 'url:http,https', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'text.max' => 'That is too long to read in one go. Paste just the product details (up to :max characters).',
            'text.min' => 'Paste the product page text first.',
        ];
    }
}
