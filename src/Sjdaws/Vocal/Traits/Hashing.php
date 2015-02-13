<?php

namespace Sjdaws\Vocal\Traits;

use Illuminate\Hashing\BcryptHasher;

/**
 * @property array $attributes
 */
trait Hashing
{
    /**
     * The fields which should be hashed automatically
     *
     * @var array
     */
    protected $hashable = array();

    /**
     * Get the original value for an attribute
     *
     * @see    \Illuminate\Database\Eloquent\Model::getOriginal()
     * @param  string $key
     * @param  mixed  $default
     * @return array
     */
    abstract public function getOriginal($key = null, $default = null);

    /**
     * Hash any hashable attributes
     *
     * @return null
     */
    private function hashAttributes()
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
}
