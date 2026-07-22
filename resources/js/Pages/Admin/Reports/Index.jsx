import { router } from '@inertiajs/react';
import { Button, DatePicker, Select } from 'antd';
import dayjs from 'dayjs';
import { useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import { route } from '../../../routes';
import PageHeader, { EmptyState } from '../../../Components/PageHeader';
import Pagination from '../../../Components/Pagination';
import TableShell from '../../../Components/TableShell';

const typeOptions = [
    { value: 'entry', label: 'Kirish' },
    { value: 'exit', label: 'Chiqish' },
];

export default function Index({
    reports,
    filters,
    employees,
    departments,
    devices,
    categories,
    schedules,
}) {
    const [form, setForm] = useState({
        employee_id: filters.employee_id ?? null,
        department_id: filters.department_id ?? null,
        device_id: filters.device_id ?? null,
        category_id: filters.category_id ?? null,
        schedule_id: filters.schedule_id ?? null,
        type: filters.type ?? null,
        date_from: filters.date_from ?? null,
        date_to: filters.date_to ?? null,
    });

    const updateField = (key, value) => {
        setForm((prev) => ({ ...prev, [key]: value }));
    };

    const submitFilters = () => {
        const payload = Object.fromEntries(
            Object.entries(form).filter(([, value]) => value !== null && value !== undefined && value !== ''),
        );

        router.get(route('admin.reports.index'), payload, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const resetFilters = () => {
        setForm({
            employee_id: null,
            department_id: null,
            device_id: null,
            category_id: null,
            schedule_id: null,
            type: null,
            date_from: null,
            date_to: null,
        });

        router.get(route('admin.reports.index'), {}, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const from = reports.from ?? (reports.data.length ? 1 : 0);
    const to = reports.to ?? reports.data.length;
    const total = reports.total ?? reports.data.length;

    return (
        <AdminLayout title="Hisobotlar">
            <PageHeader
                title="Hisobotlar"
                subtitle="Xodimlarning kirish va chiqish yozuvlari."
            />

            <div className="mb-5 rounded-xl border border-line bg-surface p-4 shadow-ambient">
                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <Select
                        allowClear
                        showSearch
                        placeholder="Xodim"
                        optionFilterProp="label"
                        value={form.employee_id}
                        options={employees.map((item) => ({ value: item.id, label: item.name }))}
                        onChange={(value) => updateField('employee_id', value ?? null)}
                        className="w-full"
                    />
                    <Select
                        allowClear
                        showSearch
                        placeholder="Bo'lim"
                        optionFilterProp="label"
                        value={form.department_id}
                        options={departments.map((item) => ({ value: item.id, label: item.name }))}
                        onChange={(value) => updateField('department_id', value ?? null)}
                        className="w-full"
                    />
                    <Select
                        allowClear
                        showSearch
                        placeholder="Qurilma"
                        optionFilterProp="label"
                        value={form.device_id}
                        options={devices.map((item) => ({ value: item.id, label: item.name }))}
                        onChange={(value) => updateField('device_id', value ?? null)}
                        className="w-full"
                    />
                    <Select
                        allowClear
                        showSearch
                        placeholder="Kategoriya"
                        optionFilterProp="label"
                        value={form.category_id}
                        options={categories.map((item) => ({ value: item.id, label: item.name }))}
                        onChange={(value) => updateField('category_id', value ?? null)}
                        className="w-full"
                    />
                    <Select
                        allowClear
                        showSearch
                        placeholder="Jadval"
                        optionFilterProp="label"
                        value={form.schedule_id}
                        options={schedules.map((item) => ({ value: item.id, label: item.name }))}
                        onChange={(value) => updateField('schedule_id', value ?? null)}
                        className="w-full"
                    />
                    <Select
                        allowClear
                        placeholder="Tur"
                        value={form.type}
                        options={typeOptions}
                        onChange={(value) => updateField('type', value ?? null)}
                        className="w-full"
                    />
                    <DatePicker
                        className="w-full"
                        placeholder="Sana dan"
                        value={form.date_from ? dayjs(form.date_from) : null}
                        onChange={(value) => updateField('date_from', value ? value.format('YYYY-MM-DD') : null)}
                    />
                    <DatePicker
                        className="w-full"
                        placeholder="Sana gacha"
                        value={form.date_to ? dayjs(form.date_to) : null}
                        onChange={(value) => updateField('date_to', value ? value.format('YYYY-MM-DD') : null)}
                    />
                </div>

                <div className="mt-4 flex flex-wrap gap-3">
                    <Button type="primary" className="!font-bold" onClick={submitFilters}>
                        Ko'rsatish
                    </Button>
                    <Button onClick={resetFilters}>
                        Tozalash
                    </Button>
                </div>
            </div>

            <TableShell
                empty={reports.data.length === 0}
                emptyState={(
                    <EmptyState
                        title="Hisobot yozuvlari topilmadi"
                        description="Filtrlarni o'zgartiring yoki qurilmadan kirish/chiqish amalga oshirilguncha kuting."
                    />
                )}
                footer={reports.data.length > 0 ? (
                    <div className="flex w-full flex-wrap items-center justify-between gap-3">
                        <span className="text-sm font-medium text-ink-muted">
                            {from}
                            {' '}
                            dan
                            {' '}
                            {to}
                            {' '}
                            gacha, jami
                            {' '}
                            {total}
                            {' '}
                            ta yozuv
                        </span>
                        <Pagination
                            links={reports.links}
                            perPage={reports.per_page}
                            total={reports.total}
                        />
                    </div>
                ) : null}
            >
                <table className="data-table">
                    <thead>
                        <tr>
                            <th className="w-12">№</th>
                            <th>Ism</th>
                            <th>Bo'lim</th>
                            <th>Qurilma</th>
                            <th>Tur</th>
                            <th>Sana va vaqt</th>
                        </tr>
                    </thead>
                    <tbody>
                        {reports.data.map((report, index) => (
                            <tr key={report.id}>
                                <td className="font-medium text-ink-subtle">{(from || 1) + index}</td>
                                <td className="font-bold text-ink">{report.employee_name}</td>
                                <td className="font-medium">{report.department_name || '—'}</td>
                                <td className="font-medium">{report.device_name || '—'}</td>
                                <td>
                                    <span
                                        className={
                                            report.type === 'exit'
                                                ? 'font-semibold text-amber-600'
                                                : 'font-semibold text-emerald-600'
                                        }
                                    >
                                        {report.type_label}
                                    </span>
                                </td>
                                <td className="font-medium text-ink-muted">{report.occurred_at}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </TableShell>
        </AdminLayout>
    );
}
