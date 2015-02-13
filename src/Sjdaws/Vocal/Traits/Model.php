<?php

namespace Sjdaws\Vocal\Traits;

trait Model
{
    /**
     * Find or create a record if it doesn't exist
     *
     * @param  Vocal          $model
     * @param  integer|string $key
     * @return Model
     */
    public function findOrCreateRecord(Vocal $model, $key = null)
    {
        if ($key)
        {
            $record = ($this->usesSoftDeletes()) ? $model->withTrashed()->find($key) : $model->find($key);

            // Only return a record if we found one, otherwise we will end up sending a new record back below
            if ($record) return $record;
        }

        return new $model;
    }

    /**
     * Determine if a model is using soft deletes
     *
     * @return bool
     */
    private function usesSoftDeletes()
    {
        return (
            (isset($this->forceDeleting) && ! $this->forceDeleting) ||
            (isset($this->softDelete) && ! $this->softDelete)
        );
    }
}
