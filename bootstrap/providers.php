<?php

use App\Providers\ApiServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\PassportServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    PassportServiceProvider::class,
    ApiServiceProvider::class,
];
