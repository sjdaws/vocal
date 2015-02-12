<?php

namespace Sjdaws\Vocal;

class Relationships
{
    /**
     * The model we're checking relationships against
     *
     * @var Vocal
     */
    private $model;

    /**
     * Create a new instance
     *
     * @param Vocal $model
     */
    public function __construct(Vocal $model)
    {
        $this->model = $model;
    }
}
