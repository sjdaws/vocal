<?php

use Sjdaws\Vocal\Vocal;

class UserAddress extends Vocal
{
    /**
     * The fields which can be filled from input
     *
     * @var array
     */
    protected $fillable = array('address', 'city');

    /**
     * The rules for validating this model
     *
     * @var array
     */
    public $rules = array(
        'address' => array('required', 'unique'),
        'city'    => array('required')
    );

    /**
     * Join parent record (not really needed)
     *
     * @return Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo('User', 'user_id');
    }
}
