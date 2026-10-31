<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Laravel\Fortify\Http\Responses\LoginResponse;
use Symfony\Component\HttpFoundation\Response;

class PasswordConfirmedLoginResponse extends LoginResponse
{
    /** @param Request $request */
    public function toResponse(mixed $request): Response
    {
        $request->session()->passwordConfirmed();

        return parent::toResponse($request);
    }
}
