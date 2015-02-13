<?php

namespace Sjdaws\Vocal;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;

class Validation
{
    /**
     * The message bag instance containing validation error messages
     *
     * @var Illuminate\Support\MessageBag
     */
    private $errors;

    /**
     * The model we're checking relationships against
     *
     * @var Vocal
     */
    private $model;

    /**
     * The validation error messages
     *
     * @var array
     */
    private $messages = array();

    /**
     * Determine whether the model has been validated
     *
     * @var bool
     */
    private $result = false;

    /**
     * The active rule set
     *
     * @var array
     */
    private $rules = array();

    /**
     * Create a new validator
     *
     * @param Vocal $model
     * @param array $rules
     * @param array $messages
     */
    public function __construct(Vocal $model, array $rules, array $messages)
    {
        $this->messages = $messages;
        $this->model = $model;

        $ruleset = new Ruleset($this->model, $rules);
        $this->rules = $ruleset->get();

        // Create message bag for errors
        $this->errors = new MessageBag;
    }

    /**
     * Get validation errors
     *
     * @return MessageBag
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Get the validation result
     *
     * @return bool
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * Validate a single record
     *
     * @return bool
     */
    public function validate()
    {
        // If this model has already been validated, we don't need to do it again
        if ($this->result) return true;

        // If we have no rules then validation will pass
        if ( ! count($this->rules)) return true;

        // Create a new validator, and capture result
        $validator = Validator::make($this->model->getAttributes(), $this->rules, $this->messages);
        $this->result = $validator->passes();

        // Update errors based on result
        $this->errors = ($this->result) ? new MessageBag : $validator->messages();

        return $this->result;
    }


}
