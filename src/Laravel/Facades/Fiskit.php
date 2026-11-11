<?php

declare(strict_types=1);

namespace Fiskit\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Fiskit::invoices()->create([...]) from anywhere in a Laravel app.
 *
 * @method static \Fiskit\Invoices invoices()
 * @method static \Fiskit\Taxpayers taxpayers()
 */
final class Fiskit extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Fiskit\Fiskit::class;
    }

    public static function invoices(): \Fiskit\Invoices
    {
        return static::getFacadeRoot()->invoices;
    }

    public static function taxpayers(): \Fiskit\Taxpayers
    {
        return static::getFacadeRoot()->taxpayers;
    }
}
