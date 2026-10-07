<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;

test('app pages carry a nonce-based policy and the page uses the same nonce', function () {
    $response = $this->get('/login');
    $csp = $response->headers->get('Content-Security-Policy');

    preg_match("/script-src [^;]*'nonce-([A-Za-z0-9]+)'/", $csp, $m);

    expect($m[1] ?? null)->not->toBeNull()
        ->and($csp)->toContain("default-src 'self'", "object-src 'self'", "frame-ancestors 'self'", "base-uri 'self'")
        ->and($csp)->not->toContain("script-src 'self' 'unsafe-inline'")
        ->and($response->getContent())->toContain('nonce="'.$m[1].'"');
});

test('every inline script in the page template carries the nonce', function () {
    $html = $this->get('/login')->getContent();

    preg_match_all('/<script(?![^>]*\bsrc=)([^>]*)>/', $html, $scripts);

    foreach ($scripts[1] as $attributes) {
        if (str_contains($attributes, 'application/json')) {
            continue; // Data blocks are not executed.
        }
        expect($attributes)->toContain('nonce=');
    }
});

test('the nonce is different on every request', function () {
    $first = $this->get('/login')->headers->get('Content-Security-Policy');
    $second = $this->get('/login')->headers->get('Content-Security-Policy');

    expect($first)->not->toBe($second);
});

test('the mode can be switched to report-only or off', function () {
    config(['security.csp' => 'report-only']);
    $response = $this->get('/login');
    expect($response->headers->has('Content-Security-Policy'))->toBeFalse()
        ->and($response->headers->has('Content-Security-Policy-Report-Only'))->toBeTrue();

    config(['security.csp' => 'off']);
    $response = $this->get('/login');
    expect($response->headers->has('Content-Security-Policy'))->toBeFalse()
        ->and($response->headers->has('Content-Security-Policy-Report-Only'))->toBeFalse();
});

test('the live-update socket is allowed to connect', function () {
    config(['security.reverb' => ['host' => 'ws.example.com', 'port' => '443', 'scheme' => 'https']]);

    expect($this->get('/login')->headers->get('Content-Security-Policy'))->toContain('connect-src', 'wss://ws.example.com:443');
});

test('an attachment keeps its own stricter policy', function () {
    // Attachments set a sandboxed CSP of their own; the global one must not replace it.
    $middleware = new SecurityHeaders;
    $response = $middleware->handle(Request::create('/x'), function () {
        return response('x')->header('Content-Security-Policy', "default-src 'none'; sandbox");
    });

    expect($response->headers->get('Content-Security-Policy'))->toBe("default-src 'none'; sandbox");
});
