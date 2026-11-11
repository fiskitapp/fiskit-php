<?php

declare(strict_types=1);

namespace Fiskit\Tests;

use Fiskit\Fiskit;
use Fiskit\FiskitException;
use Fiskit\TransportException;
use Fiskit\Webhook;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    private const INVOICE = ['id' => 'inv_1', 'object' => 'invoice', 'status' => 'authorized', 'number' => 42,
        'cae' => '76451234567890'];

    /** @var list<array{method: string, url: string, headers: array<string, string>, body: ?string}> */
    private array $requests = [];

    /** A transport that answers from a list and records every request. */
    private function client(array $answers, array $options = []): Fiskit
    {
        $transport = function (string $method, string $url, array $headers, ?string $body) use (&$answers): array {
            $this->requests[] = compact('method', 'url', 'headers', 'body');
            $answer = array_shift($answers);
            if ($answer instanceof \Throwable) {
                throw $answer;
            }
            return $answer;
        };
        return new Fiskit('sk_test_x', $options + ['transport' => $transport, 'base_url' => 'https://api.test']);
    }

    private static function json(int $status, array $body, array $headers = []): array
    {
        return ['status' => $status, 'headers' => $headers, 'body' => json_encode($body)];
    }

    public function testCreatesAnInvoiceWithTheKeyTheBodyAndAnIdempotencyKey(): void
    {
        $fiskit = $this->client([self::json(200, self::INVOICE)]);

        $invoice = $fiskit->invoices->create(['voucher_type' => 'invoice_c', 'amounts' => ['total' => 1500]], 'sale-1');

        self::assertSame('76451234567890', $invoice['cae']);
        $request = $this->requests[0];
        self::assertSame('POST', $request['method']);
        self::assertSame('https://api.test/v1/invoices', $request['url']);
        self::assertSame('Bearer sk_test_x', $request['headers']['Authorization']);
        self::assertSame('sale-1', $request['headers']['Idempotency-Key']);
        self::assertSame(['voucher_type' => 'invoice_c', 'amounts' => ['total' => 1500]], json_decode($request['body'], true));
    }

    public function testRetriesAnOutageWithTheSameGeneratedIdempotencyKey(): void
    {
        $fiskit = $this->client([
            self::json(503, ['error' => ['code' => 'authority_unavailable']], ['retry-after' => '0']),
            new TransportException('reset'),
            self::json(200, self::INVOICE),
        ]);

        $fiskit->invoices->create(['voucher_type' => 'invoice_c', 'amounts' => ['total' => 1]]);

        self::assertCount(3, $this->requests);
        $keys = array_unique(array_map(static fn ($r) => $r['headers']['Idempotency-Key'], $this->requests));
        self::assertCount(1, $keys);
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', reset($keys));
    }

    public function testApiErrorsBecomeAFiskitException(): void
    {
        $fiskit = $this->client([self::json(400, ['error' => ['type' => 'invalid_request_error',
            'code' => 'parameter_invalid', 'message' => 'Bad total', 'param' => 'amounts.total', 'request_id' => 'req_1']])]);

        try {
            $fiskit->invoices->retrieve('inv_1');
            self::fail('Expected an exception');
        } catch (FiskitException $e) {
            self::assertSame(400, $e->status);
            self::assertSame('parameter_invalid', $e->errorCode);
            self::assertSame('amounts.total', $e->param);
            self::assertSame('req_1', $e->requestId);
            self::assertSame('Bad total', $e->getMessage());
        }
    }

    public function testThePdfComesBackAsBytesAndTheTaxpayerByCuit(): void
    {
        $fiskit = $this->client([
            ['status' => 200, 'headers' => [], 'body' => '%PDF-1.7'],
            self::json(200, ['object' => 'taxpayer', 'vat_condition' => 'registered']),
        ]);

        self::assertSame('%PDF-1.7', $fiskit->invoices->pdf('inv_1'));
        self::assertSame('registered', $fiskit->taxpayers->retrieve(30712345671)['vat_condition']);
        self::assertSame('application/pdf', $this->requests[0]['headers']['Accept']);
        self::assertStringEndsWith('/v1/taxpayers/30712345671', $this->requests[1]['url']);
    }

    public function testAPostWithoutIdempotencyKeyIsNeverRetried(): void
    {
        $fiskit = $this->client([new TransportException('reset')]);

        try {
            $fiskit->invoices->email('inv_1', 'a@b.com');
            self::fail('Expected an exception');
        } catch (FiskitException $e) {
            self::assertSame('network', $e->errorCode);
        }
        self::assertCount(1, $this->requests);
    }

    public function testVerifiesWebhooksWithTheStandardWebhooksExample(): void
    {
        // Example from the Standard Webhooks specification
        $secret = 'whsec_MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw';
        $headers = [
            'webhook-id' => 'msg_p5jXN8AQM9LWM0D4loKWxJek',
            'webhook-timestamp' => '1614265330',
            'webhook-signature' => 'v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE=',
        ];

        self::assertSame(['test' => 2432232314], Webhook::verify('{"test": 2432232314}', $headers, $secret, now: 1614265330));

        $this->expectException(\UnexpectedValueException::class);
        Webhook::verify('{"test": 1}', $headers, $secret, now: 1614265330);
    }

    public function testRejectsOldWebhooks(): void
    {
        $this->expectExceptionMessage('tolerance');
        Webhook::verify('{"test": 2432232314}', [
            'Webhook-Id' => 'msg_p5jXN8AQM9LWM0D4loKWxJek',
            'Webhook-Timestamp' => '1614265330',
            'Webhook-Signature' => 'v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE=',
        ], 'whsec_MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw', now: 1614265330 + 3600);
    }
}
