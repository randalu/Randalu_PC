<?php

namespace App\Http\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

trait InteractsWithCart
{
    protected function cartCustomerId(): ?int
    {
        $id = session('customer_id');

        return is_numeric($id) ? (int) $id : null;
    }

    protected function cartToken(Request $request): ?string
    {
        $token = $request->cookie('cart_token');

        return is_string($token) && $token !== '' ? $token : null;
    }

    protected function ensureCartToken(Request $request): string
    {
        if ($token = $this->cartToken($request)) {
            return $token;
        }

        $token = (string) Str::uuid();
        Cookie::queue('cart_token', $token, 60 * 24 * 30);

        return $token;
    }
}
