<?php

namespace App\Domains\Shared\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ListFilters
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>  $columns
     * @param  array<string, list<string>>  $relations
     * @param  list<string>  $statuses
     * @return Builder<TModel>
     */
    public function apply(Request $request, Builder $query, array $columns, array $relations = [], array $statuses = [], ?string $statusColumn = 'status'): Builder
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', Rule::in($statuses)],
        ]);
        $filters = $this->values($request);
        if ($filters['search'] !== '') {
            $pattern = '%'.addcslashes($filters['search'], '%_\\').'%';
            $query->where(function (Builder $search) use ($columns, $relations, $pattern): void {
                foreach ($columns as $column) {
                    $search->orWhere($column, 'like', $pattern);
                }
                foreach ($relations as $relation => $fields) {
                    $search->orWhereHas($relation, function (Builder $related) use ($fields, $pattern): void {
                        $related->where(function (Builder $nested) use ($fields, $pattern): void {
                            foreach ($fields as $field) {
                                $nested->orWhere($field, 'like', $pattern);
                            }
                        });
                    });
                }
            });
        }
        if ($statusColumn !== null && $filters['status'] !== '') {
            $query->where($statusColumn, $filters['status']);
        }

        return $query;
    }

    /** @return array{search: string, status: string} */
    public function values(Request $request): array
    {
        return ['search' => is_string($request->input('search')) ? trim($request->input('search')) : '', 'status' => is_string($request->input('status')) ? $request->input('status') : ''];
    }
}
