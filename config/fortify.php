<?php

use App\Http\Middleware\FortifyPasswordThrottle;
use Laravel\Fortify\Features;

return [

    'guard' => 'web',

    'passwords' => 'users',

    'username' => 'email',

    'email' => 'email',

    'lowercase_usernames' => true,

    'home' => '/dashboard',

    'prefix' => '',

    'domain' => null,

    'middleware' => ['web', FortifyPasswordThrottle::class],

    'limiters' => [
        'login' => 'login',
    ],

    'views' => true,

    'features' => [
        Features::resetPasswords(),
        Features::emailVerification(),
    ],

];
