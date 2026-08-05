import { Link, router, usePage } from '@inertiajs/react';
import { Button, DatePicker, Select, TreeSelect } from 'antd';
import dayjs from 'dayjs';
import { useMemo, useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import { route } from '../../../routes';
import PageHeader, { EmptyState } from '../../../Components/PageHeader';
import Pagination from '../../../Components/Pagination';
import TableShell from '../../../Components/TableShell';
import {
    buildOrgTreeData,
    encodeOrgFilter,
    parseOrgFilter,
} from '../../../utils/orgTreeSelect';

const typeOptions = [
    { value: 1, label: 'Kirish' },
    { value: 2, label: 'Chiqish' },
    { value: 3, label: 'Kechikkan' },
    { value: 4, label: 'Erta ketgan' },
    { value: 5, label: 'Kelgan' },
    { value: 6, label: 'Kelmagan' },
    { value: 7, label: 'Ish vaqti' },
    { value: 8, label: 'Individual' },
];

const defaultDateFrom = () => dayjs().startOf('day').format('YYYY-MM-DD HH:mm:ss');
const defaultDateTo = () => dayjs().hour(23).minute(59).second(0).format('YYYY-MM-DD HH:mm:ss');

export default function Index({
    reports,
    columns = [],
    filters,
    employees,
    departments,
    groups = [],
    devices,
    categories,
    schedules,
}) {
    const can = usePage().props.can || {};
    const [form, setForm] = useState({
        employee_id: filters.employee_id ?? null,
        department_ids: filters.department_ids ?? [],
        group_ids: filters.group_ids ?? [],
        device_id: filters.device_id ?? null,
        category_id: filters.category_id ?? null,
        schedule_id: filters.schedule_id ?? null,
        type: filters.type ?? null,
        date_from: filters.date_from ?? defaultDateFrom(),
        date_to: filters.date_to ?? defaultDateTo(),
    });

    const orgTreeData = useMemo(
        () => buildOrgTreeData(departments, groups),
        [departments, groups],
    );

    const updateField = (key, value) => {
        setForm((prev) => ({ ...prev, [key]: value }));
    };

    const updateOrgFilter = (values) => {
        setForm((prev) => ({
            ...prev,
            ...parseOrgFilter(values ?? []),
        }));
    };

    const submitFilters = () => {
        const payload = Object.fromEntries(
            Object.entries(form).filter(([, value]) => {
                if (Array.isArray(value)) {
                    return value.length > 0;
                }
                return value !== null && value !== undefined && value !== '';
            }),
        );

        router.get(route('admin.reports.index'), payload, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const resetFilters = () => {
        const dateFrom = defaultDateFrom();
        const dateTo = defaultDateTo();

        setForm({
            employee_id: null,
            department_ids: [],
            group_ids: [],
            device_id: null,
            category_id: null,
            schedule_id: null,
            type: null,
            date_from: dateFrom,
            date_to: dateTo,
        });

        router.get(route('admin.reports.index'), {
            date_from: dateFrom,
            date_to: dateTo,
        }, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const exportHref = () => {
        const payload = Object.fromEntries(
            Object.entries(filters).filter(([, value]) => {
                if (Array.isArray(value)) {
                    return value.length > 0;
                }
                return value !== null && value !== undefined && value !== '';
            }),
        );
        const query = new URLSearchParams();
        Object.entries(payload).forEach(([key, value]) => {
            if (Array.isArray(value)) {
                value.forEach((item) => query.append(`${key}[]`, String(item)));
                return;
            }
            query.set(key, String(value));
        });
        const base = route('admin.reports.export');
        const qs = query.toString();

        return qs ? `${base}?${qs}` : base;
    };

    const from = reports.from ?? (reports.data.length ? 1 : 0);
    const to = reports.to ?? reports.data.length;
    const total = reports.total ?? reports.data.length;

    return (
        <AdminLayout title="Hisobotlar">
            <PageHeader
                title="Hisobotlar"
                subtitle="Xodimlarning kirish va chiqish yozuvlari."
                actions={(
                    <div className="flex gap-3">
                        {can['reports-pull'] ? (
                            <Link
                                href={route('admin.reports.pull')}
                                className="inline-flex h-10 items-center rounded-lg bg-brand-600 px-4 text-sm font-bold text-white hover:bg-brand-700"
                            >
                                Qurilmadan tortish
                            </Link>
                        ) : null}
                    </div>
                )}
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
                    <TreeSelect
                        allowClear
                        showSearch
                        treeCheckable
                        treeDefaultExpandAll
                        showCheckedStrategy={TreeSelect.SHOW_PARENT}
                        placeholder="Bo'lim / Guruh"
                        treeNodeFilterProp="title"
                        value={encodeOrgFilter(form.department_ids, form.group_ids)}
                        treeData={orgTreeData}
                        onChange={updateOrgFilter}
                        className="w-full"
                        maxTagCount="responsive"
                        popupMatchSelectWidth={360}
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
                        showTime
                        className="w-full"
                        placeholder="Sana va vaqt dan"
                        value={form.date_from ? dayjs(form.date_from) : null}
                        onChange={(value) => updateField('date_from', value ? value.format('YYYY-MM-DD HH:mm:ss') : null)}
                    />
                    <DatePicker
                        showTime
                        className="w-full"
                        placeholder="Sana va vaqt gacha"
                        value={form.date_to ? dayjs(form.date_to) : null}
                        onChange={(value) => updateField('date_to', value ? value.format('YYYY-MM-DD HH:mm:ss') : null)}
                    />
                </div>

                <div className="mt-4 flex flex-wrap gap-3 justify-end">
                    <a
                        href={exportHref()}
                        className="inline-flex h-10 items-center rounded-lg border border-line bg-surface px-4 text-sm font-bold text-ink hover:bg-surface-muted"
                    >
                        Excel yuklab olish
                    </a>
                    <Button onClick={resetFilters}>
                        Tozalash
                    </Button>
                    <Button type="primary" className="!font-bold" onClick={submitFilters}>
                        Ko'rsatish
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
                            {columns.map((col) => (
                                <th key={col.key}>{col.label}</th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {reports.data.map((row, index) => (
                            <tr key={row.id ?? `${row.employee_name}-${row.date ?? row.occurred_at}-${index}`}>
                                <td className="font-medium text-ink-subtle">{(from || 1) + index}</td>
                                {columns.map((col) => (
                                    <td key={col.key} className="font-medium">
                                        {row[col.key] ?? '—'}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </TableShell>
        </AdminLayout>
    );
}
