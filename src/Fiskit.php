<?php

declare(strict_types=1);

namespace Fiskit;

/**
 * Client of the FisKit API.
 *
 *     $fiskit = new Fiskit\Fiskit(getenv('FISKIT_API_KEY'));
 *     $invoice = $fiskit->invoices->create(
 *         ['voucher_type' => 'invoice_c', 'amounts' => ['total' => 1500]],
 *         idempotencyKey: 'sale-8812',
 *     );
 */
final class Fiskit
{
    public const VERSION = '0.1.0';
    public const DEFAULT_BASE_URL = 'https://api.fiskit.com.ar';
    private const RETRYABLE = [408, 409, 429, 500, 502, 503, 504];

    public readonly Invoices $invoices;
    public readonly Taxpayers $taxpayers;

    private readonly string $apiKey;
    private readonly string $baseUrl;
    private readonly int $maxRetries;
    private readonly float $timeout;
    /** @var callable(string, string, array<string, string>, ?string): array{status: int, headers: array<string, string>, body: string} */
    private $transport;

    /**
     * @param string|null $apiKey sk_test_... or sk_live_...; by default the FISKIT_API_KEY environment variable
     * @param array{base_url?: string, max_retries?: int, timeout?: float, transport?: callable} $options
     */
    public function __construct(?string $apiKey = null, array $options = [])
    {
        $apiKey ??= getenv('FISKIT_API_KEY') ?: null;
        if ($apiKey === null || $apiKey === '') {
            throw new \InvalidArgumentException('Missing API key: pass it or set FISKIT_API_KEY');
        }
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($options['base_url'] ?? self::DEFAULT_BASE_URL, '/');
        $this->maxRetries = $options['max_retries'] ?? 2;
        $this->timeout = $options['timeout'] ?? 60.0;
        $this->transport = $options['transport'] ?? new CurlTransport($this->timeout);
        $this->invoices = new Invoices($this);
        $this->taxpayers = new Taxpayers($this);
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>|string|null decoded JSON, or the raw bytes when $binary
     */
    public function request(string $method, string $path, ?array $body = null, ?string $idempotencyKey = null,
            bool $binary = false): array|string|null
    {
        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => $binary ? 'application/pdf' : 'application/json',
            'User-Agent' => 'fiskit-php/' . self::VERSION,
        ];
        $payload = null;
        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
            $payload = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        }
        if ($idempotencyKey !== null) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }
        // Without an idempotency key a POST is never repeated: it might have been processed
        $retryAllowed = $method === 'GET' || $idempotencyKey !== null;

        for ($attempt = 0; ; $attempt++) {
            try {
                $response = ($this->transport)($method, $this->baseUrl . $path, $headers, $payload);
            } catch (TransportException $e) {
                if ($retryAllowed && $attempt < $this->maxRetries) {
                    self::wait($attempt, null);
                    continue;
                }
                throw new FiskitException(0, 'connection_error', 'network', $e->getMessage());
            }
            $status = $response['status'];
            if ($status >= 200 && $status < 300) {
                if ($binary) {
                    return $response['body'];
                }
                return $response['body'] === '' ? null : json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
            }
            if (in_array($status, self::RETRYABLE, true) && $retryAllowed && $attempt < $this->maxRetries) {
                self::wait($attempt, $response['headers']['retry-after'] ?? null);
                continue;
            }
            throw FiskitException::fromResponse($status, $response['body'], $response['headers']['request-id'] ?? null);
        }
    }

    private static function wait(int $attempt, ?string $retryAfter): void
    {
        $seconds = is_numeric($retryAfter) ? (float) $retryAfter : 0.0;
        $delay = $seconds > 0 ? min($seconds, 30.0) : 0.5 * (2 ** $attempt);
        usleep((int) ($delay * 1_000_000));
    }
}
