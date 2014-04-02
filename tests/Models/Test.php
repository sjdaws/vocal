<?php

namespace LakeDawson\Tests\Models;

use LakeDawson\Vocal\Vocal;

class Test extends Vocal
{
    /**
     * The fields which can be filled from input
     *
     * @var array
     */
    protected $fillable = array('description');

    /**
     * We should fill this model automatically from input
     *
     * @var bool
     */
    protected $fillFromInput = false;

    /**
     * The rules for validating this model
     *
     * @var array
     */
    protected $rules = array(
        'description' => array('required', 'unique:~table,~field,~id,id,description')
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
        return $this->hasMany('LakeDawson\Tests\Models\TestChild', 'test_id');
    }
}
