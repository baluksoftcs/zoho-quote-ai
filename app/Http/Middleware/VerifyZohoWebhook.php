<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyZohoWebhook
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $expected = (string) config('zoho.webhook_secret');
        $provided = (string) $request->header('X-Webhook-Secret');

        // Constant-time comparison; also reject if the secret isn't configured
        if ($expected === '' || ! hash_equals($expected, $provided)) {
            abort(401, 'Invalid webhook secret');
        }
        return $next($request);
    }
}
