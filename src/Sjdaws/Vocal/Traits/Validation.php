<?php

namespace Sjdaws\Vocal\Traits;

trait Validation
{
    /**
     * Whether to validate before save or not
     *
     * @var bool
     */
    public $validateBeforeSave = false;

    /**
     * Register a validated model event with the dispatcher
     *
     * @param  Closure|string $callback
     * @return void
     */
    public static function validated($callback)
    {
        static::registerModelEvent('validated', $callback);
    }

    /**
     * Register a validating model event with the dispatcher
     *
     * @param  Closure|string $callback
     * @return void
     */
    public static function validating($callback)
    {
        static::registerModelEvent('validating', $callback);
    }
}
