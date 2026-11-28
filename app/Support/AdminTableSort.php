<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class AdminTableSort
{
    public static function apply(Request $request, Builder $query, array $columns): Builder
    {
        $sort = $request->validate([
            'sort' => ['sometimes', 'required', Rule::in(array_keys($columns))],
            'direction' => ['sometimes', 'required', Rule::in(['asc', 'desc'])],
        ]);

        if (isset($sort['sort'])) {
            $query->reorder()->orderBy($columns[$sort['sort']], $sort['direction'] ?? 'asc');
        }

        return $query;
    }
}
