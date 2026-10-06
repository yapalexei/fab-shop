<?php

namespace App\Http\Requests;

use App\Models\ShopAiSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateShopAiSettingsRequest extends FormRequest
{
    /**
     * Owner-only: the key spends money and the choice decides where the
     * shop's customer data is sent. Not delegable, same as plan changes.
     */
    public function authorize(): bool
    {
        return Gate::allows('manage-shop');
    }

    /**
     * Provider and model must come from config/ai.php - never free text. A
     * blank key keeps the stored one, so the owner can change the model
     * without re-entering (or ever seeing) the key.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $providers = array_keys(config('ai.providers'));
        $provider = $this->input('provider');
        $models = in_array($provider, $providers, true)
            ? array_keys(config("ai.providers.{$provider}.models"))
            : [];

        return [
            'provider' => ['required', Rule::in($providers)],
            'model' => ['required', Rule::in($models)],
            'api_key' => [
                Rule::requiredIf(fn () => ! $this->existingSetting()?->hasKey()),
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }

    public function existingSetting(): ?ShopAiSetting
    {
        return $this->user()->currentShop->aiSetting;
    }
}
