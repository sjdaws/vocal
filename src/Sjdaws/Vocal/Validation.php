<?php

namespace Sjdaws\Vocal;

use Illuminate\Support\MessageBag;
use Illuminate\Support\Facades\Input;
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

        // Add event callbacks
        $this->addEventCallbacks(array('validat'));
    }

    /**
     * Add or merge validation errors
     *
     * @param  MessageBag $bag
     * @param  MessageBag $errors
     * @param  integer    $index
     * @return MessageBag
     */
    private function captureValidationErrors(MessageBag $bag, MessageBag $errors, $index = null)
    {
        if ( ! $errors->count()) return $bag;

        // Add or merge errors into bag
        if ($index) $bag->add($index, $errors);
        else $bag->merge($errors);

        return $bag;
    }

    /**
     * Find a record, validate it and return it
     *
     * @param  Model $model
     * @param  array $data
     * @param  array $rules
     * @param  array $messages
     * @return MessageBag
     */
    private function validateRecord(Model $model, array $data, array $rules = array(), array $messages = array())
    {
        // Find or create record
        $primaryKey = (isset($model->primaryKey)) ? $model->primaryKey : 'id';
        $key = isset($data[$primaryKey]) ? $data[$primaryKey] : null;
        $record = $this->findOrCreateRecord($model, $key);

        // Validate and return errors
        $record->validate($data, $rules, $messages);

        return $record->errors;
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
     * Recursively validate a recordset
     *
     * @param  array $data
     * @param  array $conditions
     * @param  array $rules
     * @param  array $messages
     * @return bool
     */
    public function validateRecursive(array $data = array(), array $conditions = array(), array $rules = array(), array $messages = array())
    {
        // If we don't have any data passed, use input
        if ( ! count($data)) $data = Input::all();

        // Validate this record
        $this->validate($data, $rules, $messages);

        // See if we have any relationships to validate
        $relationships = $this->getRelationships($data, $conditions);

        // If we don't have any relationships, return validation result
        if ( ! count($relationships)) return ($this->errors->count() == 0);

        $result = $this->validateRelationships($relationships, $data, $conditions, $rules, $messages);

        return $result;
    }

    /**
     * Recursively validate relationships
     *
     * @param  array $relationships
     * @param  array $data
     * @param  array $conditions
     * @param  array $rules
     * @param  array $messages
     * @return bool
     */
    private function validateRelationships(array $relationships, array $data = array(), array $conditions = array(), array $rules = array(), array $messages = array())
    {
        foreach ($relationships as $model => $type)
        {
            // Create a new message bag for errors
            $errors = new MessageBag;

            // Instantiate class/model we're working on
            $instance = $this->{Str::camel($model)}->getModel();

            // Extract rules and messages we will use specifically for this relationship
            list($conditions, $messages, $rules) = $this->getRelationshipData($model, $conditions, $rules, $messages);

            // Validate based on record type
            if ($type == 'one')
            {
                $result = $this->validateRecord($instance, $data[$model], $rules, $messages);
                $errors = $this->captureValidationErrors($errors, $result);
            }
            else
            {
                foreach ($data[$model] as $index => $relationship)
                {
                    // Find or create record
                    $record = $this->findOrCreateRecord($instance, $relationship, $rules, $messages);
                    $errors = $this->captureValidationErrors($errors, $result, $index);

                    // Validate children if we have some
                    $cousins = $record->getRelationships($relationship, $conditions);

                    if (count($cousins))
                    {
                        $result = $record->validateRelations($cousins, $relationship, $conditions, $rules, $messages);
                        if ( ! $result) $errors->add($index, $record->errors);
                    }
                }
            }

            // Merge in any errors we have
            $this->errors = $this->captureValidationErrors($this->errors, $errors, $model);
        }

        return ($this->errors->count() == 0);
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
