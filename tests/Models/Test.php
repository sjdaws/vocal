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
    protected $fillable = array('description', 'password');

    /**
     * Hash password
     *
     * @var array
     */
    protected $hashAttributes = array('password');

    /**
     * The rules for validating this model
     *
     * @var array
     */
    public $rules = array(
        'description' => array('required', 'unique')
    );

    /*********************************************************************************************
     * Relationships
     ********************************************************************************************/

    /**
     * Join child records
     *
     * @return object
     */
    public function children()
    {
        return $this->hasMany('Sjdaws\Tests\Models\TestChild', 'test_id');
    }
}
