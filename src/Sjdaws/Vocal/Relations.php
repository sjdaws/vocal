<?php

namespace Sjdaws\Vocal;

use Illuminate\Database\Eloquent\Relations\Relation;
use ReflectionClass;

class Relations
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
