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
     * @param  Model $model
     * @param  array $rules
     */
    public function __construct($model, array $rules = array())
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

            // Process rule
            foreach ($set as &$rule)
            {
                list($type, $parameters) = $this->getRuleTypeAndParameters($rule);
                $parameters = $this->parseParameters($parameters, $field);
                $parameters = $this->parseUniqueRule($type, $parameters, $field);

                // Don't try and join parameters unless we have some
                if ( ! $parameters || ! count(array_filter($parameters))) continue;

                // Rebuild rule
                $this->rules[] = $this->rebuildRule($type, $parameters);
            }
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
     * Parse rule parameters
     *
     * @param  string|array $parameters
     * @param  string       $field
     * @return array
     */
    private function parseParameters($parameters, $field)
    {
        // Make sure parameters is an array
        $parameters = (strpos($parameters, ',') > 0) ? explode(',', $parameters) : array($parameters);

        // Process each parameter
        foreach ($parameters as &$parameter)
        {
            if (strpos($parameter, '~') !== false)
            {
                // Replace ~table and ~field unless we have an attribute with the same name
                if ($parameter == '~table' && ! $this->model->{str_replace('~', '', $parameter)}) $parameter = $this->model->getTable();
                if ($parameter == '~field' && ! $this->model->{str_replace('~', '', $parameter)}) $parameter = $field;

                // Replace with attribute if we haven't replaced yet
                if (strpos($parameter, '~') !== false) $parameter = $this->model->{str_replace('~', '', $parameter)};
            }
        }

        return $parameters;
    }

    /**
     * Parse a unique rule
     *
     * @param  string $type
     * @param  array  $parameters
     * @param  string $field
     * @return array
     */
    private function parseUniqueRule($type, array $parameters, $field)
    {
        // If we have a unique rule, make sure it's built correctly
        if ($type != 'unique') return $parameters;

        // Construct unique rule correctly
        $rule = array(
            $this->useParameterIfSet($parameters, 0, $this->model->getTable()),
            $this->useParameterIfSet($parameters, 1, $field),
            $this->useParameterIfSet($parameters, 2, (isset($this->model->primaryKey)) ? $this->model->{$this->model->primaryKey} : $this->model->id),
            $this->useParameterIfSet($parameters, 3, (isset($this->model->primaryKey)) ? $this->model->primaryKey : 'id')
        );

        // If we have exactly 6 parameters then we use the where clause field to fill the exclusion
        if (count($parameters) > 4) for ($i = 4; $i < count($parameters); ++$i) $rule[] = $parameters[$i];

        return $rule;
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

        return $rule;
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
