<?php

use LakeDawson\Vocal\Vocal;

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
    protected $rules = array(
        'username' => array('required', 'unique'),
        'email'    => array('required', 'unique', 'email')
    );

    /**
     * Enable soft deletions for this model
     *
     * @var bool
     */
    protected $softDelete = true;


    /*********************************************************************************************
     * Relationships
     ********************************************************************************************/

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
