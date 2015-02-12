<?php

namespace Sjdaws\Vocal;

use Illuminate\Support\MessageBag;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;

class Validation extends SuperModel
{
    /**
     * The message bag instance containing validation error messages
     *
     * @var Illuminate\Support\MessageBag
     */
    protected $errors;

    /**
     * Whether to validate before save or not
     *
     * @var bool
     */
    public $validateBeforeSave = false;

    /**
     * Determine whether the model has been validated
     *
     * @var bool
     */
    private $_validatedByVocal = false;

    /**
     * Create a new model instance
     *
     * @param  array $attributes
     */
    public function __construct(array $attributes = array())
    {
        parent::__construct($attributes);

        // Create message bag for errors
        $this->errors = new MessageBag;

        // Boot model to enable hooks
        self::boot();
    }

    /**
     * Override to boot method of each model to attach before and after hooks
     *
     * @see    Illuminate\Database\Eloquent\Model::boot()
     * @return void
     */
    public static function boot()
    {
        parent::boot();

        $this->addEventCallbacks(array('validat'));
    }

    /**
     * Validate a single record
     *
     * @param  array $data
     * @param  array $rules
     * @param  array $messages
     * @return bool
     */
    public function validate(array $data = array(), array $rules = null, array $messages = array())
    {
        // Fill model attributes
        $this->hydrateModel($data);

        // Remove any fields from the model which can't be submitted, such as objects and arrays
        // - This will prevent errors with bound objects being saved twice
        $this->removeInvalidAttributes();

        // Fire validating event
        if ($this->fireModelEvent('validating') === false) return false;

        $ruleset = new Ruleset($this, $rules ?: $this->rules);
        $rules = $ruleset->get();

        // If we have no rules then validation will pass
        if ( ! count($rules))
        {
            $this->fireModelEvent('validated', false);
            return true;
        }
    }

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
