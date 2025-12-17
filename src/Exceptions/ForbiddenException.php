<?php

namespace Stumason\Coolify\Exceptions;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ForbiddenException extends Exception
{
    /**
     * Render the exception as an HTTP response.
     */
    public function render(Request $request): Response
    {
        return response(
            view('coolify::forbidden'),
            403
        );
    }
}
