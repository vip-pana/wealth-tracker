<?php

// SPDX-FileCopyrightText: 2026 Vincenzo Panacciulli
// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_page_carries_the_hardening_headers(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
        $this->assertStringContainsString('microphone=()', (string) $response->headers->get('Permissions-Policy'));
    }

    public function test_the_health_check_carries_them_too(): void
    {
        $this->get('/up')->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_the_policy_locks_the_page_down_to_this_origin(): void
    {
        $policy = (string) $this->get('/')->headers->get('Content-Security-Policy');

        foreach ([
            "default-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "frame-ancestors 'none'",
            "form-action 'self'",
            "connect-src 'self'",
        ] as $directive) {
            $this->assertStringContainsString($directive, $policy);
        }
    }

    public function test_scripts_need_a_nonce_rather_than_being_inline_anything(): void
    {
        $policy = (string) $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[A-Za-z0-9]+'/", $policy);
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $policy);
    }

    public function test_styles_keep_unsafe_inline_and_no_nonce_so_it_is_not_cancelled_out(): void
    {
        $policy = (string) $this->get('/')->headers->get('Content-Security-Policy');

        preg_match('/style-src ([^;]+)/', $policy, $matches);

        $this->assertStringContainsString("'unsafe-inline'", $matches[1]);
        $this->assertStringNotContainsString('nonce-', $matches[1]);
    }

    public function test_the_inline_theme_script_carries_the_same_nonce_the_policy_announces(): void
    {
        $response = $this->get('/');

        preg_match("/'nonce-([A-Za-z0-9]+)'/", (string) $response->headers->get('Content-Security-Policy'), $matches);

        $this->assertNotEmpty($matches[1] ?? '');
        $response->assertSee('<script nonce="'.$matches[1].'">', escape: false);
    }

    public function test_the_nonce_is_new_on_every_request(): void
    {
        $first = (string) $this->get('/')->headers->get('Content-Security-Policy');
        $second = (string) $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertNotSame($first, $second);
    }

    public function test_hsts_is_sent_over_https_and_withheld_over_plain_http(): void
    {
        $this->get('/')->assertHeaderMissing('Strict-Transport-Security');

        $this->get('https://localhost/')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }
}
