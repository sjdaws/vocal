<?php

namespace Sjdaws\Vocal;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Hashing\BcryptHasher;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * @property string $primaryKey
 */
class SuperModel extends Model
{
    /**
     * The message bag instance containing validation error messages
     *
     * @var Illuminate\Support\MessageBag
     */
    protected $errors;

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
        $this->addEventCallbacks(array('creat', 'delet', 'hydrat', 'sav', 'updat', 'validat'));
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
     * Determine if a model is using soft deletes
     *
     * @return bool
     */
    private function usesSoftDeletes()
    {
        return ((isset($this->forceDeleting) && ! $this->forceDeleting) || isset($this->softDelete) && ! $this->softDelete);
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
