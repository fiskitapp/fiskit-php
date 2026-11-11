<?php

declare(strict_types=1);

namespace Fiskit;

final class Taxpayers
{
    public function __construct(private readonly Fiskit $client)
    {
    }

    /**
     * Name, fiscal address and VAT condition of a CUIT, from the ARCA registry.
     *
     * @return array<string, mixed>
     */
    public function retrieve(int|string $cuit): array
    {
        return $this->client->request('GET', '/v1/taxpayers/' . rawurlencode((string) $cuit));
    }
}
