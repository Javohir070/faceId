import { router } from '@inertiajs/react';
import { Button, DatePicker, Input, Select } from 'antd';
import dayjs from 'dayjs';
import { useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader, { ResourceActions } from '../../../Components/PageHeader';
import Pagination from '../../../Components/Pagination';
import TableShell from '../../../Components/TableShell';
import { route } from '../../../routes';

export default function Index({
    notes,
    filters = {},
    employees = [],
    types = [],
}) {
    const [form, setForm] = useState({
        search: filters.search ?? '',
        employee_id: filters.employee_id ?? null,
        type: filters.type ?? null,
        date_from: filters.date_from ?? null,
        date_to: filters.date_to ?? null,
    });

    const updateField = (key, value) => {
        setForm((previous) => ({ ...previous, [key]: value }));
    };

    const submitFilters = () => {
        const payload = Object.fromEntries(
            Object.entries(form).filter(([, value]) => value !== null && value !== undefined && value !== ''),
        );

        router.get(route('admin.notes.index'), payload, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const resetFilters = () => {
        setForm({
            search: '',
            employee_id: null,
            type: null,
            date_from: null,
            date_to: null,
        });

        router.get(route('admin.notes.index'), {}, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const typeLabels = new Map(types.map((type) => [type.value, type.label]));

    return (
        <AdminLayout title="Izohlar">
            <PageHeader
                title="Izohlar"
                subtitle="Xodimlarning kechikish, erta ketish va yo‘qlama izohlarini boshqaring."
                createHref={route('admin.notes.create')}
                createLabel="Izoh qo‘shish"
            />

            <div className="mb-5 rounded-xl border border-line bg-surface p-4 shadow-ambient">
                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <Input
                        allowClear
                        placeholder="Izoh yoki xodim bo‘yicha qidiruv"
                        value={form.search}
                        onChange={(event) => updateField('search', event.target.value)}
                        onPressEnter={submitFilters}
                    />
                    <Select
                        allowClear
                        showSearch
                        className="w-full"
                        placeholder="Xodim"
                        optionFilterProp="label"
                        value={form.employee_id}
                        options={employees.map((employee) => ({
                            value: employee.id,
                            label: employee.name,
                        }))}
                        onChange={(value) => updateField('employee_id', value ?? null)}
                    />
                    <Select
                        allowClear
                        className="w-full"
                        placeholder="Tip"
                        value={form.type}
                        options={types}
                        onChange={(value) => updateField('type', value ?? null)}
                    />
                    <DatePicker
                        className="w-full"
                        placeholder="Boshlanish sanasi"
                        value={form.date_from ? dayjs(form.date_from) : null}
                        onChange={(value) => updateField('date_from', value ? value.format('YYYY-MM-DD') : null)}
                    />
                    <DatePicker
                        className="w-full"
                        placeholder="Tugash sanasi"
                        value={form.date_to ? dayjs(form.date_to) : null}
                        onChange={(value) => updateField('date_to', value ? value.format('YYYY-MM-DD') : null)}
                    />
                    <div className="flex gap-2">
                        <Button className="flex-1 !font-semibold" onClick={resetFilters}>
                            Tozalash
                        </Button>
                        <Button type="primary" className="flex-1 !font-bold" onClick={submitFilters}>
                            Filtrlash
                        </Button>
                    </div>
                </div>
            </div>

            <TableShell
                empty={notes.data.length === 0}
                createHref={route('admin.notes.create')}
                createLabel="Izoh qo‘shish"
                footer={notes.data.length > 0 ? (
                    <Pagination
                        links={notes.links}
                        perPage={notes.per_page}
                        total={notes.total}
                    />
                ) : null}
            >
                <table className="data-table">
                    <thead>
                        <tr>
                            <th className="w-16">N#</th>
                            <th>Xodim</th>
                            <th>Tip</th>
                            <th>Sana</th>
                            <th>Izoh</th>
                            <th className="text-right">Amallar</th>
                        </tr>
                    </thead>
                    <tbody>
                        {notes.data.map((note, index) => (
                            <tr key={note.id}>
                                <td className="font-medium text-ink-subtle">{(notes.from ?? 1) + index}</td>
                                <td className="font-bold text-ink">{note.employee?.name || '—'}</td>
                                <td className="font-semibold text-ink">{typeLabels.get(note.type) || '—'}</td>
                                <td className="whitespace-nowrap font-medium text-ink-muted">
                                    {note.dt ? note.dt.slice(0, 10) : '—'}
                                </td>
                                <td className="max-w-xl whitespace-normal font-medium text-ink-muted">
                                    {note.note || '—'}
                                </td>
                                <td>
                                    <ResourceActions
                                        editHref={route('admin.notes.edit', note.id)}
                                        deleteHref={route('admin.notes.destroy', note.id)}
                                    />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </TableShell>
        </AdminLayout>
    );
}
