<?php

use App\Providers\AppServiceProvider;
use App\Providers\ScrambleServiceProvider;
use App\Providers\TenancyServiceProvider;

return [
    AppServiceProvider::class,
    ScrambleServiceProvider::class,
    TenancyServiceProvider::class,
];
