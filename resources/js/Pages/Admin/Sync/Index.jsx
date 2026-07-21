import { router } from '@inertiajs/react';
import { Button, Checkbox, Input, Select } from 'antd';
import { useMemo, useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader from '../../../Components/PageHeader';

function PanelTable({
    title,
    rows,
    selectedIds,
    onToggleAll,
    onToggleOne,
    columns,
    search,
    onSearchChange,
    pageSize,
    onPageSizeChange,
    emptyLabel,
}) {
    const filtered = useMemo(() => {
        const query = search.trim().toLowerCase();

        if (!query) {
            return rows;
        }

        return rows.filter((row) => Object.values(row).some((value) => {
            if (value === null || value === undefined) {
                return false;
            }

            return String(value).toLowerCase().includes(query);
        }));
    }, [rows, search]);

    const visible = filtered.slice(0, pageSize);
    const allVisibleSelected = visible.length > 0 && visible.every((row) => selectedIds.includes(row.id));
    const someVisibleSelected = visible.some((row) => selectedIds.includes(row.id)) && !allVisibleSelected;

    return (
        <div className="flex min-h-[520px] flex-1 flex-col rounded-xl border border-line bg-surface shadow-ambient">
            <div className="border-b border-line px-4 py-3">
                <h2 className="text-base font-extrabold text-ink">{title}</h2>
            </div>

            <div className="flex flex-wrap items-center gap-3 border-b border-line px-4 py-3">
                <Select
                    value={pageSize}
                    options={[25, 50, 100].map((size) => ({ value: size, label: String(size) }))}
                    onChange={onPageSizeChange}
                    className="min-w-20"
                />
                <Input
                    allowClear
                    placeholder="Nima qidiryapsiz?"
                    value={search}
                    onChange={(e) => onSearchChange(e.target.value)}
                    className="max-w-xs"
                />
            </div>

            <div className="overflow-x-auto">
                <table className="data-table devices-table min-w-full">
                    <thead>
                        <tr>
                            <th className="devices-table__checkbox">
                                <Checkbox
                                    checked={allVisibleSelected}
                                    indeterminate={someVisibleSelected}
                                    onChange={(e) => onToggleAll(e.target.checked, visible.map((row) => row.id))}
                                />
                            </th>
                            {columns.map((column) => (
                                <th key={column.key}>{column.label}</th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {visible.length === 0 ? (
                            <tr>
                                <td colSpan={columns.length + 1} className="py-10 text-center text-sm text-ink-muted">
                                    {emptyLabel}
                                </td>
                            </tr>
                        ) : visible.map((row, index) => (
                            <tr key={row.id}>
                                <td className="devices-table__checkbox">
                                    <Checkbox
                                        checked={selectedIds.includes(row.id)}
                                        onChange={(e) => onToggleOne(row.id, e.target.checked)}
                                    />
                                </td>
                                {columns.map((column) => (
                                    <td key={column.key} className={column.className}>
                                        {column.render(row, index)}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <div className="mt-auto border-t border-line px-4 py-3 text-sm text-ink-muted">
                1 dan
                {' '}
                {visible.length}
                {' '}
                gacha, jami
                {' '}
                {filtered.length}
                {' '}
                ta yozuv
            </div>
        </div>
    );
}

export default function Index({ devices, employees, categories, departments }) {
    const [selectedDeviceIds, setSelectedDeviceIds] = useState([]);
    const [selectedEmployeeIds, setSelectedEmployeeIds] = useState([]);
    const [deviceSearch, setDeviceSearch] = useState('');
    const [employeeSearch, setEmployeeSearch] = useState('');
    const [devicePageSize, setDevicePageSize] = useState(50);
    const [employeePageSize, setEmployeePageSize] = useState(50);
    const [categoryFilter, setCategoryFilter] = useState(null);
    const [departmentFilter, setDepartmentFilter] = useState(null);
    const [transferring, setTransferring] = useState(false);

    const filteredDevices = useMemo(() => {
        if (!categoryFilter) {
            return devices;
        }

        return devices.filter((device) => device.category?.id === categoryFilter);
    }, [devices, categoryFilter]);

    const filteredEmployees = useMemo(() => {
        if (!departmentFilter) {
            return employees;
        }

        return employees.filter((employee) => employee.department?.id === departmentFilter);
    }, [employees, departmentFilter]);

    const toggleIds = (setter, id, checked) => {
        setter((prev) => (checked ? [...prev, id] : prev.filter((item) => item !== id)));
    };

    const toggleAll = (setter, checked, ids) => {
        setter((prev) => {
            if (!checked) {
                return prev.filter((id) => !ids.includes(id));
            }

            return [...new Set([...prev, ...ids])];
        });
    };

    const submitTransfer = () => {
        setTransferring(true);
        router.post('/admin/sync/transfer', {
            device_ids: selectedDeviceIds,
            employee_ids: selectedEmployeeIds,
        }, {
            preserveScroll: true,
            onFinish: () => setTransferring(false),
        });
    };

    return (
        <AdminLayout title="Sinxronizatsiya">
            <PageHeader
                title="Sinxronizatsiya"
                subtitle="Tanlangan xodimlarni tanlangan Hikvision qurilmalariga yuboring."
                actions={(
                    <div className="flex flex-wrap items-center gap-3">
                        <Select
                            allowClear
                            placeholder="Kategoriya"
                            className="min-w-40"
                            value={categoryFilter}
                            options={categories.map((item) => ({ value: item.id, label: item.name }))}
                            onChange={setCategoryFilter}
                        />
                        <Select
                            allowClear
                            placeholder="Bo'lim"
                            className="min-w-40"
                            value={departmentFilter}
                            options={departments.map((item) => ({ value: item.id, label: item.name }))}
                            onChange={setDepartmentFilter}
                        />
                        <Button
                            type="primary"
                            size="large"
                            className="!h-11 !rounded-lg !px-6 !font-bold"
                            loading={transferring}
                            disabled={!selectedDeviceIds.length || !selectedEmployeeIds.length}
                            onClick={submitTransfer}
                        >
                            Ko'chirish
                        </Button>
                    </div>
                )}
            />

            <div className="grid gap-4 xl:grid-cols-2">
                <PanelTable
                    title="Qurilmalar"
                    rows={filteredDevices}
                    selectedIds={selectedDeviceIds}
                    onToggleAll={(checked, ids) => toggleAll(setSelectedDeviceIds, checked, ids)}
                    onToggleOne={(id, checked) => toggleIds(setSelectedDeviceIds, id, checked)}
                    search={deviceSearch}
                    onSearchChange={setDeviceSearch}
                    pageSize={devicePageSize}
                    onPageSizeChange={setDevicePageSize}
                    emptyLabel="Qurilmalar topilmadi"
                    columns={[
                        {
                            key: 'index',
                            label: '№',
                            render: (_row, index) => index + 1,
                        },
                        {
                            key: 'ip',
                            label: 'IP',
                            className: 'font-medium',
                            render: (row) => row.ip_address,
                        },
                        {
                            key: 'name',
                            label: 'Nomi',
                            className: 'font-medium',
                            render: (row) => row.name,
                        },
                    ]}
                />

                <PanelTable
                    title="Xodimlar"
                    rows={filteredEmployees}
                    selectedIds={selectedEmployeeIds}
                    onToggleAll={(checked, ids) => toggleAll(setSelectedEmployeeIds, checked, ids)}
                    onToggleOne={(id, checked) => toggleIds(setSelectedEmployeeIds, id, checked)}
                    search={employeeSearch}
                    onSearchChange={setEmployeeSearch}
                    pageSize={employeePageSize}
                    onPageSizeChange={setEmployeePageSize}
                    emptyLabel="Xodimlar topilmadi"
                    columns={[
                        {
                            key: 'index',
                            label: '№',
                            render: (_row, index) => index + 1,
                        },
                        {
                            key: 'id',
                            label: 'ID',
                            className: 'font-medium text-ink-subtle',
                            render: (row) => row.id,
                        },
                        {
                            key: 'name',
                            label: 'Ism',
                            className: 'font-medium',
                            render: (row) => row.name,
                        },
                        {
                            key: 'devices',
                            label: 'Qurilmalar',
                            className: 'font-semibold text-brand-600',
                            render: (row) => {
                                const total = row.assigned_devices_count ?? 0;
                                const synced = row.synced_devices_count ?? 0;

                                if (total === 0) {
                                    return '—';
                                }

                                return `${synced}/${total}`;
                            },
                        },
                    ]}
                />
            </div>
        </AdminLayout>
    );
}
