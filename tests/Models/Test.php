<?php

namespace Sjdaws\Tests\Models;

use Sjdaws\Vocal\Vocal;

class Test extends Vocal
{
    /**
     * The fields which can be filled from input
     *
     * @var array
     */
    protected $fillable = array('name', 'description', 'password');

    /**
     * Hash password
     *
     * @var array
     */
    protected $hashable = array('password');

    /**
     * Callback test variable, 1 uses beforeHydrate, 2 uses afterHydrate, 0 disables
     *
     * @var integer
     */
    private $callbackTest = 0;

    /**
     * The rules for validating this model
     *
     * @var array
     */
    public $rules = array(
        'name' => array('required', 'unique'),
    );

    /**
     * Test we can alter stuff after hydration
     *
     * @return null
     */
    public function afterHydrate()
    {
        if ($this->callbackTest == 2) $this->name = 'Callback';
    }

    /**
     * Test that hydration is aborted if this returns false
     *
     * @return bool
     */
    public function beforeHydrate()
    {
        if ($this->callbackTest == 1) return false;
    }

    /**
     * Join child records
     *
     * @return object
     */
    public function children()
    {
        return $this->hasMany('Sjdaws\Tests\Models\TestChild', 'test_id');
    }

    /**
     * Toggle callback test
     *
     * @param  integer $value
     * @return null
     */
    public function setCallbackTest($value)
    {
        $this->callbackTest = $value;
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
