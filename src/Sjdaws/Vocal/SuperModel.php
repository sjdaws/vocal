<?php

namespace Sjdaws\Vocal;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Hashing\BcryptHasher;
use Illuminate\Support\Str;
use ReflectionClass;

class SuperModel extends Model
{
    /**
     * Whether to fill the model from input or not
     *
     * @var bool
     */
    protected $fillFromInput = true;

    /**
     * The fields which should be hashed automatically
     *
     * @var array
     */
    protected $hashable = array();

    /**
     * The events which will we observe
     *
     * @var array
     */
    private $observableEvents = array();

    /**
     * Determine whether the model has been hydrated
     *
     * @var bool
     */
    private $_hydratedByVocal = false;

    /**
     * Create a new model instance
     *
     * @param  array $attributes
     */
    public function __construct(array $attributes = array())
    {
        parent::__construct($attributes);

        // Add event callbacks
        $this->addEventCallbacks(array('creat', 'delet', 'hydrat', 'sav', 'updat'));
    }

    /**
     * Add a callback for an event if it exists
     *
     * @param  string $method
     * @param  string $event
     * @return null
     */
    private function addObservableEvent($method, $event)
    {
        if (method_exists(get_called_class(), $method))
        {
            self::{$event}(function($model) use ($method)
            {
                return $model->{$method}($model);
            });

            // Keep track of events
            $this->observableEvents[] = $event;
        }
    }

    /**
     * Attach callback before and after a set of events
     *
     * @param  array $events
     * @return null
     */
    protected function addEventCallbacks(array $events)
    {
        $hooks = array('before' => 'ing', 'after' => 'ed');

        foreach ($events as $event)
        {
            foreach ($hooks as $hook => $suffix)
            {
                $method = $hook . ucfirst($event) . 'e';
                $callback = $event . $suffix;

                $this->addObservableEvent($method, $callback);
            }
        }
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
     * Test 'only' and 'except' lists for excluding relationships
     *
     * @param  string $model
     * @param  array  $conditions
     * @return bool
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
    protected function findOrCreateRecord(Model $model, $key = null)
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
     * Get the observable event names including any hooks
     *
     * @return array
     */
    public function getObservableEvents()
    {
        return array_merge(parent::getObservableEvents(), $this->observableEvents);
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
    protected function getRelationshipData($relationship, array $conditions, array $rules, array $messages)
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
    protected function getRelationships(array $data, array $conditions)
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
     * @param  string $model
     * @return string
     */
    private function getRelationshipType($model)
    {
        // Poke method to check the type of instance
        $class = get_class($this->{$model}());
        $reflection = new ReflectionClass($class);

        return (in_array($reflection->getShortName(), array('BelongsTo', 'HasOne', 'MorphOne', 'MorphTo'))) ? 'one' : 'many';
    }

    /**
     * Hash any hashable attributes
     *
     * @return null
     */
    private function hashAttributes()
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
     * @return false|null
     */
    protected function hydrateModel(array $data)
    {
        // Make sure we're using fillable, and we haven't previously filled the model which may overwrite stuff
        if ( ! $this->_hydratedByVocal && $this->fillFromInput)
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
        // All relations extend Illuminate\Database\Eloquent\Relations\Relation
        return is_subclass_of($this->{$model}(), 'Relation');
    }

    /**
     * Remove any fields which can't be submitted to the database
     *
     * @return void
     */
    protected function removeInvalidAttributes()
    {
        foreach ($this->getAttributes() as $attribute => $data)
        {
            if ( ! is_null($data) && ! is_scalar($data)) unset($this->{$attribute});
        }
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

        // Validate record before save unless we're asked not to
        $valid = ( ! $this->validateBeforeSave || $this->_validatedByVocal) ? true : $this->validate($data, $rules, $messages);

        // If record is invalid, save is unsuccessful
        if ( ! $valid) return false;

        // Hash any hashable attributes
        $this->hashAttributes();

        // Save the record
        return parent::save();
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
}
