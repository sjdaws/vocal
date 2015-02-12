<?php

namespace Sjdaws\Vocal;

class Ruleset
{
    private $model;

    /**
     * The active rule set
     *
     * @var array
     */
    private $rules = array();

    /**
     * Create a new rule set
     *
     * @param SuperModel $model
     * @param array      $rules
     */
    public function __construct(SuperModel $model, array $rules = array())
    {
        $this->model = $model;
        $this->add(array_filter($rules));
    }

    /**
     * Add new rules to the array
     *
     * @return void
     */
    private function add(array $rules)
    {
        foreach ($rules as $field => $rule)
        {
            // Change pipe delimited rules to arrays
            $set = $this->pipeToArray($rule);

            // Process rules
            $this->rules = array_merge($this->rules, $this->processRuleset($field, $set));
        }
    }

    /**
     * Return the current rule set
     *
     * @return array
     */
    public function get()
    {
        return $this->rules;
    }

    /**
     * Get rule type and parameters
     *
     * @param  string $rule
     * @return array
     */
    private function getRuleTypeAndParameters($rule)
    {
        if (strpos($rule, ':') !== false) return explode(':', $rule, 2);

        return array($rule, null);
    }

    /**
     * Merge an array from an index point
     *
     * @param  array   $to
     * @param  array   $from
     * @param  integer $index
     * @return array
     */
    private function mergeArrayFromIndex(array $to, array $from, $index = 0)
    {
        if (count($from) > $index) for ($i = $index; $i < count($from); ++$i) $to[] = $from[$i];

        return $to;
    }

    /**
     * Parse rule parameters
     *
     * @param  string       $field
     * @param  string|array $parameters
     * @return array
     */
    private function parseParameters($field, $parameters)
    {
        // Make sure parameters is an array
        $parameters = (strpos($parameters, ',') > 0) ? explode(',', $parameters) : array($parameters);

        // Process each parameter
        foreach ($parameters as $key => $parameter)
        {
            // Replace ~table and ~field if they exist
            $parameter = str_ireplace(array('~table', '~field'), array($this->model->getTable(), $field), $parameter);

            // Replace with attribute
            if (strpos($parameter, '~') !== false) $parameter = $this->model->{str_replace('~', '', $parameter)};
        }

        return $parameters;
    }

    /**
     * Parse a unique rule
     *
     * @param  string $field
     * @param  array  $parameters
     * @param  string $type
     * @return array
     */
    private function parseUniqueRule($field, array $parameters, $type)
    {
        // If we have a unique rule, make sure it's built correctly
        if ($type != 'unique') return $parameters;

        // Determine primary key
        $primaryKey = (isset($this->model->primaryKey)) ? $this->model->primaryKey : 'id';

        // Construct unique rule correctly
        $rule = array(
            $this->useParameterIfSet($parameters, 0, $this->model->getTable()),
            $this->useParameterIfSet($parameters, 1, $field),
            $this->useParameterIfSet($parameters, 2, $this->model->{$primaryKey}),
            $this->useParameterIfSet($parameters, 3, $primaryKey)
        );

        // Merge in any other parameters we have
        return $this->mergeArrayFromIndex($rule, $parameters, 4);
    }

    /**
     * Convert pipe rules to arrays
     *
     * @param  string|array $rule
     * @return array
     */
    private function pipeToArray($rule)
    {
        return (is_array($rule)) ? $rule : explode('|', $rule);
    }

    /**
     * Process rule set into rules
     *
     * @param  string $field
     * @param  array  $set
     * @return array
     */
    private function processRuleset($field, array $set)
    {
        $rules = array();

        foreach ($set as &$rule)
        {
            list($type, $parameters) = $this->getRuleTypeAndParameters($rule);
            $parameters = $this->parseParameters($field, $parameters);
            $parameters = $this->parseUniqueRule($field, $parameters, $type);

            // Rebuild rule
            $rules[] = $this->rebuildRule($type, $parameters);
        }

        return $rules;
    }

    /**
     * Rebuild a whole rule
     *
     * @param  string       $type
     * @param  string|array $parameters
     * @return string
     */
    private function rebuildRule($type, $parameters)
    {
        // Rebuild rule
        $rule = $type;

        if (is_array($parameters) && count($parameters)) $rule .= ':' . implode(',', $parameters);
        else $rule .= ':' . $parameters;

        // Remove any trailing colons we have from rules without parameters
        return trim($rule, ':');
    }

    /**
     * Use a value if set, otherwise use an optional default
     *
     * @param  array   $parameters
     * @param  integer $index
     * @param  string  $default
     * @return string
     */
    private function useParameterIfSet($parameters, $index, $default = null)
    {
        return (isset($parameters[$index])) ? $parameters[$index] : $default;
    }
}
