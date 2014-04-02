<?php

namespace LakeDawson\Tests\Models;

use LakeDawson\Vocal\Vocal;

class TestChild extends Vocal
{
    /**
     * The fields which can be filled from input
     *
     * @var array
     */
    protected $fillable = array('description');

    /**
     * The rules for validating this model
     *
     * @var array
     */
    protected $rules = array(
        'description' => array('required', 'unique')
    );

    /*********************************************************************************************
     * Relationships
     ********************************************************************************************/

    /**
     * Join parent record
     *
     * @return object
     */
    public function parent()
    {
        return $this->belongsTo('LakeDawson\Tests\Models\Test', 'test_id');
    }
}
