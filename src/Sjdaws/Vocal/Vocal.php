<?php

namespace Sjdaws\Vocal;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Hashing\BcryptHasher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * @method   withTrashed()
 * @property bool $forceDeleting
 * @property bool $softDelete
 */
class Vocal extends Model
{
    /**
     * We can automatically fill the model from input if no data is passed
     *
     * @param bool
     */
    protected $allowHydrationFromInput = true;

    /**
     * The conditions used to determine relationships
     *
     * @var array
     */
    private $conditions = [];

    /**
     * The data we will use for this record and it's relationships
     *
     * @var array
     */
    private $dataset = [];

    /**
     * The message bag instance containing validation error messages
     *
     * @var \Illuminate\Support\MessageBag
     */
    private $errors;

    /**
     * The fields which should be hashed automatically
     *
     * @var array
     */
    protected $hashable = [];

    /**
     * Determine if we've hydrated a model already or not
     *
     * @var bool
     */
    private $hydrated = false;

    /**
     * The folder which language files are stored in
     *
     * @var string
     */
    protected $languageFolder = 'validation';

    /**
     * The key within the language file where validation messages are stored
     *
     * @var string
     */
    protected $languageKey = null;

    /**
     * The messages we will use if a validation error occurs
     *
     * @var array
     */
    protected $messages = [];

    /**
     * The messages we will use for this record and it's relationships
     *
     * @var array
     */
    private $messageset = [];

    /**
     * The events which will we observe
     *
     * @var array
     */
    private static $observableEvents = [];

    /**
     * The result for the last valdiation
     *
     * @var bool
     */
    private $result;

    /**
     * The rules we will use to validate this record
     *
     * @var array
     */
    public $rules = [];

    /**
     * The rules we will use for this record and it's relationships
     *
     * @var array
     */
    private $ruleset = [];

    /**
     * Whether to validate before save or not
     *
     * @var bool
     */
    public $validateBeforeSave = true;

    /**
     * Create a new model instance
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        self::boot();
    }

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
    private static function addEventCallbacks(array $events = [])
    {
        $hooks = ['before' => 'ing', 'after' => 'ed'];

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
     * Monitor events and trigger callbacks if they exist
     *
     * @see    \Illuminate\Database\Eloquent\Model::boot()
     * @return null
     */
    public static function boot()
    {
        parent::boot();

        // Add event callbacks
        self::addEventCallbacks(['creat', 'delet', 'hydrat', 'restor', 'sav', 'updat', 'validat']);
    }

    /**
     * Create a record in one go
     *
     * @param  array $data
     * @param  array $rules
     * @param  array $messages
     * @return Vocal
     */
    public static function create(array $data = [], array $rules = [], array $messages = [])
    {
        $model = new static;
        $model->saveRecursive($data, $rules, $messages);

        return $model;
    }

    /**
     * Recursively extract error messages
     *
     * @param  array $error
     * @return array
     */
    private function extractErrors($error)
    {
        $messages = [];

        foreach ($error as $key => $errors)
        {
            // If error is a MessageBag, extract errors
            if ($errors instanceof MessageBag) return $this->extractErrors($errors->toArray());

            // If we have an array of errors preserve key
            if (is_array($errors))
            {
                $messages[$key] = $this->extractErrors($errors);
                continue;
            }

            // We must be down to a single error, just return it
            return $errors;
        }

        return $messages;
    }

