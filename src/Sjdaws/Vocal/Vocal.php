<?php

namespace Sjdaws\Vocal;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use Illuminate\Hashing\BcryptHasher;
use Illuminate\Support\Str;
use Illuminate\Support\MessageBag;

/**
 * @property int $id
 */
class Vocal extends Model
{
    /**
     * Store the differences for updated fields
     *
     * @var array
     */
    protected $diff = array();

    /**
     * Fields to ignore when comparing diff
     *
     * @var array
     */
    protected $diffIgnore = array();

    /**
     * The message bag instance containing validation error messages
     *
     * @var Illuminate\Support\MessageBag
     */
    protected $errors;

    /**
     * Fill all models from input by default
     *
     * @var bool
     */
    protected $fillFromInput = true;

    /**
     * Hash attributes automatically on save
     *
     * @var array
     */
    protected $hashable = array();

    /**
     * Whether to remove invalid/non-scalar attributes before saving
     *
     * @var bool
     */
    protected $unsetInvalidAttributes = true;

    /**
     * Whether to validate before save or not
     *
     * @var bool
     */
    public $validateBeforeSave = false;

    /**
     * Determine whether the model has been hydrated
     *
     * @var bool
     */
    private $_hydratedByVocal = false;

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
     * @return Sjdaws\Vocal\Vocal
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
     * Attach a model instance to the parent model
     *
     * @param  Illuminate\Database\Eloquent\Model $model
     * @return Illuminate\Database\Eloquent\Model
     */
    public function attachToParent(Model $model)
    {
        $model->setAttribute($this->getPlainForeignKey(), $this->getParentKey());

        return $model->forceSave() ? $model : false;
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

        $hooks    = array('before' => 'ing', 'after' => 'ed');
        $radicals = array('creat', 'delet', 'hydrat', 'sav', 'updat', 'validat');

        foreach ($radicals as $rad)
        {
            foreach ($hooks as $hook => $event)
            {
                $method = $hook . ucfirst($rad) . 'e';

                if (method_exists(get_called_class(), $method))
                {
                    $eventMethod = $rad . $event;

                    self::{$eventMethod}(function($model) use ($method)
                    {
                        return $model->{$method}($model);
                    });
                }
            }
        }
    }

    /**
     * Build validation rules
     * - This function replaces ~attributes with their values
     * - ~table will be replaced with the table name and ~field will be replaced with the field name
     *   providing there is no column named table or field respectively
     * - This function also auto builds 'unique' rules when the a rule is just passed as 'unique'
     *
     * @param array $rules
     * @return array
     */
    private function expandRuleset($rules)
    {
        // Replace any tilde rules with the correct attribute
        foreach ($rules as $field => &$ruleset)
        {
            // If rules are pipe delimited, change them to array
            if (is_string($ruleset)) $ruleset = explode('|', $ruleset);

            foreach ($ruleset as &$rule)
            {
                // Seperate rule type from rule
                if (strpos($rule, ':') !== false) list($type, $parameters) = explode(':', $rule, 2);
                else
                {
                    $type = $rule;
                    $parameters = null;
                }

                // Make parameters into an array
                $parameters = (strpos($parameters, ',') > 0) ? explode(',', $parameters) : array($parameters);

                // Process each parameter
                foreach ($parameters as &$parameter)
                {
                    if (strpos($parameter, '~') !== false)
                    {
                        // Replace ~table and ~field unless we have an attribute with the same name
                        if ($parameter == '~table' && ! $this->{str_replace('~', '', $parameter)}) $parameter = $this->getTable();
                        if ($parameter == '~field' && ! $this->{str_replace('~', '', $parameter)}) $parameter = $field;

                        // Replace with attribute if we haven't replaced yet
                        if (strpos($parameter, '~') !== false) $parameter = $this->{str_replace('~', '', $parameter)};
                    }
                }

                // If we have a unique rule, make sure it's built correctly
                if ($type == 'unique')
                {
                    $uniqueRule = array();

                    // Build up the rule to make sure it's correct
                    if (isset($parameters[0])) $uniqueRule[] = $parameters[0];
                    else $uniqueRule[] = $this->getTable();

                    // Field name second
                    if (isset($parameters[1])) $uniqueRule[] = $parameters[1];
                    else $uniqueRule[] = $field;

                    // Make sure we ignore the current record
                    if (isset($this->primaryKey))
                    {
                        $uniqueRule[] = (isset($parameters[2])) ? $parameters[2] : $this->{$this->primaryKey};
                        $uniqueRule[] = (isset($parameters[3])) ? $parameters[3] : $this->primaryKey;
                    }
                    else
                    {
                        $uniqueRule[] = (isset($parameters[2])) ? $parameters[2] : $this->id;
                        $uniqueRule[] = (isset($parameters[3])) ? $parameters[3] : 'id';
                    }

                    // If we have exactly 6 parameters then we use the where clause field to fill the exclusion
                    if (count($parameters) > 4) for ($i = 4; $i < count($parameters); ++$i) $uniqueRule[] = $parameters[$i];

                    $parameters = $uniqueRule;
                }

                // Rebuild rule
                $rule = $type;

                // Don't try and join parameters unless we have some
                if ( ! $parameters || ! count(array_filter($parameters))) continue;

                if (is_array($parameters) && count($parameters)) $rule .= ':' . implode(',', $parameters);
                else $rule .= ':' . $parameters;
            }
        }

        return $rules;
    }

