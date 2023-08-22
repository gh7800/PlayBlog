<?php

namespace App\Http\Requests;

class BaseRequest extends \Illuminate\Http\Request
{
    public function expectsJson(): bool
    {
        return true;
    }

    public function wantsJson(): bool
    {
        return true;
    }
}
