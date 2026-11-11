<?php

declare(strict_types=1);

namespace Fiskit;

/** Checks the webhooks FisKit sends (Standard Webhooks signatures). */
final class Webhook
{
    /**
     * Checks the signature and returns the parsed event. Pass the raw body as received, before parsing.
     *
     * @param array<string, string|string[]> $headers
     * @return array<string, mixed>
     * @throws \UnexpectedValueException if the signature is wrong or the message is too old
     */
    public static function verify(string $rawBody, array $headers, string $secret, int $toleranceSeconds = 300,
            ?int $now = null): array
    {
        $lowered = [];
        foreach ($headers as $name => $value) {
            $lowered[strtolower((string) $name)] = is_array($value) ? (string) reset($value) : (string) $value;
        }
        $id = $lowered['webhook-id'] ?? '';
        $timestamp = $lowered['webhook-timestamp'] ?? '';
        $signatures = $lowered['webhook-signature'] ?? '';
        if ($id === '' || $timestamp === '' || $signatures === '') {
            throw new \UnexpectedValueException('Missing webhook headers');
        }
        if (abs(($now ?? time()) - (int) $timestamp) > $toleranceSeconds) {
            throw new \UnexpectedValueException('Webhook timestamp out of tolerance');
        }
        $key = base64_decode((string) preg_replace('/^whsec_/', '', $secret), true);
        $expected = base64_encode(hash_hmac('sha256', $id . '.' . $timestamp . '.' . $rawBody, (string) $key, true));
        foreach (explode(' ', $signatures) as $candidate) {
            if (hash_equals($expected, (string) preg_replace('/^v1,/', '', $candidate))) {
                return json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
            }
        }
        throw new \UnexpectedValueException('Invalid webhook signature');
    }
}
