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
     * We should fill this model automatically from input
     *
     * @var bool
     */
    public $fillFromInput = true;

    /**
     * The rules for validating this model
     *
     * @var array
     */
    public static $rules = array(
        'description' => array('required')
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
