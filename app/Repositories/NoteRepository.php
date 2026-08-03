<?php

namespace App\Repositories;

use App\Interfaces\NoteRepositoryInterface;
use App\Models\Note;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class NoteRepository implements NoteRepositoryInterface
{
    /**
     * @param  array{employee_id?:?int, type?:?int, date_from?:?string, date_to?:?string, search?:?string}  $filters
     * @return Builder<Note>
     */
    public function filteredQuery(array $filters = []): Builder
    {
        $query = Note::query()
            ->with(['employee:id,name'])
            ->whereHas('employee');

        if (! empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('dt', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('dt', '<=', $filters['date_to']);
        }

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('note', 'like', '%'.$search.'%')
                    ->orWhereHas(
                        'employee',
                        fn ($employeeQuery) => $employeeQuery->where('name', 'like', '%'.$search.'%'),
                    );
            });
        }

        return $query->latest();
    }

    /**
     * @param  array{employee_id?:?int, type?:?int, date_from?:?string, date_to?:?string, search?:?string}  $filters
     */
    public function paginate(int $perPage = 12, array $filters = []): LengthAwarePaginator
    {
        return $this->filteredQuery($filters)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $data): Note
    {
        return Note::query()->create($data);
    }

    public function update(Note $note, array $data): Note
    {
        $note->update($data);

        return $note->fresh();
    }

    public function delete(Note $note): void
    {
        $note->delete();
    }
}
