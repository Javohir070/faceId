<?php

namespace App\Services;

use App\Interfaces\NoteRepositoryInterface;
use App\Models\Employee;
use App\Models\Note;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class NoteService
{
    public function __construct(
        private readonly NoteRepositoryInterface $noteRepository,
    ) {}

    /**
     * @param  array{employee_id?:?int, department_id?:?int, group_id?:?int, type?:?int, date_from?:?string, date_to?:?string, search?:?string}  $filters
     */
    public function paginate(int $perPage = 12, array $filters = [], string $pageName = 'page'): LengthAwarePaginator
    {
        return $this->noteRepository->paginate($perPage, $filters, $pageName);
    }

    /**
     * @param  array{employee_id?:?int, department_id?:?int, group_id?:?int, type?:?int, date_from?:?string, date_to?:?string, search?:?string}  $filters
     * @return Builder<Note>
     */
    public function getFilteredQuery(array $filters = []): Builder
    {
        return $this->noteRepository->filteredQuery($filters);
    }

    public function create(array $data): Note
    {
        return $this->noteRepository->create($data);
    }

    public function update(Note $note, array $data): Note
    {
        return $this->noteRepository->update($note, $data);
    }

    public function delete(Note $note): void
    {
        $this->noteRepository->delete($note);
    }

    public function employeesForForm(): Collection
    {
        return Employee::query()
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
