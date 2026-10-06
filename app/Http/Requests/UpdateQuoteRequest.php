<?php

namespace App\Http\Requests;

use App\Models\Quote;
use Illuminate\Validation\Validator;

class UpdateQuoteRequest extends StoreQuoteRequest
{
    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            /** @var Quote $quote */
            $quote = $this->route('quote');

            if ($quote->customer_locked && (int) $this->input('customer_id') !== $quote->customer_id) {
                $validator->errors()->add('customer_id', "The customer can't be changed once the quote has been emailed, unless the email failed to deliver.");
            }
        }];
    }
}
