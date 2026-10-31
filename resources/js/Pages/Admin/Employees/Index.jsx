import { Link, router, usePage } from '@inertiajs/react';
import { DeleteOutlined, PlusOutlined } from '@ant-design/icons';
import { App as AntApp, Button, Checkbox, Input, Select, TreeSelect } from 'antd';
import { useEffect, useMemo, useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import { route } from '../../../routes';
import PageHeader, { ResourceActions } from '../../../Components/PageHeader';
import Pagination from '../../../Components/Pagination';
import StatusTag from '../../../Components/StatusTag';
import TableShell from '../../../Components/TableShell';
import {
    buildOrgTreeData,
    encodeOrgFilter,
    parseOrgFilter,
} from '../../../utils/orgTreeSelect';

const faceOptions = [
    { value: '1', label: 'Yuz bor' },
    { value: '0', label: "Yuz yo'q" },
];

export default function Index({
    employees,
    filters = {},
    departments = [],
    groups = [],
    schedules = [],
    devices = [],
}) {
    const can = usePage().props.can || {};
    const { modal } = AntApp.useApp();
    const [selectedIds, setSelectedIds] = useState([]);
    const [form, setForm] = useState({
        search: filters.search ?? '',
        department_ids: filters.department_ids ?? [],
        group_ids: filters.group_ids ?? [],
        schedule_id: filters.schedule_id ?? null,
        device_id: filters.device_id ?? null,
        has_face: filters.has_face ?? null,
    });

    const orgTreeData = useMemo(
        () => buildOrgTreeData(departments, groups),
        [departments, groups],
    );

    // Sahifa yoki filtr almashsa tanlov tozalanadi — ko‘rinmaydigan xodim tasodifan o‘chirilmasin.
    useEffect(() => {
        setSelectedIds([]);
    }, [employees.data]);

    const pageIds = employees.data.map((employee) => employee.id);
    const allSelected = pageIds.length > 0 && pageIds.every((id) => selectedIds.includes(id));
    const someSelected = selectedIds.length > 0 && !allSelected;

    const toggleOne = (id, checked) => {
        setSelectedIds((current) => (checked ? [...current, id] : current.filter((item) => item !== id)));
    };

    const deleteSelected = () => {
        modal.confirm({
            title: 'Tanlangan xodimlarni o‘chirish',
            content: `${selectedIds.length} ta xodim o‘chiriladi. Ular yozilgan Face ID qurilmalardan ham navbat orqali olib tashlanadi. Bu amalni qaytarib bo‘lmaydi.`,
            okText: 'Ha, o‘chirish',
            cancelText: 'Bekor qilish',
            okButtonProps: { danger: true },
            onOk: () => new Promise((resolve, reject) => {
                router.post(route('admin.employees.bulk-destroy'), {
                    employee_ids: selectedIds,
                }, {
                    preserveScroll: true,
                    onSuccess: () => {
                        setSelectedIds([]);
                        resolve();
                    },
                    onError: reject,
                });
            }),
        });
    };

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

        router.get(route('admin.employees.index'), payload, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const resetFilters = () => {
        setForm({
            search: '',
            department_ids: [],
            group_ids: [],
            schedule_id: null,
            device_id: null,
            has_face: null,
        });

        router.get(route('admin.employees.index'), {}, {
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
        const base = route('admin.employees.export');
        const qs = query.toString();

        return qs ? `${base}?${qs}` : base;
    };

    return (
        <AdminLayout title="Xodimlar">
            <PageHeader
                title="Xodimlar"
                subtitle="Xodimlar, kartalar, yuz rasmlari va bo'limlarni boshqaring."
                actions={(
                    <div className="flex flex-wrap items-center gap-2">
                        <a
                            href={exportHref()}
                            className="inline-flex h-11 items-center rounded-lg border border-line px-4 text-sm font-semibold text-ink hover:bg-surface-muted"
                        >
                            Excel yuklab olish
                        </a>
                        {can['employees-pull'] ? (
                            <Link
                                href={route('admin.employees.pull')}
                                className="inline-flex h-11 items-center rounded-lg border border-line px-4 text-sm font-semibold text-ink hover:bg-surface-muted"
                            >
                                Qurilmadan tortish
                            </Link>
                        ) : null}
                        <Button
                            type="primary"
                            size="large"
                            className="!h-11 shrink-0 !rounded-lg !px-5 !font-bold"
                            icon={<PlusOutlined />}
                            onClick={() => router.visit(route('admin.employees.create'))}
                        >
                            Xodim qo&apos;shish
                        </Button>
                    </div>
                )}
            />

            <div className="mb-5 rounded-xl border border-line bg-surface p-4 shadow-ambient">
                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
                    <Input
                        allowClear
                        placeholder="Qidiruv (ism / karta)"
                        value={form.search}
                        onChange={(e) => updateField('search', e.target.value)}
                        onPressEnter={submitFilters}
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
                        placeholder="Jadval"
                        optionFilterProp="label"
                        value={form.schedule_id}
                        options={schedules.map((item) => ({ value: item.id, label: item.name }))}
                        onChange={(value) => updateField('schedule_id', value ?? null)}
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
                        placeholder="Yuz"
                        value={form.has_face}
                        options={faceOptions}
                        onChange={(value) => updateField('has_face', value ?? null)}
                        className="w-full"
                    />
                    <div className="flex gap-2">
                        <Button type="primary" className="flex-1 !font-bold" onClick={submitFilters}>
                            Filtrlash
                        </Button>
                        <Button className="!font-semibold" onClick={resetFilters}>
                            Tozalash
                        </Button>
                    </div>
                </div>
            </div>

            {selectedIds.length > 0 ? (
                <div className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-brand-600/20 bg-brand-50 px-4 py-3 dark:border-brand-400/20 dark:bg-brand-950/40">
                    <p className="text-sm font-bold text-ink">
                        {selectedIds.length}
                        {' ta xodim tanlandi'}
                    </p>
                    <div className="flex items-center gap-2">
                        <Button className="!rounded-lg !font-bold" onClick={() => setSelectedIds([])}>
                            Bekor qilish
                        </Button>
                        <Button
                            danger
                            type="primary"
                            className="!rounded-lg !font-bold"
                            icon={<DeleteOutlined />}
                            onClick={deleteSelected}
                        >
                            O‘chirish
                        </Button>
                    </div>
                </div>
            ) : null}

            <TableShell
                empty={employees.data.length === 0}
                createHref={route('admin.employees.create')}
                createLabel="Xodim qo'shish"
                footer={employees.data.length > 0 ? (
                    <Pagination
                        links={employees.links}
                        perPage={employees.per_page}
                        total={employees.total}
                    />
                ) : null}
            >
                <table className="data-table">
                    <thead>
                        <tr>
                            <th className="w-10">
                                <Checkbox
                                    checked={allSelected}
                                    indeterminate={someSelected}
                                    onChange={(e) => setSelectedIds(e.target.checked ? pageIds : [])}
                                    aria-label="Sahifadagi barcha xodimlarni tanlash"
                                />
                            </th>
                            <th className="w-16">N#</th>
                            <th>F.I.Sh.</th>
                            <th>Karta</th>
                            <th>Jins</th>
                            <th>Lavozim</th>
                            <th>Bo&apos;lim</th>
                            <th>Jadval</th>
                            <th>Yuz</th>
                            <th>Amal qilish</th>
                            <th className="text-right">Amallar</th>
                        </tr>
                    </thead>
                    <tbody>
                        {employees.data.map((employee, index) => (
                            <tr key={employee.id} className={selectedIds.includes(employee.id) ? 'bg-brand-50/70' : undefined}>
                                <td>
                                    <Checkbox
                                        checked={selectedIds.includes(employee.id)}
                                        onChange={(e) => toggleOne(employee.id, e.target.checked)}
                                        aria-label={`«${employee.name}» ni tanlash`}
                                    />
                                </td>
                                <td className="font-medium text-ink-subtle">{(employees.from ?? 1) + index}</td>
                                <td>
                                    <Link
                                        href={route('admin.employees.show', employee.id)}
                                        className="font-bold text-ink transition-colors duration-150 ease-productive hover:text-brand-700"
                                    >
                                        {employee.name}
                                    </Link>
                                </td>
                                <td className="font-medium">{employee.card}</td>
                                <td className="font-medium">{employee.gender_label || '—'}</td>
                                <td className="font-medium">{employee.position || '—'}</td>
                                <td className="font-medium">{employee.department?.name || '—'}</td>
                                <td className="font-medium">{employee.schedule?.name || '—'}</td>
                                <td>
                                    <StatusTag tone={employee.has_face ? 'success' : 'neutral'}>
                                        {employee.has_face ? 'Bor' : "Yo'q"}
                                    </StatusTag>
                                </td>
                                <td className="font-medium text-ink-muted">
                                    {employee.valid_from_dt}
                                    {' — '}
                                    {employee.valid_to_dt}
                                </td>
                                <td>
                                    <ResourceActions
                                        showHref={route('admin.employees.show', employee.id)}
                                        editHref={route('admin.employees.edit', employee.id)}
                                        deleteHref={route('admin.employees.destroy', employee.id)}
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
