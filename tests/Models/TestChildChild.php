<?php

namespace Sjdaws\Tests\Models;

use Sjdaws\Vocal\Vocal;

class TestChildChild extends Vocal
{
    /**
     * The fields which can be filled from input
     *
     * @var array
     */
    protected $fillable = array('name');

    /**
     * The rules for validating this model
     *
     * @var array
     */
    public $rules = array(
        'name' => array('required', 'unique')
    );

    /**
     * The table name
     *
     * @var string
     */
    protected $table = 'test_child_child';

    /**
     * Join parent record
     *
     * @return object
     */
    public function parent()
    {
        return $this->belongsTo('Sjdaws\Tests\Models\TestChild', 'test_child_id');
    }
}
