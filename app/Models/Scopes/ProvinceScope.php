<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class ProvinceScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        if (!request()->hasHeader('Province-ID')) {
            return;
        }

        $provinceId = request()->header('Province-ID');

        if (!is_numeric($provinceId)) {
            return;
        }

        $builder->where($model->getTable() . '.province_id', $provinceId);
    }
}
