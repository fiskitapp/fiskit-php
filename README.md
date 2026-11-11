# FisKit for PHP and Laravel

[Español más abajo](#español)

Official client of the [FisKit](https://fiskit.com.ar) API: Argentine electronic invoices with ARCA (formerly AFIP) in a single request. PHP 8.1 or later, no dependencies besides curl. Works with Laravel out of the box.

```bash
composer require fiskit/fiskit-php
```

```php
use Fiskit\Fiskit;

$fiskit = new Fiskit(getenv('FISKIT_API_KEY'));

// Your sale id as the key: a retry never issues a second invoice
$invoice = $fiskit->invoices->create(
    ['voucher_type' => 'invoice_c', 'amounts' => ['total' => 1500]],
    idempotencyKey: 'sale-8812',
);

echo $invoice['number'], ' ', $invoice['cae'];

$pdf = $fiskit->invoices->pdf($invoice['id']);                  // PDF with ARCA's QR code
$fiskit->invoices->email($invoice['id'], 'customer@example.com');
$taxpayer = $fiskit->taxpayers->retrieve(30712345671);          // name and VAT condition of a CUIT
```

## Laravel

Nothing to register: add `FISKIT_API_KEY` to `.env` and use the facade or inject `Fiskit\Fiskit`.

```php
use Fiskit\Laravel\Facades\Fiskit;

$invoice = Fiskit::invoices()->create(['voucher_type' => 'invoice_c', 'amounts' => ['total' => 1500]], "order-{$order->id}");
```

Webhooks: protect the route with the middleware (it reads `FISKIT_WEBHOOK_SECRET`) and read the verified event.

```php
Route::post('/webhooks/fiskit', function (Request $request) {
    $event = $request->attributes->get('fiskit_event');
    // $event['type'] === 'invoice.authorized'
})->middleware(\Fiskit\Laravel\VerifyWebhookSignature::class);
```

To customize the settings: `php artisan vendor:publish --tag=fiskit-config`.

`sk_test_...` keys issue against ARCA's homologation environment (no fiscal value); `sk_live_...` keys issue real invoices. Outages are retried with the same idempotency key. Errors are `Fiskit\FiskitException` with `status`, `type`, `errorCode`, `param` and `requestId`. Outside Laravel, `Fiskit\Webhook::verify($rawBody, $headers, $secret)` checks the signature.

Full documentation: https://fiskit.com.ar/developers

Coding with an AI agent? Point it to https://fiskit.com.ar/llms.txt: the API rules, errors and examples in plain text.

---

## Español

Cliente oficial de la API de [FisKit](https://fiskit.com.ar): facturas electrónicas de ARCA (ex AFIP) con un solo pedido. Para PHP 8.1 o superior, y listo para Laravel.

```php
$fiskit = new Fiskit\Fiskit(getenv('FISKIT_API_KEY'));

// Con el id de tu venta como clave, un reintento nunca duplica la factura
$factura = $fiskit->invoices->create(
    ['voucher_type' => 'invoice_c', 'amounts' => ['total' => 1500]],
    idempotencyKey: 'venta-8812',
);
```

**En Laravel** no hay que registrar nada: agregás `FISKIT_API_KEY` al `.env` y usás `Fiskit::invoices()->create([...])`. Para los webhooks, el middleware `VerifyWebhookSignature` verifica la firma con `FISKIT_WEBHOOK_SECRET`.

Las claves `sk_test_...` emiten en homologación, sin validez fiscal; las `sk_live_...` emiten facturas reales.

Documentación completa: https://fiskit.com.ar/developers

¿Programás con un agente de IA? Pasale https://fiskit.com.ar/llms.txt: las reglas, errores y ejemplos de la API en texto plano.