    /**
     * Test 'only' and 'except' lists for excluding relationships
     *
     * @param  string $model
     * @param  array  $conditions
     * @return array
     */
    private function filterRelationshipByConditions($model, $conditions)
    {
        // Test for false and return the opposite
        return ( !
            (isset($conditions['only']) && ! in_array($model, $conditions['only'])) ||
            (isset($conditions['except']) && in_array($model, $conditions['except']))
        );
    }

    /**
     * Find or create a record if it doesn't exist
     *
     * @param  Model  $model
     * @param  string $key
     * @return Model
     */
    private function findOrCreateRecord(Model $model, $key = null)
    {
        if ($key)
        {
            $record = ($this->usesSoftDeletes()) ? $model->withTrashed()->find($key) : $model->find($key);

            // Only return a record if we found one, otherwise we will end up sending a new record back
            if ($record) return $record;
        }

        return new $model;
    }

    /**
     * Get the observable event names
     *
     * @return array
     */
    public function getObservableEvents()
    {
        return array_merge(
            parent::getObservableEvents(),
            array('hydrating', 'hydrated', 'validating', 'validated')
        );
    }

    /**
     * Get data for a relationship
     *
     * @param  string $relationship
     * @param  array  $conditions
     * @param  array  $rules
     * @param  array  $messages
     * @return array
     */
    private function getRelationshipData($relationship, array $conditions, array $rules, array $messages)
    {
        return array(
            $this->getRelationshipDataFromArray($relationship, $conditions),
            $this->getRelationshipDataFromArray($relationship, $rules),
            $this->getRelationshipDataFromArray($relationship, $messages)
        );
    }

    /**
     * Extract data from a relationship array
     *
     * @param  string $relationship
     * @param  array $data
     * @return array
     */
    private function getRelationshipDataFromArray($relationship, array $data)
    {
        // Determine model class
        $modelClass = Str::camel($relationship);

        if (isset($data[$relationship]) || isset($data[$modelClass]))
        {
            return (isset($data[$relationship])) ? $data[$relationship] : $data[$modelClass];
        }

        return array();
    }

    /**
     * Get all relationships for a model
     *
     * @param  array $data
     * @param  array $conditions
     * @return array
     */
    private function getRelationships(array $data, array $conditions)
    {
        $relationships = array();

        // Relationships will be arrays, so reduce dataset
        $data = array_filter($data, function($value)
        {
            return is_array($value);
        });

        // Loop through input, and check whether any key is a valid relationship
        foreach ($data as $model => $value)
        {
            // Class name for models will always be camel case
            $modelClass = Str::camel($model);

            /**
             * A valid relationship must:
             * - Be a method
             * - Not appear in the 'except' list
             * - Appear in the 'only' list (if used)
             * - Be a valid instance of a relationship type
             */
            if (
                ! method_exists($this, $model) ||
                ! $this->filterRelationshipByConditions($model, $conditions) ||
                ! $this->isRelationship($modelClass)
            ) continue;

            // Capture relationship and it's type
            $relationships[$model] = $this->getRelationshipType($modelClass);
        }

        return $relationships;
    }

