<?php

use App\Mcp\Servers\ShopServer;
use Laravel\Mcp\Facades\Mcp;

// Each shop's own AI connects here with a bearer token minted in Settings >
// AI. The token belongs to that shop's agent user, so the shop is implied by
// the token - there is no shop id in the URL to tamper with. Order matters:
// authenticate (Sanctum, tokens only), then confirm it is a shop agent in a
// shop that currently has AI, then rate limit.
Mcp::web('/mcp', ShopServer::class)
    ->middleware(['auth:sanctum', 'mcp.agent', 'throttle:mcp']);
