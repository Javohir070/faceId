import { router } from '@inertiajs/react';
import { App as AntApp, Button, Checkbox, Input, Select, Tag } from 'antd';
import { useMemo, useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import { route } from '../../../routes';
import PageHeader from '../../../Components/PageHeader';
import useInertiaPoll from '../../../hooks/useInertiaPoll';

const POLL_MS = 5000;

function PanelTable({
    title,
    subtitle,
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
    headerExtra,
}) {
    const filtered = useMemo(() => {
        const query = search.trim().toLowerCase();

        if (!query) {
            return rows;
        }

        return rows.filter((row) => Object.values(row).some((value) => {
            if (value === null || value === undefined || typeof value === 'object') {
                if (value && typeof value === 'object' && 'name' in value) {
                    return String(value.name).toLowerCase().includes(query);
                }

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
            <div className="flex flex-wrap items-start justify-between gap-3 border-b border-line px-4 py-3">
                <div>
                    <h2 className="text-base font-extrabold text-ink">{title}</h2>
                    {subtitle ? (
                        <p className="mt-0.5 text-sm text-ink-muted">{subtitle}</p>
                    ) : null}
                </div>
                {headerExtra}
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
                    placeholder="Qidiruv..."
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
                            <tr
                                key={row.id}
                                className={selectedIds.includes(row.id) ? 'bg-brand-50/60' : undefined}
                            >
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
                Tanlangan:
                {' '}
                <span className="font-semibold text-ink">{selectedIds.length}</span>
                {' · '}
                1–
                {visible.length}
                {' / '}
                {filtered.length}
            </div>
        </div>
    );
}

export default function Index({ devices = [], employees = [], categories = [], departments = [] }) {
    const { modal } = AntApp.useApp();
    const [selectedDeviceIds, setSelectedDeviceIds] = useState([]);
    const [selectedEmployeeIds, setSelectedEmployeeIds] = useState([]);
    const [deviceSearch, setDeviceSearch] = useState('');
    const [employeeSearch, setEmployeeSearch] = useState('');
    const [devicePageSize, setDevicePageSize] = useState(50);
    const [employeePageSize, setEmployeePageSize] = useState(50);
    const [categoryFilter, setCategoryFilter] = useState(null);
    const [departmentFilter, setDepartmentFilter] = useState(null);
    const [transferring, setTransferring] = useState(false);

    useInertiaPoll(['devices', 'employees'], POLL_MS);

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

    const selectedDevices = useMemo(
        () => devices.filter((device) => selectedDeviceIds.includes(device.id)),
        [devices, selectedDeviceIds],
    );

    const offlineSelectedCount = selectedDevices.filter((device) => !device.is_online).length;
    const totalOps = selectedDeviceIds.length * selectedEmployeeIds.length;

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
        const doTransfer = () => {
            setTransferring(true);
            router.post(route('admin.sync.transfer'), {
                device_ids: selectedDeviceIds,
                employee_ids: selectedEmployeeIds,
            }, {
                preserveScroll: true,
                onSuccess: () => {
                    setSelectedDeviceIds([]);
                    setSelectedEmployeeIds([]);
                },
                onFinish: () => setTransferring(false),
            });
        };

        if (offlineSelectedCount > 0) {
            modal.confirm({
                title: 'Ulanmagan qurilmalar bor',
                content: `${offlineSelectedCount} ta tanlangan qurilma hozir ulanmagan. Yuborish baribir uriniladi, lekin xato bo‘lishi mumkin. Davom etasizmi?`,
                okText: 'Ha, yuborish',
                cancelText: 'Bekor qilish',
                onOk: () => new Promise((resolve) => {
                    doTransfer();
                    resolve();
                }),
            });
            return;
        }

        doTransfer();
    };

    const canTransfer = selectedDeviceIds.length > 0 && selectedEmployeeIds.length > 0 && !transferring;

    return (
        <AdminLayout title="Sinxronizatsiya">
            <PageHeader
                title="Sinxronizatsiya"
                subtitle={`Tanlangan xodimlar darhol Hikvision qurilmalariga yuboriladi. Holat har ${POLL_MS / 1000} soniyada yangilanadi.`}
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
                            disabled={!canTransfer}
                            onClick={submitTransfer}
                        >
                            {transferring
                                ? 'Yuborilmoqda...'
                                : `Yuborish${totalOps > 0 ? ` (${totalOps})` : ''}`}
                        </Button>
                    </div>
                )}
            />

            {(selectedDeviceIds.length > 0 || selectedEmployeeIds.length > 0) && (
                <div className="mb-4 flex flex-wrap items-center gap-3 rounded-lg border border-line bg-surface-muted px-4 py-3 text-sm">
                    <span className="font-semibold text-ink">
                        {selectedEmployeeIds.length}
                        {' '}
                        xodim →
                        {' '}
                        {selectedDeviceIds.length}
                        {' '}
                        qurilma
                    </span>
                    <span className="text-ink-muted">
                        (
                        {totalOps}
                        {' '}
                        ta yuborish)
                    </span>
                    {offlineSelectedCount > 0 ? (
                        <Tag color="warning">
                            {offlineSelectedCount}
                            {' '}
                            ulanmagan qurilma
                        </Tag>
                    ) : null}
                </div>
            )}

            <div className="grid gap-4 xl:grid-cols-2">
                <PanelTable
                    title="Qurilmalar"
                    subtitle="Qayerga yuboriladi"
                    rows={filteredDevices}
                    selectedIds={selectedDeviceIds}
                    onToggleAll={(checked, ids) => toggleAll(setSelectedDeviceIds, checked, ids)}
                    onToggleOne={(id, checked) => toggleIds(setSelectedDeviceIds, id, checked)}
                    search={deviceSearch}
                    onSearchChange={setDeviceSearch}
                    pageSize={devicePageSize}
                    onPageSizeChange={setDevicePageSize}
                    emptyLabel="Qurilmalar topilmadi"
                    headerExtra={(
                        <Tag color="processing">
                            {filteredDevices.filter((d) => d.is_online).length}
                            /
                            {filteredDevices.length}
                            {' '}
                            online
                        </Tag>
                    )}
                    columns={[
                        {
                            key: 'index',
                            label: '№',
                            render: (_row, index) => index + 1,
                        },
                        {
                            key: 'status',
                            label: 'Holat',
                            render: (row) => (
                                <Tag color={row.is_online ? 'success' : 'default'}>
                                    {row.is_online ? 'Ulangan' : 'Ulanmagan'}
                                </Tag>
                            ),
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
                            render: (row) => (
                                <div>
                                    <div>{row.name}</div>
                                    <div className="text-xs font-medium text-ink-muted">
                                        {row.type_label || '—'}
                                        {row.category?.name ? ` · ${row.category.name}` : ''}
                                    </div>
                                </div>
                            ),
                        },
                        {
                            key: 'synced',
                            label: 'Sync',
                            className: 'font-semibold text-brand-600',
                            render: (row) => {
                                const total = row.assigned_employees_count ?? 0;
                                const synced = row.synced_employees_count ?? 0;

                                if (total === 0) {
                                    return '—';
                                }

                                return `${synced}/${total}`;
                            },
                        },
                    ]}
                />

                <PanelTable
                    title="Xodimlar"
                    subtitle="Kimlar yuboriladi"
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
                            key: 'name',
                            label: 'Xodim',
                            className: 'font-medium',
                            render: (row) => (
                                <div>
                                    <div>{row.name}</div>
                                    <div className="text-xs font-medium text-ink-muted">
                                        {row.card || 'Karta yo‘q'}
                                        {row.department?.name ? ` · ${row.department.name}` : ''}
                                    </div>
                                </div>
                            ),
                        },
                        {
                            key: 'face',
                            label: 'Yuz',
                            render: (row) => (
                                <Tag color={row.has_face ? 'success' : 'default'}>
                                    {row.has_face ? 'Bor' : 'Yo‘q'}
                                </Tag>
                            ),
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
