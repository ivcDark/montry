<?php

namespace app\Modules\Auth\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class LoginController
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }
}
