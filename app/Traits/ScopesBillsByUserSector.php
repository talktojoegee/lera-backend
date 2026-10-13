<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

trait ScopesBillsByUserSector
{
    /**
     * Restrict billing queries to LGAs allowed by the user's sector.
     *
     * @param  Builder<\App\Models\Billing>  $query
     * @return Builder<\App\Models\Billing>
     */
    protected function applySectorScope(Builder $query, int $userId): Builder
    {
        $user = User::find($userId);

        if ($user === null) {
            throw new RuntimeException('User not found for export.');
        }

        $allowedLgaIds = $user->sectorLgaIds();

        if ($allowedLgaIds !== []) {
            $query->whereIn('lga_id', $allowedLgaIds);
        }

        return $query;
    }
}
