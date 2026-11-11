<?php

declare(strict_types=1);

namespace Fiskit\Laravel;

use Closure;
use Fiskit\Webhook;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware that rejects webhooks without a valid FisKit signature (FISKIT_WEBHOOK_SECRET).
 * The verified event is left in $request->attributes->get('fiskit_event').
 *
 *     Route::post('/webhooks/fiskit', WebhookController::class)
 *         ->middleware(\Fiskit\Laravel\VerifyWebhookSignature::class);
 */
final class VerifyWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $event = Webhook::verify($request->getContent(), $request->headers->all(),
                (string) config('fiskit.webhook_secret'));
        } catch (\UnexpectedValueException) {
            abort(401, 'Invalid webhook signature');
        }
        $request->attributes->set('fiskit_event', $event);
        return $next($request);
    }
}
