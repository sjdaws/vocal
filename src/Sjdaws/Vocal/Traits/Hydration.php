<?php

namespace Sjdaws\Vocal\Traits;

use \Illuminate\Support\Facades\Input;

trait Hydration
{
    /**
     * We can automatically fill the model from input if no data is passed
     *
     * @param bool
     */
    protected $allowHydration = true;

    /**
     * Determine whether the model has been hydrated
     *
     * @var bool
     */
    private $hydrated = false;

    /**
     * Fill the model with an array of attributes
     *
     * @param  array $attributes
     * @return $this
     *
     * @throws \Illuminate\Database\Eloquent\MassAssignmentException
     */
    abstract public function fill(array $attributes);

    /**
     * Fire an event for a model
     *
     * @param  string $event
     * @param  bool   $halt
     * @return mixed
     */
    abstract protected function fireModelEvent($event, $halt = true);

    /**
     * Get all of the current attributes on the model
     *
     * @return array
     */
    abstract public function getAttributes();

    /**
     * Determine what data we should use to hydrate a model
     *
     * @param  array $data
     * @return array
     */
    private function getHydrationData(array $data = [])
    {
        return (count($data) || ! $this->allowHydration) ? $data : Input::all();
    }

    /**
     * Register a hydrated model event with the dispatcher
     *
     * @param  \Closure|string $callback
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
     * @return bool
     */
    public function hydrateModel(array $data = [])
    {
        // Fire hydrating event
        if ($this->fireModelEvent('hydrating') === false) return false;

        // Get the data we're using for this model
        $data = $this->getHydrationData($data);

        // Fill from data and record we've filled it
        $this->fill($data);
        $this->hydrated = true;

        // Remove any fields from the model which can't be submitted, such as objects and arrays
        // - This will prevent errors with bound objects being saved twice
        $this->removeInvalidAttributes();

        $this->fireModelEvent('hydrated', false);

        return true;
    }

    /**
     * Register a hydrating model event with the dispatcher
     *
     * @param  \Closure|string $callback
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
     * Set whether hydration is allowed or not
     *
     * @param  bool $value
     * @return null
     */
    public function setAllowHydration($value)
    {
        $this->allowHydration = $value;
    }
}
