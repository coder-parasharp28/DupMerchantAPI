<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     */
    protected $routeMiddleware = [
        'throttle.transactions' => \App\Http\Middleware\ThrottleTransactions::class,
    ];
}