    /**
     * Determine if we're working with a one or many relationship
     *
     * @param  object $model
     * @return string
     */
    private function getRelationshipType($model)
    {
        // Poke method to check the type of instance
        $instance = $this->$model();

        return (
            $model instanceof BelongsTo ||
            $model instanceof HasOne ||
            $model instanceof MorphOne ||
            $model instanceof MorphTo
        ) ? 'one' : 'many';
    }

    /**
     * Get rules to use for validation
     *
     * @param  array $rules
     * @return array
     */
    private function getRuleset(array $rules = array())
    {
        // If we have rules pass use them, otherwise use rules from model
        $rules = ( ! count($rules)) ? $this->rules : $rules;

        // Remove any empty rules
        $rules = $this->removeInvalidRules($rules);

        // Expand rules we will use to validate the model
        return $this->expandRuleset($rules);
    }


    /**
     * Hash any hashable attributes
     *
     * @return void
     */
    private function hashAttributes()
    {
        // Hash attributes
        if (count($this->hashable))
        {
            $hasher = new BcryptHasher;
            $filtered = array_filter($this->attributes);

            foreach ($filtered as $key => $value)
            {
                if (in_array($key, $this->hashable) && $value != $this->getOriginal($key))
                {
                    $this->attributes[$key] = $hasher->make($value);
                }
            }
        }
    }

    /**
     * Register a hydrated model event with the dispatcher
     *
     * @param  Closure|string $callback
     * @return void
     */
    public static function hydrated($callback)
    {
        static::registerModelEvent('hydrated', $callback);
    }

    /**
     * Hydrate a model from input or an array
     *
     * @param  array $data
     * @return void
     */
    private function hydrateModel(array $data)
    {
        // Make sure we're using fillable, and we haven't previously filled the model which may overwrite stuff
        if ($this->fillFromInput && count($this->fillable) && ! $this->_hydratedByVocal)
        {
            // Fire hydrating event
            if ($this->fireModelEvent('hydrating') === false) return false;

            // Fill from data and record we've filled it once
            $this->fill($data);
            $this->_hydratedByVocal = true;

            $this->fireModelEvent('hydrated', false);
        }
    }

    /**
     * Register a hydrating model event with the dispatcher
     *
     * @param  Closure|string $callback
     * @return void
     */
    public static function hydrating($callback)
    {
        static::registerModelEvent('hydrating', $callback);
    }

    /**
     * Determine if a string is a relationship
     *
     * @param  string $model
     * @return bool
     */
    private function isRelationship($model)
    {
        // Poke method to check the type of instance
        $instance = $this->$model();

        // Check whether the instance is a relationship
        return (
            $instance instanceof BelongsTo ||
            $instance instanceof BelongsToMany ||
            $instance instanceof HasMany ||
            $instance instanceof HasManyThrough ||
            $instance instanceof HasOne ||
            $instance instanceof MorphMany ||
            $instance instanceof MorphOne ||
            $instance instanceof MorphTo
        );
    }

    /**
     * Remove any fields which can't be submitted to the database
     *
     * @return void
     */
    private function removeInvalidAttributes()
    {
        foreach ($this->getAttributes() as $attribute => $data)
        {
            if ( ! is_null($data) && ! is_scalar($data)) unset($this->$attribute);
        }
    }

    /**
     * Remove any invalid rules before attempting to validate
     *
     * @param  array $rules
     * @return array
     */
    private function removeInvalidRules(array $rules)
    {
        foreach ($rules as $field => $rule) if (empty($rule)) unset($rules[$field]);

        return $rules;
    }

    /**
     * Save a single record
     *
     * @param  array $data
     * @param  array $rules
     * @param  array $messages
     * @return bool
     */
    public function save(array $data = array(), array $rules = array(), array $messages = array())
    {
        // Fill model attributes
        $this->hydrateModel($data);

        // Hash any hashable attributes
        $this->hashAttributes();

        // Save the record
        return parent::save();
    }

    /**
     * Save a one to many relationship record
     *
     * @return void
     */
    private function saveManyRelationship()
    {

    }

    /**
     * Save a one to one relationship record
     *
     * @return void
     */
    private function saveOneRelationship()
    {

    }

