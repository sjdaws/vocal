<?php

namespace Sjdaws\Vocal\Traits;

trait Events
{
    /**
     * The events which will we observe
     *
     * @var array
     */
    private static $observableEvents = array();

    /**
     * Add a callback for an event if it exists
     *
     * @param  string $method
     * @param  string $event
     * @return null
     */
    private static function addObservableEvent($method, $event)
    {
        if (method_exists(get_called_class(), $method))
        {
            self::registerModelEvent($event, function($model) use ($method)
            {
                return $model->{$method}($model);
            });

            // Keep track of events
            self::$observableEvents[] = $event;
        }
    }

    /**
     * Attach callback before and after a set of events
     *
     * @param  array $events
     * @return null
     */
    protected static function addEventCallbacks(array $events)
    {
        $hooks = array('before' => 'ing', 'after' => 'ed');

        foreach ($events as $event)
        {
            foreach ($hooks as $hook => $suffix)
            {
                $method = $hook . ucfirst($event) . 'e';
                $callback = $event . $suffix;

                self::addObservableEvent($method, $callback);
            }
        }
    }

    /**
     * Get the observable event names including any hooks
     *
     * @return array
     */
    public function getObservableEvents()
    {
        return array_merge(parent::getObservableEvents(), self::$observableEvents);
    }
}
