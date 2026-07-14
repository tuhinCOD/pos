<?php

namespace Modules\Credit\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class CreditServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Credit';
    protected string $nameLower = 'credit';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];
}