    /**
     * Recursively save a record
     *
     * @param  array $data
     * @param  array $conditions
     * @param  array $rules
     * @param  array $messages
     * @return bool
     */
    public function saveRecursive(array $data = array(), array $conditions = array(), array $rules = array(), array $messages = array())
    {
        // If we don't have data, use input
        if ( ! count($data)) $data = Input::all();

        // Save this record
        $result = $this->save($data);

        if ( ! $result) return false;

        // See if we have any relationships to save
        $relationships = $this->getRelationships($data, $conditions);

        // If we don't have any relationships or save failed, fail
        if ( ! count($relationships) || ! $result) return $result;

        $result = $this->saveRelationships($relationships, $data, $conditions);

        return $result;
    }

    /**
     * Recursively save relationships
     *
     * @param  array $relationships
     * @param  array $data
     * @param  array $conditions
     * @param  array $rules
     * @param  array $messages
     * @return bool
     */
    private function saveRelationships(array $relationships, array $data = array(), array $conditions = array(), array $rules = array(), array $messages = array())
    {
        foreach ($relationships as $relationship => $type)
        {
            // Get class/model we're working on
            $modelClass = Str::camel($relationship);
            $model = $this->$modelClass()->getModel();

            // Determine which key we will use to find an existing record
            $key = (isset($model->primaryKey)) ? $model->primaryKey : 'id';


        }
    }

    /**
     * Determine if a model is using soft deletes
     *
     * @return bool
     */
    private function usesSoftDeletes()
    {
        return ((isset($this->forceDeleting) && ! $this->forceDeleting) || isset($this->softDeletes) && ! $this->softDeletes);
    }

    /**
     * Validate a single record
     *
     * @param  array $data
     * @param  array $rules
     * @param  array $messages
     * @return bool
     */
    public function validate(array $data = array(), array $rules = array(), array $messages = array())
    {
        // Fill model attributes
        $this->hydrateModel($data);

        // Fire validating event
        if ($this->fireModelEvent('validating') === false) return false;

        // Remove any fields from the model which can't be submitted, such as objects and arrays
        // - This will prevent errors with bound objects being saved twice
        $this->removeInvalidAttributes();

        // Get rules we will use to validate this model
        $rules = $this->getRuleset($rules);

        // If we have no rules then validation will pass
        if ( ! count($rules))
        {
            $this->fireModelEvent('validated', false);
            return true;
        }

        // Determine what we're validating
        $model = $this->getAttributes();

        // Validate
        $validator = Validator::make($model, $rules, $messages);
        $result = $validator->passes();

        // If model is valid, remove old errors
        if ($result)
        {
            $this->errors = new MessageBag;

            // Tag this model as valid
            $this->_validatedByVocal = true;
        }
        else
        {
            // Add errors messages
            $this->errors = $validator->messages();

            // Stash the input to the current session
            if (Input::hasSession()) Input::flash();
        }

        $this->fireModelEvent('validated', false);

        return $result;
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
            // Capture any errors from relationships
            $errors = new MessageBag;

            // Instantiate class/model we're working on
            $modelClass = Str::camel($model);
            $instance = $this->$modelClass()->getModel();

            // Determine which key we will use to find an existing record
            $key = (isset($instance->primaryKey)) ? $instance->primaryKey : 'id';

            // Extract rules and messages we will use specifically for this relationship
            list($conditions, $messages, $rules) = $this->getRelationshipData($model, $conditions, $rules, $messages);

            if ($type == 'one')
            {
                // Find or create record
                $record = $this->findOrCreateRecord($instance, isset($data[$model][$key]) ? $data[$model][$key] : null);

                // Validate and capture errors
                $result = $record->validate($data[$model], $rules, $messages);
                if ( ! $result) $errors->merge($record->errors);
            }
            else
            {
                foreach ($data[$model] as $index => $relationship)
                {
                    // Find or create record
                    $record = $this->findOrCreateRecord($instance, isset($relationship[$key]) ? $relationship[$key] : null);

                    // Validate and capture errors
                    $result = $record->validate($relationship, $rules, $messages);
                    if ( ! $result) $errors->add($index, $record->errors);

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
            if ($errors->count()) $this->errors->add($model, $errors);
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