    /**
     * Fill a model from input or an array
     *
     * @return bool
     */
    private function fillModel()
    {
        // If we've already filled this model don't fill it again
        if ($this->hydrated) return true;

        // Fire hydrating event
        if ($this->fireModelEvent('hydrating') === false) return false;

        // Fill from data and record we've filled it
        $this->fill($this->dataset);
        $this->hydrated = true;

        // Remove any fields from the model which can't be submitted, such as objects and arrays
        // - This will prevent errors with bound objects being saved twice
        $this->removeInvalidAttributes();

        $this->fireModelEvent('hydrated', false);

        return true;
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
     * @param  integer|string $key
     * @return Vocal
     */
    private function findOrCreateRecord($key = null)
    {
        if ($key)
        {
            $record = ($this->usesSoftDeletes()) ? $this->withTrashed()->find($key) : $this->find($key);

            // Only return a record if we found one, otherwise we will end up sending a new record back below
            if ($record) return $record;
        }

        return new $this;
    }

    /**
     * Create a record without attempting to validate it
     *
     * @param  array $data
     * @return Vocal
     */
    public static function forceCreate(array $data = [])
    {
        $model = new static;
        $model->forceSave($data, true);

        return $model;
    }

    /**
     * Save a record regardless of whether it's valid or not
     *
     * @param  array $data
     * @return bool
     */
    public function forceSave(array $data = [], $recursive = false)
    {
        return ($recursive) ? $this->recurse('save', $data) : $this->saveRecord($data);
    }

    /**
     * Save a record then delete it
     *
     * @param  array $data
     * @return bool
     */
    public function forceSaveAndDelete(array $data = [])
    {
        $this->saveRecord($data);
        return $this->delete();
    }

    /**
     * Save a record recursively regardless of whether it's valid or not
     *
     * @param  array $data
     * @return bool
     */
    public function forceSaveRecursive(array $data = [])
    {
        return $this->forceSave($data, true);
    }

    /**
     * Return the errors as an array
     *
     * @param  string $filter
     * @return array
     */
    public function getErrors($filter = null)
    {
        // Create an array to hold errors
        $messages = [];

        // If we have no errors, abort
        if ( ! $this->errors->count()) return $messages;

        foreach ($this->errors->toArray() as $key => $error)
        {
            $messages[$key] = $this->extractErrors($error);
        }

        // Return a specific set of messages if asked
        return array_get($messages, $filter);
    }

    /**
     * Get error messagebag from the last action
     *
     * @return \Illuminate\Support\MessageBag
     */
    public function getErrorBag()
    {
        return $this->errors;
    }

    /**
     * Get message from a range of different file/key combinations
     *
     * @param  string $filename
     * @param  string $key
     * @return string
     */
    public function getMessage($filename, $key)
    {
        $messageset = $this->getParameters('messageset');

        // If we have a matching key in messageset it wins
        if (array_get($messageset, $key))
        {
            return array_get($messageset, $key);
        }

        $fileKey = ($this->languageKey) ? $this->languageKey . '.' . $key : $key;

        // We have a couple of options where messages could be retrieved by default,
        // try: Model.php and model.php, as well as Model_Model.php and Model/Model.php
        $messages = [
            $filename . $fileKey,
            Str::lower($filename) . $fileKey,
            str_replace('_', '/', $filename) . $fileKey,
            Str::lower(str_replace('_', '/', $filename)) . $fileKey
        ];

        // Cycle through messages trying to find one that is valid
        foreach ($messages as $message)
        {
            if (Lang::has($message))
            {
                return Lang::get($message);
            }
        }

        return "";
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

    /**
     * Get relationship data, rules or messages
     *
     * @param  string         $type
     * @param  integer|string $index
     * @return array
     */
    private function getParameters($type, $index = null)
    {
        // If the type is invalid, return an empty array
        if ( ! in_array($type, ['dataset', 'messageset', 'ruleset'])) return [];

        return array_get($this->{$type}, $index, []);
    }

    /**
     * Get relationship data
     *
     * @param  integer|string $index
     * @return array
     */
    private function getRelationshipData($index = null)
    {
        return array_get($this->dataset, $index, []);
    }

    /**
     * Get relationship messages
     *
     * @param  integer|string $index
     * @return array
     */
    private function getRelationshipMessages($index = null)
    {
        return array_get($this->messageset, $index, []);
    }

    /**
     * Get relationship rules
     *
     * @param  integer|string $index
     * @return array
     */
    private function getRelationshipRules($index = null)
    {
        return array_get($this->ruleset, $index, []);
    }

    /**
     * Get all relationships for a model
     *
     * @return array
     */
    public function getRelationships()
    {
        $relationships = [];

        // Loop through input, and check whether any key is a valid relationship
        foreach ($this->dataset as $model => $value)
        {
            // Skip anything which isn't a valid relationship
            if ( ! $this->isRelationship($model, $this->conditions)) continue;

            // Capture relationship and it's type
            $relationships[$model] = $this->getRelationshipType(Str::camel($model));
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

        return (in_array($reflection->getShortName(), ['BelongsTo', 'HasOne', 'MorphOne', 'MorphTo'])) ? 'one' : 'many';
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

        return [$rule, null];
    }

    /**
     * Hash any hashable attributes
     *
     * @return null
     */
    private function hashHashable()
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
     * @param  \Closure|string $callback
     * @return void
     */
    public static function hydrated($callback)
    {
        static::registerModelEvent('hydrated', $callback);
    }

    /**
     * Fill a model from an array or input
     *
     * @param  array $data
     * @return bool
     */
    public function hydrateModel(array $data = [])
    {
        $this->setDataset($data);
        return $this->fillModel();
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
     * Determine if a string is a relationship
     *
     * @param  string $model
     * @param  array  $conditions
     * @return bool
     */
    private function isRelationship($model, $conditions)
    {
        /**
         * A valid relationship must:
         * - Be a method
         * - Not appear in the 'except' list
         * - Appear in the 'only' list (if used)
         * - Extend Illuminate\Database\Eloquent\Relations\Relation
         */
        return (
            method_exists($this, $model) &&
            $this->filterRelationshipByConditions($model, $conditions) &&
            is_subclass_of($this->{Str::camel($model)}(), 'Illuminate\Database\Eloquent\Relations\Relation')
        );
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
     * Merge two message bags together
     *
     * @param  \Illuminate\Support\MessageBag $bag
     * @param  \Illuminate\Support\MessageBag $errors
     * @param  integer                        $index
     * @return \Illuminate\Support\MessageBag
     */
    private function mergeErrors(MessageBag $bag, MessageBag $errors, $index = null)
    {
        if ( ! $errors->count()) return $bag;

        // Add or merge errors into bag
        if ($index) $bag->add($index, $errors);
        else $bag->merge($errors);

        return $bag;
    }

    /**
     * Parse rule parameters
     *
     * @param  string       $field
     * @param  string|array $parameters
     * @return array
     */
    private function parseRuleParameters($field, $parameters)
    {
        // Make sure parameters is an array
        $parameters = (strpos($parameters, ',') > 0) ? explode(',', $parameters) : [$parameters];

        // Process each parameter
        foreach ($parameters as $key => $parameter)
        {
            // Replace ~table and ~field if they exist
            $parameter = str_ireplace(['~table', '~field'], [$this->getTable(), $field], $parameter);

            // Replace with attribute
            if (strpos($parameter, '~') !== false) $parameter = $this->{str_replace('~', '', $parameter)};
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

        // The first item may be null, remove it if that's the case
        $parameters = array_filter($parameters);

        // Determine primary key
        $primaryKey = (isset($this->primaryKey)) ? $this->primaryKey : 'id';

        // Construct unique rule correctly
        $rule = [
            array_get($parameters, 0, $this->getTable()),
            array_get($parameters, 1, $field),
            array_get($parameters, 2, $this->{$primaryKey}),
            array_get($parameters, 3, $primaryKey)
        ];

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
     * Prepare a record for saving or validating
     *
     * @param  array $data
     * @param  array $rules
     * @param  array $messages
     * @return null
     */
    private function prepareRecord(array $data, array $rules, array $messages)
    {
        // Reset error bag
        $this->errors = new MessageBag;

        // Capture data
        $this->setDataset($data);
        $this->setRuleset($rules);
        $this->setMessageset($messages);

        // Fill model
        $this->fillModel();
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
        $rules = [];

        foreach ($set as $rule)
        {
            // If this rule is an array skip it because it's going to be a relationship
            if (is_array($rule)) continue;

            list($type, $parameters) = $this->getRuleTypeAndParameters($rule);
            $parameters = $this->parseRuleParameters($field, $parameters);
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
     * Perform a recursive action
     *
     * @param  string $method
     * @param  array  $data
     * @param  array  $rules
     * @param  array  $messages
     * @return bool
     */
    private function recurse($method, array $data = [], array $rules = [], array $messages = [])
    {
        // Make sure method is valid
        if ( ! in_array($method, ['save', 'validate'])) return false;

        // Disable validation
        $validateBeforeSave = $this->validateBeforeSave;
        $this->validateBeforeSave = false;

        // Perform action
        $result = $this->{$method}($data, $rules, $messages);

        // Check for relationships
        $relationships = $this->getRelationships();

        // Recurse into each relationship if we can
        foreach ($relationships as $name => $type)
        {
            $subresult = $this->recurseRelationship($method, $name, $type);

            // If a relationship fails, we fail
            if ( ! $subresult) $result = false;
        }

        // Reset validation
        $this->validateBeforeSave = $validateBeforeSave;

        return $result;
    }

        /**
     * Validate a one to many relationship
     *
     * @param  string $method
     * @param  string $name
     * @param  string $type
     * @return bool
     */
    private function recurseRelationship($method, $name, $type = 'one')
    {
        $dataset = $this->getParameters('dataset', $name);

        // Assume by default this will pass
        $result = true;

        if ($type == 'one')
        {
            $result = $this->recurseRecord($method, $name, $dataset);
        }
        else
        {
            foreach ($dataset as $index => $data)
            {
                $subresult = $this->recurseRecord($method, $name, $data, $index);

                // Relationship failures are failures
                if ( ! $subresult) $result = false;
            }
        }

        // A positive result and no errors mean action was successful
        return ($result && $this->errors->count() === 0);
    }

    /**
     * Save or validate a single recursive record
     *
     * @param  string $method
     * @param  string $name
     * @param  array  $data
     * @param  string $index
     * @return bool
     */
    private function recurseRecord($method, $name, array $data, $index = null)
    {
        // Get model and determine primary key column
        $class = Str::camel($name);
        $model = $this->{$class}()->getModel();
        $primaryKey = (isset($model->primaryKey)) ? $model->primaryKey : 'id';

        $key = array_get($data, $primaryKey);

        // Find record and perform action on it
        $record = $model->findOrCreateRecord($key);
        $result = $record->recurse($method, $data, $this->getParameters('ruleset', $name), $this->getParameters('messageset', $name));

        // Capture errors
        $this->errors = $this->mergeErrors($this->errors, $record->getErrorBag(), $index);

        // Attach relationhip to record
        if ($result) $this->setRelationship($record, $name, $class, $index);

        // Record failure
        return $result;
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
            if ( ! is_null($data) && ! is_scalar($data)) unset($this->{$attribute});
        }
    }

    /**
     * Save a record
     *
     * @param  array $data
     * @param  array $rules
     * @param  array $messages
     * @return bool
     */
    public function save(array $data = [], array $rules = [], array $messages = [])
    {
        // Validate, and if it fails abort save
        if ($this->validateBeforeSave && ! $this->validate($data, $rules, $messages)) return false;

        return $this->saveRecord($data, $rules, $messages);
    }

    /**
     * Save a record, then delete it if the save was successful
     *
     * @param  array $data
     * @param  array $rules
     * @param  array $messages
     * @return bool
     */
    public function saveAndDelete(array $data = [], array $rules = [], array $messages = [])
    {
        $result = $this->save($data, $rules, $messages);

        if ( ! $result) return false;

        return $this->delete();
    }

    /**
     * Save a single record
     *
     * @param  array $data
     * @param  array $rules
     * @param  array $messages
     * @return bool
     */
    private function saveRecord(array $data = [], array $rules = [], array $messages = [])
    {
        $this->prepareRecord($data, $rules, $messages);

        // Hash any hashable attributes
        $this->hashHashable();

        // Hand off to Model
        return parent::save();
    }

    /**
     * Recursively save a record
     *
     * @param  array $data
     * @param  array $rules
     * @param  array $messages
     * @return bool
     */
    public function saveRecursive(array $data = [], array $rules = [], array $messages = [])
    {
        // If we're validating, do the whole record first to head off any problems
        if ($this->validateBeforeSave && ! $this->validateRecursive($data, $rules, $messages)) return false;

        return $this->recurse('save', $data, $rules, $messages);
    }

    /**
     * Select which set of messages we should use
     *
     * @param  array $messages
     * @return array
     */
    private function selectMessages(array $messages = [])
    {
        return (count($messages)) ? $messages : $this->messages;
    }

    /**
     * Select which set of rules we should use
     *
     * @param  array $rules
     * @return array
     */
    private function selectRules(array $rules = [])
    {
        return (count($rules)) ? $rules : $this->rules;
    }

    /**
     * Set relationship data
     *
     * @param  array $data
     * @return null
     */
    private function setDataset(array $data = [])
    {
        // If we don't have any data, use input if we're allowed
        if ( ! count($data) && $this->allowHydrationFromInput) $data = Input::all();

        $this->dataset = $data;
    }

    /**
     * Set relationship messages
     *
     * @param  array $messages
     * @return null
     */
    private function setMessageset(array $messages = [])
    {
        // Passed messages rule, but we will use model messages if nothing has been passed
        $this->messageset = $this->selectMessages($messages);

        // If we don't have any rules, don't do anything because an error will never occur
        if ( ! count($this->rules)) return;

        // Determine file for validation messages
        $file = $this->languageFolder . '/' . str_replace('\\', '/', get_called_class()) . '.';

        $this->messages = [];

        // Process each rule
        foreach($this->rules as $field => $set)
        {
            foreach ($set as $rule)
            {
                // Remove parameters if we have them
                list($type,) = $this->getRuleTypeAndParameters($rule);

                // Generate a key using language file dot notation e.g. 'username.required'
                $key = implode('.', [$field, $type]);

                // Try to find a suitable error message
                $message = $this->getMessage($file, $key);
                if ($message) $this->messages[$key] = $message;
            }
        }
    }

    /**
     * Attach two records together via relationship
     *
     * @param  Vocal          $record
     * @param  string         $relationship
     * @param  string         $class
     * @param  integer|string $index
     * @return \Illuminate\Database\Eloquent\Model
     */
    private function setRelationship(Vocal $record, $relationship, $class, $index)
    {
        // If we have an index we're dealing with a many relationship
        if ($index !== null)
        {
            $collection = ($this->{$relationship}) ? $this->{$relationship} : new Collection;
            $collection->put($index, $record);
            return $this->setRelation($relationship, $collection);
        }

        // belongsTo and morphTo become parent records via associate
        if (method_exists($this->{$class}(), 'associate'))
        {
            return $model->associate($record)->forceSave();
        }
        else
        {
            return $this->setRelation($relationship, $record);
        }
    }

    /**
     * Set relationship rules
     *
     * @param  array $rules
     * @return null
     */
    private function setRuleset(array $rules = [])
    {
        // Passed rules rule, but we will use model rules if nothing has been passed
        $this->rules = $this->ruleset = $this->selectRules($rules);

        // Expand out rules
        foreach ($this->rules as $field => &$rule)
        {
            // Change pipe delimited rules to arrays
            $set = $this->pipeToArray($rule);
            $rule = $this->processRuleset($field, $set);
        }
    }

    /**
     * Generate a database compatible timestamp using now as default
     *
     * @param  mixed $value
     * @return string
     */
    public function timestamp($value = null)
    {
        // Use now if no time was passed
        if ( ! $value) $value = $this->freshTimestamp();

        return $this->fromDateTime($value);
    }

    /**
     * Determine if a model is using soft deletes
     *
     * @return bool
     */
    private function usesSoftDeletes()
    {
        return (
            (isset($this->forceDeleting) && ! $this->forceDeleting) ||
            (isset($this->softDelete) && ! $this->softDelete)
        );
    }

    /**
     * Validate a single record
     *
     * @param  array $data
     * @param  array $rules
     * @param  array $messages
     * @return bool
     */
    public function validate(array $data = [], array $rules = [], array $messages = [])
    {
        $this->prepareRecord($data, $rules, $messages);

        return $this->validateRecord();
    }

    /**
     * Register a validated model event with the dispatcher
     *
     * @param  \Closure|string $callback
     * @return void
     */
    public static function validated($callback)
    {
        static::registerModelEvent('validated', $callback);
    }

    /**
     * Validate a single record
     *
     * @return bool
     */
    private function validateRecord()
    {
        // Abort if beforeValidate() fails
        if ($this->fireModelEvent('validating') === false) return false;

        $validator = Validator::make($this->getAttributes(), $this->rules, $this->messages);
        $result = $validator->passes();

        // Update errors based on result
        $this->errors = ($result) ? new MessageBag : $validator->messages();

        $this->fireModelEvent('validated', false);

        return $result;
    }

    /**
     * Recursively validate a record
     *
     * @param  array $data
     * @param  array $rules
     * @param  array $messages
     * @return bool
     */
    public function validateRecursive(array $data = [], array $rules = [], array $messages = [])
    {
        return $this->recurse('validate', $data, $rules, $messages);
    }

    /**
     * Register a validating model event with the dispatcher
     *
     * @param  \Closure|string $callback
     * @return void
     */
    public static function validating($callback)
    {
        static::registerModelEvent('validating', $callback);
    }
}
