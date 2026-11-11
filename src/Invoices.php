<?php

declare(strict_types=1);

namespace Fiskit;

final class Invoices
{
    public function __construct(private readonly Fiskit $client)
    {
    }

    /**
     * Issues an invoice. Without an idempotency key one is generated, so the automatic retries never
     * issue a second invoice. Pass your own (for example your sale id) to make your retries safe too.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function create(array $body, ?string $idempotencyKey = null): array
    {
        return $this->client->request('POST', '/v1/invoices', $body, $idempotencyKey ?? self::uuid());
    }

    /** @return array<string, mixed> */
    public function retrieve(string $id): array
    {
        return $this->client->request('GET', '/v1/invoices/' . rawurlencode($id));
    }

    /** The voucher PDF, with the QR code ARCA requires, as raw bytes. */
    public function pdf(string $id): string
    {
        return $this->client->request('GET', '/v1/invoices/' . rawurlencode($id) . '/pdf', binary: true);
    }

    /**
     * Emails the PDF to the customer; without an email, to receiver.email of the invoice.
     *
     * @return array<string, mixed>
     */
    public function email(string $id, ?string $email = null): array
    {
        return $this->client->request('POST', '/v1/invoices/' . rawurlencode($id) . '/email',
            $email === null ? [] : ['email' => $email]);
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
