<?php

namespace App\Models\Concerns;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait BelongsToBranch
{
    protected static function bootBelongsToBranch(): void
    {
        static::addGlobalScope('branch', function (Builder $builder): void {
            $branchId = Auth::user()?->branch_id;
            if ($branchId) {
                $builder->where($builder->qualifyColumn('branch_id'), $branchId);
            }
        });

        static::creating(function ($model): void {
            if (blank($model->branch_id)) {
                $model->branch_id = Auth::user()?->branch_id ?? Branch::query()->where('code', 'MAIN')->value('id');
            }
        });
    }

    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->withoutGlobalScope('branch')->where($query->qualifyColumn('branch_id'), $branchId);
    }
}
