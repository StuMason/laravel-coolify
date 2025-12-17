<?php

namespace Stumason\Coolify\Http\Controllers;

use Illuminate\Routing\Controller as BaseController;

abstract class Controller extends BaseController
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware(config('coolify.middleware', 'web'));
    }
}
