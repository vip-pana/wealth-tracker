<?php

// SPDX-FileCopyrightText: 2026 Vincenzo Panacciulli
// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * The response headers that tell the browser what this app is allowed to do.
 *
 * Worth having even on a single-user instance: the pages render the user's
 * whole financial history, the AI advisor writes model output into the DOM,
 * and bank transaction descriptions are third-party text. A content-injection
 * bug anywhere in that chain is the difference between a rendering glitch and
 * an exfiltration, and the Content-Security-Policy is what makes it the
 * former.
 *
 * The policy can be this strict because the frontend loads nothing from
 * anywhere else: no CDN, no web font, no analytics, no external image. The
 * only exception is the Vite dev server, which is allowed only while it is
 * actually running.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Generated before the view renders so the @vite tags and the inline
        // theme script in app.blade.php can carry it. A fresh value per
        // request is the whole point: a nonce an attacker can predict is not
        // a nonce.
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        foreach ($this->headers($request, $nonce) as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }

    /** @return array<string, string> */
    private function headers(Request $request, string $nonce): array
    {
        $headers = [
            'Content-Security-Policy' => $this->contentSecurityPolicy($nonce),

            // Belt and braces with CSP's frame-ancestors, which older browsers
            // ignore. Nothing in the app is meant to be framed.
            'X-Frame-Options' => 'DENY',

            // Stops a response the app meant as data (a CSV export, a JSON
            // payload) from being executed because the browser guessed a
            // different type for it.
            'X-Content-Type-Options' => 'nosniff',

            // Outbound links must not carry the path they came from. Several
            // routes put a month, a category or a goal id in the URL.
            'Referrer-Policy' => 'strict-origin-when-cross-origin',

            // The app asks for none of these. Denying them up front means a
            // script that somehow runs here cannot ask on its behalf.
            'Permissions-Policy' => 'accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()',

            // Severs the window reference between this page and anything it
            // opens or is opened by.
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];

        // Only over HTTPS. Sent over plain http it is ignored by browsers, and
        // self-hosting without a TLS terminator in front is a supported setup
        // (see docs/self-hosting.md), so it must not be a blanket header.
        //
        // No includeSubDomains: the app may well be one name among several on
        // a domain whose other hosts are not ours to force onto HTTPS.
        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000';
        }

        return $headers;
    }

    private function contentSecurityPolicy(string $nonce): string
    {
        $directives = [
            "default-src 'self'",

            // Nonce rather than 'unsafe-inline': the one inline script is the
            // theme flash guard in app.blade.php, and Laravel's Vite helper
            // stamps the same nonce on the tags it generates.
            "script-src 'self' 'nonce-{$nonce}'",

            // 'unsafe-inline' here is not a shortcut we can close. Radix
            // positions its popovers and Recharts sizes its SVGs through
            // style attributes written at render time, and a nonce cannot
            // cover a style attribute. Note that adding a nonce to this
            // directive would make browsers *ignore* 'unsafe-inline' and
            // break both — so it deliberately has none.
            "style-src 'self' 'unsafe-inline'",

            // data: for the inlined icons Vite emits under its asset size
            // threshold; blob: for canvas-rendered chart exports.
            "img-src 'self' data: blob:",

            "font-src 'self' data:",
            "connect-src 'self'",
            "manifest-src 'self'",
            "worker-src 'self'",

            // No plugins, no <base> rewriting, no framing, and forms can only
            // post back to this origin.
            "object-src 'none'",
            "base-uri 'self'",
            "frame-ancestors 'none'",
            "form-action 'self'",
        ];

        if (Vite::isRunningHot()) {
            $directives = $this->allowViteDevServer($directives);
        }

        return implode('; ', $directives);
    }

    /**
     * Under `vite dev` the scripts, the stylesheets and the HMR socket all come
     * from a second origin. It is read from the hot file Vite itself writes, so
     * the policy follows whatever host and port the dev server was started on
     * rather than hard-coding localhost — the container publishes a different
     * port, and DEV_HOST moves it onto the LAN address when developing against
     * a phone.
     *
     * @param  list<string>  $directives
     * @return list<string>
     */
    private function allowViteDevServer(array $directives): array
    {
        $origin = rtrim(trim((string) file_get_contents(Vite::hotFile())), '/');
        $socket = str_starts_with($origin, 'https://')
            ? 'wss://'.substr($origin, 8)
            : 'ws://'.substr($origin, 7);

        $extra = [
            'script-src' => "{$origin} 'unsafe-eval'",
            'style-src' => $origin,
            'connect-src' => "{$origin} {$socket}",
        ];

        return array_map(function (string $directive) use ($extra): string {
            $name = explode(' ', $directive)[0];

            return isset($extra[$name]) ? $directive.' '.$extra[$name] : $directive;
        }, $directives);
    }
}
