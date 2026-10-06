<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DraftQuoteWithAiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('quotes.create');
    }

    /**
     * Over the limit is an error, never a silent cut-off: a draft built from
     * half an email would look complete and quietly miss lines.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:10', 'max:'.config('ai.max_message_length')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'message.max' => 'That is too long to draft from in one go. Paste the part that describes the work (up to :max characters).',
            'message.min' => 'Paste the customer\'s request first.',
        ];
    }
}
