<?php

namespace App\Interfaces;

use App\Models\Note;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

interface NoteRepositoryInterface
{
    /**
     * @param  array{employee_id?:?int, department_id?:?int, group_id?:?int, type?:?int, date_from?:?string, date_to?:?string, search?:?string}  $filters
     * @return Builder<Note>
     */
    public function filteredQuery(array $filters = []): Builder;

    /**
     * @param  array{employee_id?:?int, department_id?:?int, group_id?:?int, type?:?int, date_from?:?string, date_to?:?string, search?:?string}  $filters
     */
    public function paginate(int $perPage = 12, array $filters = [], string $pageName = 'page'): LengthAwarePaginator;

    public function create(array $data): Note;

    public function update(Note $note, array $data): Note;

    public function delete(Note $note): void;
}
