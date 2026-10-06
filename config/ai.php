<?php

/*
|--------------------------------------------------------------------------
| AI assistant
|--------------------------------------------------------------------------
|
| A shop brings its own provider key (Settings > AI) and picks one of the
| models listed here. The list is curated rather than free text so a typo
| can't silently break every AI feature - add a model by adding a line.
| Provider base URLs are fixed on purpose: no shop-supplied endpoint, so a
| key field can never be turned into a request to an arbitrary host.
|
*/

return [

    'providers' => [
        'anthropic' => [
            'label' => 'Anthropic (Claude)',
            'base_url' => 'https://api.anthropic.com',
            'default_model' => 'claude-sonnet-5-5',
            'models' => [
                'claude-sonnet-5-5' => 'Claude Sonnet 5.5 (recommended)',
                'claude-haiku-4-5' => 'Claude Haiku 4.5 (fastest, cheapest)',
                'claude-opus-5-5' => 'Claude Opus 5.5 (most capable)',
            ],
        ],
        'openai' => [
            'label' => 'OpenAI',
            'base_url' => 'https://api.openai.com',
            'default_model' => env('AI_OPENAI_DEFAULT_MODEL', 'gpt-4o'),
            // Confirm these against OpenAI's current model list before
            // launch - they are the conservative, long-lived names.
            'models' => [
                'gpt-4o' => 'GPT-4o',
                'gpt-4o-mini' => 'GPT-4o mini (cheapest)',
            ],
        ],
    ],

    // Seconds to wait on a provider before giving up (the owner is waiting
    // on a button, so fail fast rather than hang the request).
    'timeout' => (int) env('AI_TIMEOUT', 30),

    // Upper bound on a single completion.
    'max_output_tokens' => (int) env('AI_MAX_OUTPUT_TOKENS', 4096),

    // Most catalog items per list (materials, services, ...) sent with a
    // request, to keep the prompt small. Shops over it are told the draft
    // only saw part of their catalog.
    'max_catalog_items' => (int) env('AI_MAX_CATALOG_ITEMS', 300),

    // Longest customer message accepted for quote intake, in characters.
    'max_message_length' => (int) env('AI_MAX_MESSAGE_LENGTH', 20000),

    // Tool calls per minute one shop's connected AI may make over MCP.
    'mcp_requests_per_minute' => (int) env('AI_MCP_REQUESTS_PER_MINUTE', 120),

    // How long an MCP token lives unless the owner picks otherwise, in days.
    'mcp_token_days' => (int) env('AI_MCP_TOKEN_DAYS', 90),

    // Below this, a suggestion is shown as "needs a look" instead of being
    // pre-filled (used by the AI features, not by the connection test).
    'confidence_threshold' => (float) env('AI_CONFIDENCE_THRESHOLD', 0.7),

];
