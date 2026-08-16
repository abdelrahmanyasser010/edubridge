<?php

namespace App\Support\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

final class AppAccessDeniedException extends HttpException
{
    public function __construct(string $message = 'Your account is not authorized for this application.')
    {
        parent::__construct(403, $message);
    }
}
