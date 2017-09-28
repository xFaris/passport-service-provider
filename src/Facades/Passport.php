<?php

namespace Faris\Passport\Facades;

use Illuminate\Support\Facades\Facade;

class Passport extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'passport';
    }
}
