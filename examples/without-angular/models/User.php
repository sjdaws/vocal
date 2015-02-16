<?php

use Sjdaws\Vocal\Vocal;

class User extends Vocal
{
    /**
     * The fields which can be filled from input
     *
     * @var array
     */
    protected $fillable = array('username', 'email');

    /**
     * The rules for validating this model
     *
     * @var array
     */
    public $rules = array(
        'username' => array('required', 'unique'),
        'email'    => array('required', 'unique', 'email')
    );

    /**
     * Join addresses
     *
     * @return Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function addresses()
    {
        return $this->hasMany('UserAddress', 'user_id');
    }
}
