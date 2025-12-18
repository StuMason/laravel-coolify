<?php

namespace Stumason\Coolify\Exceptions;

class CoolifyNotFoundException extends CoolifyApiException
{
    public function __construct(string $message = 'The requested resource was not found in Coolify.', int $code = 404)
    {
        parent::__construct($message, $code);
    }
}
