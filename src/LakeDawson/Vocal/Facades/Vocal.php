<?php

namespace LakeDawson\Vocal\Facades;

use Illuminate\Support\Facades\Facade;

class Ardent extends Facade
{
    /**
     * Return the registered name of the component
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'vocal';
    }
}
