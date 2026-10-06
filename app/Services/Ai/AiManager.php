<?php

namespace App\Services\Ai;

use App\Enums\Feature;
use App\Models\Shop;
use App\Models\ShopAiSetting;
use App\Services\Ai\Contracts\TextModel;

/**
 * The one way the app gets a model for a shop. It takes the Shop explicitly
 * (never "whoever is logged in") so queued work, which has no authenticated
 * user, resolves the same way a request does - and it re-checks the plan
 * every time, so a job queued before a trial ended stops working the moment
 * the shop is no longer entitled, with no separate expiry logic.
 */
class AiManager
{
    /**
     * @throws AiException
     */
    public function textModelFor(Shop $shop): TextModel
    {
        if (! $shop->hasFeature(Feature::AiAssist)) {
            throw AiException::notEntitled();
        }

        $setting = $shop->aiSetting;

        if (! $setting || ! $setting->hasKey()) {
            throw AiException::notConfigured();
        }

        return $this->build($setting);
    }

    /**
     * A model from an unsaved or just-edited setting - used by "Test
     * connection" so a key can be checked before it is stored.
     */
    public function build(ShopAiSetting $setting): TextModel
    {
        return match ($setting->provider) {
            'anthropic' => new AnthropicTextModel($setting->api_key, $setting->model),
            'openai' => new OpenAiTextModel($setting->api_key, $setting->model),
            default => throw AiException::notConfigured(),
        };
    }

    /**
     * A tiny real call, so a bad key, a wrong model or no credit shows up
     * when the owner saves - not the first time a quote is drafted.
     *
     * @throws AiException
     */
    public function test(ShopAiSetting $setting): void
    {
        $this->build($setting)->complete('Reply with the single word: ok', 'ping');
    }
}
