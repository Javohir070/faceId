import { router } from '@inertiajs/react';
import { App as AntApp, Button, Checkbox, Input, Modal, Pagination, Progress, Select } from 'antd';
import { useEffect, useMemo, useRef, useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import { route } from '../../../routes';
import PageHeader from '../../../Components/PageHeader';
import StatusTag from '../../../Components/StatusTag';
import useInertiaPoll from '../../../hooks/useInertiaPoll';

const POLL_MS = 5000;

function getXsrfToken() {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}

function formatSyncLogLine(result) {
    return `IP: ${result.device_ip}; ID: ${result.employee_card || result.employee_id}; Error: ${result.message}`;
}

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
    isSelectable = () => true,
}) {
    const [page, setPage] = useState(1);

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

    useEffect(() => {
        setPage(1);
    }, [search, pageSize, rows.length]);

    const totalPages = Math.max(1, Math.ceil(filtered.length / pageSize) || 1);
    const currentPage = Math.min(page, totalPages);
    const from = filtered.length === 0 ? 0 : ((currentPage - 1) * pageSize) + 1;
    const to = Math.min(currentPage * pageSize, filtered.length);
    const visible = filtered.slice((currentPage - 1) * pageSize, currentPage * pageSize);

    const selectableVisibleIds = visible.filter((row) => isSelectable(row)).map((row) => row.id);
    const allVisibleSelected = selectableVisibleIds.length > 0
        && selectableVisibleIds.every((id) => selectedIds.includes(id));
    const someVisibleSelected = selectableVisibleIds.some((id) => selectedIds.includes(id))
        && !allVisibleSelected;

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
                                    disabled={selectableVisibleIds.length === 0}
                                    onChange={(e) => onToggleAll(e.target.checked, selectableVisibleIds)}
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
                        ) : visible.map((row, index) => {
                            const selectable = isSelectable(row);

                            return (
                                <tr
                                    key={row.id}
                                    className={selectedIds.includes(row.id) ? 'bg-brand-50/60' : undefined}
                                >
                                    <td className="devices-table__checkbox">
                                        <Checkbox
                                            checked={selectedIds.includes(row.id)}
                                            disabled={!selectable}
                                            onChange={(e) => onToggleOne(row.id, e.target.checked)}
                                        />
                                    </td>
                                    {columns.map((column) => (
                                        <td key={column.key} className={column.className}>
                                            {column.render(row, from + index - 1)}
                                        </td>
                                    ))}
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>

            <div className="mt-auto flex flex-wrap items-center justify-between gap-3 border-t border-line px-4 py-3">
                <p className="text-sm text-ink-muted">
                    Tanlangan:
                    {' '}
                    <span className="font-semibold text-ink">{selectedIds.length}</span>
                    {' · '}
                    {filtered.length === 0 ? '0' : `${from}–${to}`}
                    {' / '}
                    {filtered.length}
                </p>
                {filtered.length > pageSize ? (
                    <Pagination
                        size="small"
                        current={currentPage}
                        pageSize={pageSize}
                        total={filtered.length}
                        showSizeChanger={false}
                        onChange={setPage}
                    />
                ) : null}
            </div>
        </div>
    );
}

function SyncProgressModal({
    open,
    current,
    total,
    logs,
    done,
    cancelled,
    onClose,
    onCancel,
}) {
    const percent = total > 0 ? Math.round((current / total) * 100) : 0;
    const logText = logs.join('\n');

    return (
        <Modal
            open={open}
            title="Sinxronizatsiya jarayoni"
            closable={done}
            mask={{ closable: done }}
            keyboard={done}
            onCancel={onClose}
            footer={done ? (
                <Button type="primary" onClick={onClose}>
                    Yopish
                </Button>
            ) : (
                <Button danger onClick={onCancel}>
                    Bekor qilish
                </Button>
            )}
            width={640}
            destroyOnHidden
        >
            <div className="space-y-4">
                <div>
                    <p className="mb-2 text-sm font-semibold text-ink">
                        Progress
                        {' '}
                        {current}
                        /
                        {total}
                        {' '}
                        (
                        {percent}
                        %)
                        {cancelled && !done ? ' — to‘xtatilmoqda...' : null}
                        {done && cancelled ? ' — bekor qilindi' : null}
                        {done && !cancelled ? ' — yakunlandi' : null}
                    </p>
                    <Progress
                        percent={percent}
                        status={done ? (cancelled || logs.length > 0 ? 'exception' : 'success') : 'active'}
                    />
                </div>

                <div>
                    <p className="mb-2 text-sm font-semibold text-ink">
                        Xatolar / jurnal
                        {logs.length > 0 ? ` (${logs.length})` : ''}
                    </p>
                    <textarea
                        readOnly
                        value={logText || (done ? 'Xato yo‘q.' : 'Jarayon ketmoqda...')}
                        className="h-52 w-full resize-y rounded-lg border border-line bg-surface-muted px-3 py-2 font-mono text-xs leading-5 text-ink"
                    />
                </div>
            </div>
        </Modal>
    );
}

export default function Index({ devices = [], employees = [], categories = [], departments = [] }) {
    const { modal, message } = AntApp.useApp();
    const [selectedDeviceIds, setSelectedDeviceIds] = useState([]);
    const [selectedEmployeeIds, setSelectedEmployeeIds] = useState([]);
    const [deviceSearch, setDeviceSearch] = useState('');
    const [employeeSearch, setEmployeeSearch] = useState('');
    const [devicePageSize, setDevicePageSize] = useState(50);
    const [employeePageSize, setEmployeePageSize] = useState(50);
    const [categoryFilter, setCategoryFilter] = useState(null);
    const [departmentFilter, setDepartmentFilter] = useState(null);
    const [transferring, setTransferring] = useState(false);
    const [progressOpen, setProgressOpen] = useState(false);
    const [progressCurrent, setProgressCurrent] = useState(0);
    const [progressTotal, setProgressTotal] = useState(0);
    const [progressLogs, setProgressLogs] = useState([]);
    const [progressDone, setProgressDone] = useState(false);
    const [progressCancelled, setProgressCancelled] = useState(false);
    const abortRef = useRef(false);

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

    const runTransferJobs = async () => {
        const jobs = [];

        selectedEmployeeIds.forEach((employeeId) => {
            selectedDeviceIds.forEach((deviceId) => {
                jobs.push({ deviceId, employeeId });
            });
        });

        abortRef.current = false;
        setTransferring(true);
        setProgressOpen(true);
        setProgressCurrent(0);
        setProgressTotal(jobs.length);
        setProgressLogs([]);
        setProgressDone(false);
        setProgressCancelled(false);

        let success = 0;
        let failed = 0;
        let skipped = 0;

        for (let i = 0; i < jobs.length; i += 1) {
            if (abortRef.current) {
                setProgressCancelled(true);
                break;
            }

            const job = jobs[i];

            try {
                const response = await fetch(route('admin.sync.transfer-one'), {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-XSRF-TOKEN': getXsrfToken(),
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        device_id: job.deviceId,
                        employee_id: job.employeeId,
                    }),
                });

                const result = await response.json().catch(() => null);

                if (!response.ok || !result) {
                    failed += 1;
                    const device = devices.find((item) => item.id === job.deviceId);
                    const employee = employees.find((item) => item.id === job.employeeId);
                    setProgressLogs((prev) => [
                        ...prev,
                        `IP: ${device?.ip_address || '—'}; ID: ${employee?.card || job.employeeId}; Error: HTTP ${response.status}`,
                    ]);
                } else if (result.status === 'success') {
                    success += 1;
                } else if (result.status === 'skipped') {
                    skipped += 1;
                    setProgressLogs((prev) => [...prev, formatSyncLogLine(result)]);
                } else {
                    failed += 1;
                    setProgressLogs((prev) => [...prev, formatSyncLogLine(result)]);
                }
            } catch (error) {
                failed += 1;
                const device = devices.find((item) => item.id === job.deviceId);
                const employee = employees.find((item) => item.id === job.employeeId);
                setProgressLogs((prev) => [
                    ...prev,
                    `IP: ${device?.ip_address || '—'}; ID: ${employee?.card || job.employeeId}; Error: ${error.message || 'Tarmoq xatosi'}`,
                ]);
            }

            setProgressCurrent(i + 1);
        }

        setProgressDone(true);
        setTransferring(false);
        setSelectedDeviceIds([]);
        setSelectedEmployeeIds([]);

        router.reload({ only: ['devices', 'employees'] });

        if (abortRef.current) {
            message.warning(`Bekor qilindi: ${success} muvaffaqiyatli, ${failed} xato, ${skipped} o‘tkazildi.`);
        } else if (failed === 0 && skipped === 0) {
            message.success(`Sinxronizatsiya yakunlandi: ${success} muvaffaqiyatli.`);
        } else {
            message.error(`Sinxronizatsiya yakunlandi: ${success} muvaffaqiyatli, ${failed} xato, ${skipped} o‘tkazildi.`);
        }
    };

    const submitTransfer = () => {
        const start = () => {
            runTransferJobs();
        };

        if (offlineSelectedCount > 0) {
            modal.confirm({
                title: 'Ulanmagan qurilmalar bor',
                content: `${offlineSelectedCount} ta tanlangan qurilma hozir ulanmagan. Ular jurnalda "device disconnected" deb yoziladi. Davom etasizmi?`,
                okText: 'Ha, yuborish',
                cancelText: 'Bekor qilish',
                onOk: () => new Promise((resolve) => {
                    start();
                    resolve();
                }),
            });
            return;
        }

        start();
    };

    const canTransfer = selectedDeviceIds.length > 0 && selectedEmployeeIds.length > 0 && !transferring;

    return (
        <AdminLayout title="Sinxronizatsiya">
            <PageHeader
                title="Sinxronizatsiya"
                subtitle={`Tanlangan xodimlar darhol Hikvision qurilmalariga yuboriladi. Yuz rasmi yo‘q xodimlar yuborilmaydi. Holat har ${POLL_MS / 1000} soniyada yangilanadi.`}
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

            <SyncProgressModal
                open={progressOpen}
                current={progressCurrent}
                total={progressTotal}
                logs={progressLogs}
                done={progressDone}
                cancelled={progressCancelled}
                onCancel={() => {
                    abortRef.current = true;
                    setProgressCancelled(true);
                }}
                onClose={() => setProgressOpen(false)}
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
                        <StatusTag tone="warning">
                            {offlineSelectedCount}
                            {' '}
                            ulanmagan qurilma
                        </StatusTag>
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
                        <StatusTag tone="info">
                            {filteredDevices.filter((d) => d.is_online).length}
                            /
                            {filteredDevices.length}
                            {' '}
                            online
                        </StatusTag>
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
                                <StatusTag tone={row.is_online ? 'success' : 'neutral'}>
                                    {row.is_online ? 'Ulangan' : 'Ulanmagan'}
                                </StatusTag>
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
                    subtitle="Faqat yuz rasmi bor xodimlar yuboriladi"
                    rows={filteredEmployees}
                    selectedIds={selectedEmployeeIds}
                    onToggleAll={(checked, ids) => toggleAll(setSelectedEmployeeIds, checked, ids)}
                    onToggleOne={(id, checked) => toggleIds(setSelectedEmployeeIds, id, checked)}
                    isSelectable={(row) => Boolean(row.has_face)}
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
                                <StatusTag tone={row.has_face ? 'success' : 'neutral'}>
                                    {row.has_face ? 'Bor' : 'Yo‘q'}
                                </StatusTag>
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
