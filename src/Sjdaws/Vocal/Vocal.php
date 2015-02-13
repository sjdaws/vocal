<?php

namespace Sjdaws\Vocal;

use Illuminate\Database\Eloquent\Model;
use Sjdaws\Vocal\Traits\Events;
use Sjdaws\Vocal\Traits\Hashing;
use Sjdaws\Vocal\Traits\Hydration;
use Sjdaws\Vocal\Traits\Messages;
use Sjdaws\Vocal\Traits\Relations;
use Sjdaws\Vocal\Traits\Rules;
use Sjdaws\Vocal\Traits\Model as SuperModel; // ;)
use Sjdaws\Vocal\Traits\Validation;

class Vocal extends Model
{
    use Events, Hashing, Hydration, Messages, Rules, Relations, SuperModel, Validation;

    /**
     * Create a new model instance
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = array())
    {
        parent::__construct($attributes);
        self::boot();
    }

    /**
     * Monitor events and trigger callbacks if they exist
     *
     * @see    \Illuminate\Database\Eloquent\Model::boot()
     * @return null
     */
    public static function boot()
    {
        parent::boot();

        // Add event callbacks
        self::addEventCallbacks(array('creat', 'delet', 'hydrat', 'restor', 'sav', 'updat', 'validat'));
    }

    /**
     * Create a new record
     *
     *
     */
    //public static function create()
    //{}

    /**
     * Save a single record
     *
     *
     */
    //public function save()
    //{}

    /**
     * Save a set of records
     *
     *
     */
    public function saveRecursive()
    {}

    /**
     * Validate a single record
     *
     *
     */
    public function validate()
    {}

    /**
     * Validate a set of records
     *
     *
     */
    public function validateRecursive()
    {}
}
