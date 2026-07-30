import { router } from '@inertiajs/react';
import {
    ApiOutlined,
    CloudDownloadOutlined,
    LinkOutlined,
    PoweroffOutlined,
    ReloadOutlined,
    SyncOutlined,
    UnlockOutlined,
} from '@ant-design/icons';
import { App as AntApp, Button, Checkbox, Input, Pagination, Select } from 'antd';
import { useEffect, useMemo, useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import { route } from '../../../routes';
import ShowPage from '../../../Components/ShowPage';
import StatusTag from '../../../Components/StatusTag';
import useInertiaPoll from '../../../hooks/useInertiaPoll';

function Field({ label, children }) {
    return (
        <div className="flex flex-col gap-1 border-b border-line/60 py-3 last:border-b-0 sm:flex-row sm:items-baseline sm:gap-4">
            <dt className="w-36 shrink-0 text-sm font-bold text-ink-muted">{label}</dt>
            <dd className="min-w-0 flex-1 break-words text-sm font-semibold text-ink">
                {children ?? '—'}
            </dd>
        </div>
    );
}

function runAction(deviceId, action, { modal, setBusy }) {
    const routes = {
        connect: route('admin.devices.connect', deviceId),
        disconnect: route('admin.devices.disconnect', deviceId),
        ping: route('admin.devices.ping', deviceId),
        open: route('admin.devices.open', deviceId),
        restart: route('admin.devices.restart', deviceId),
        clear: route('admin.devices.clear', deviceId),
        sync: route('admin.devices.sync-employees', deviceId),
        pull: route('admin.devices.pull-employees', deviceId),
    };

    const url = routes[action];
    if (!url) {
        return;
    }

    const submit = () => {
        setBusy(action);
        router.post(url, {}, {
            preserveScroll: true,
            onFinish: () => setBusy(null),
        });
    };

    if (action === 'clear') {
        modal.confirm({
            title: 'Qurilmani tozalash',
            content: 'Barcha xodimlar ushbu qurilmadan o‘chiriladi. Davom etasizmi?',
            okText: 'Ha, tozalash',
            cancelText: 'Bekor qilish',
            okButtonProps: { danger: true },
            onOk: () => new Promise((resolve, reject) => {
                setBusy(action);
                router.post(url, {}, {
                    preserveScroll: true,
                    onSuccess: resolve,
                    onError: reject,
                    onFinish: () => setBusy(null),
                });
            }),
        });
        return;
    }

    if (action === 'restart') {
        modal.confirm({
            title: 'Qurilmani qayta yuklash',
            content: 'Qurilma qayta yuklanadi. Davom etasizmi?',
            okText: 'Ha, qayta yuklash',
            cancelText: 'Bekor qilish',
            onOk: () => new Promise((resolve, reject) => {
                setBusy(action);
                router.post(url, {}, {
                    preserveScroll: true,
                    onSuccess: resolve,
                    onError: reject,
                    onFinish: () => setBusy(null),
                });
            }),
        });
        return;
    }

    if (action === 'sync') {
        modal.confirm({
            title: 'Xodimlarni sinxronlash',
            content: 'Faqat ushbu qurilmaga ulangan xodimlar qayta yuboriladi. Davom etasizmi?',
            okText: 'Ha, sinxronlash',
            cancelText: 'Bekor qilish',
            onOk: () => new Promise((resolve, reject) => {
                setBusy(action);
                router.post(url, {}, {
                    preserveScroll: true,
                    onSuccess: resolve,
                    onError: reject,
                    onFinish: () => setBusy(null),
                });
            }),
        });
        return;
    }

    if (action === 'pull') {
        modal.confirm({
            title: 'Xodimlarni tortish',
            content: 'Qurilmadagi xodimlar bazaga yuklanadi. Bir xil karta (employeeNo) dublikat yaratilmaydi. Davom etasizmi?',
            okText: 'Ha, tortish',
            cancelText: 'Bekor qilish',
            onOk: () => new Promise((resolve, reject) => {
                setBusy(action);
                router.post(url, {}, {
                    preserveScroll: true,
                    onSuccess: resolve,
                    onError: reject,
                    onFinish: () => setBusy(null),
                });
            }),
        });
        return;
    }

    submit();
}

function DeviceEmployeesPanel({ deviceId, employees }) {
    const { modal } = AntApp.useApp();
    const [selectedIds, setSelectedIds] = useState([]);
    const [search, setSearch] = useState('');
    const [pageSize, setPageSize] = useState(25);
    const [page, setPage] = useState(1);
    const [removing, setRemoving] = useState(false);

    const filtered = useMemo(() => {
        const query = search.trim().toLowerCase();

        if (!query) {
            return employees;
        }

        return employees.filter((employee) => (
            employee.name.toLowerCase().includes(query)
            || String(employee.card).toLowerCase().includes(query)
            || (employee.department?.name || '').toLowerCase().includes(query)
        ));
    }, [employees, search]);

    useEffect(() => {
        setPage(1);
    }, [search, pageSize, employees.length]);

    const totalPages = Math.max(1, Math.ceil(filtered.length / pageSize) || 1);
    const currentPage = Math.min(page, totalPages);
    const from = filtered.length === 0 ? 0 : ((currentPage - 1) * pageSize) + 1;
    const to = Math.min(currentPage * pageSize, filtered.length);
    const visible = filtered.slice((currentPage - 1) * pageSize, currentPage * pageSize);

    const allVisibleSelected = visible.length > 0 && visible.every((employee) => selectedIds.includes(employee.id));
    const someVisibleSelected = visible.some((employee) => selectedIds.includes(employee.id)) && !allVisibleSelected;

    const toggleAll = (checked) => {
        const visibleIds = visible.map((employee) => employee.id);
        setSelectedIds((prev) => (
            checked
                ? [...new Set([...prev, ...visibleIds])]
                : prev.filter((id) => !visibleIds.includes(id))
        ));
    };

    const toggleOne = (id, checked) => {
        setSelectedIds((prev) => (
            checked ? [...prev, id] : prev.filter((item) => item !== id)
        ));
    };

    const removeSelected = () => {
        modal.confirm({
            title: 'Qurilmadan o‘chirish',
            content: 'Tanlangan xodimlar ushbu qurilmadan o‘chiriladi. Davom etasizmi?',
            okText: 'Ha, o‘chirish',
            cancelText: 'Bekor qilish',
            okButtonProps: { danger: true },
            onOk: () => new Promise((resolve, reject) => {
                setRemoving(true);
                router.post(route('admin.devices.remove-employees', deviceId), {
                    employee_ids: selectedIds,
                }, {
                    preserveScroll: true,
                    onSuccess: () => {
                        setSelectedIds([]);
                        resolve();
                    },
                    onError: reject,
                    onFinish: () => setRemoving(false),
                });
            }),
        });
    };

    return (
        <div className="rounded-xl border border-line bg-surface shadow-ambient">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-line px-4 py-3">
                <h2 className="text-base font-extrabold text-ink">
                    Ulangan xodimlar
                    <span className="ml-2 text-sm font-semibold text-ink-muted">
                        (
                        {employees.length}
                        )
                    </span>
                </h2>
                <Button
                    danger
                    type="primary"
                    className="!font-bold"
                    disabled={!selectedIds.length}
                    loading={removing}
                    onClick={removeSelected}
                >
                    O'chirish
                </Button>
            </div>

            <div className="flex flex-wrap items-center gap-3 border-b border-line px-4 py-3">
                <div className="flex items-center gap-2 text-sm text-ink-muted">
                    <span>Ko'rsatish</span>
                    <Select
                        value={pageSize}
                        options={[25, 50, 100].map((size) => ({ value: size, label: String(size) }))}
                        onChange={setPageSize}
                        className="min-w-20"
                    />
                    <span>ta yozuv</span>
                </div>
                <div className="ml-auto flex items-center gap-2">
                    <span className="text-sm text-ink-muted">Qidiruv:</span>
                    <Input
                        allowClear
                        placeholder="Ism, karta yoki bo‘lim"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="min-w-[220px]"
                    />
                </div>
            </div>

            <div className="overflow-x-auto">
                <table className="data-table devices-table min-w-full">
                    <thead>
                        <tr>
                            <th className="devices-table__checkbox">
                                <Checkbox
                                    checked={allVisibleSelected}
                                    indeterminate={someVisibleSelected}
                                    onChange={(e) => toggleAll(e.target.checked)}
                                    disabled={!visible.length}
                                />
                            </th>
                            <th className="w-12">№</th>
                            <th>F.I.Sh.</th>
                            <th>Karta</th>
                            <th>Bo&apos;lim</th>
                            <th>Holat</th>
                        </tr>
                    </thead>
                    <tbody>
                        {visible.length === 0 ? (
                            <tr>
                                <td colSpan={6} className="py-10 text-center text-sm text-ink-muted">
                                    Bu qurilmaga hali xodim biriktirilmagan.
                                </td>
                            </tr>
                        ) : visible.map((employee, index) => (
                            <tr key={employee.id}>
                                <td className="devices-table__checkbox">
                                    <Checkbox
                                        checked={selectedIds.includes(employee.id)}
                                        onChange={(e) => toggleOne(employee.id, e.target.checked)}
                                    />
                                </td>
                                <td className="font-medium text-ink-subtle">{from + index}</td>
                                <td>
                                    <button
                                        type="button"
                                        className="font-medium text-brand-600 transition hover:text-brand-700 hover:underline"
                                        onClick={() => router.visit(route('admin.employees.show', employee.id))}
                                    >
                                        {employee.name}
                                    </button>
                                </td>
                                <td className="font-medium">{employee.card}</td>
                                <td className="font-medium">{employee.department?.name || '—'}</td>
                                <td>
                                    {employee.sync_error ? (
                                        <StatusTag tone="danger" title={employee.sync_error}>
                                            Xato
                                        </StatusTag>
                                    ) : employee.is_synced ? (
                                        <StatusTag tone="success">
                                            Sinxron
                                            {employee.synced_at ? ` · ${employee.synced_at}` : ''}
                                        </StatusTag>
                                    ) : (
                                        <StatusTag tone="warning">Sinxronlanmagan</StatusTag>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <div className="flex flex-wrap items-center justify-between gap-3 border-t border-line px-4 py-3">
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

function DeviceQuickActions({ device }) {
    const { modal } = AntApp.useApp();
    const [busy, setBusy] = useState(null);

    const run = (action) => runAction(device.id, action, { modal, setBusy });

    return (
        <div className="flex flex-wrap gap-2">
            <Button
                type="primary"
                icon={<ApiOutlined />}
                loading={busy === 'ping'}
                disabled={Boolean(busy) && busy !== 'ping'}
                onClick={() => run('ping')}
            >
                Tekshirish
            </Button>

            {device.is_connected ? (
                <Button
                    icon={<PoweroffOutlined />}
                    loading={busy === 'disconnect'}
                    disabled={Boolean(busy) && busy !== 'disconnect'}
                    onClick={() => run('disconnect')}
                >
                    Uzish
                </Button>
            ) : (
                <Button
                    icon={<LinkOutlined />}
                    loading={busy === 'connect'}
                    disabled={Boolean(busy) && busy !== 'connect'}
                    onClick={() => run('connect')}
                >
                    Ulash
                </Button>
            )}

            <Button
                icon={<SyncOutlined />}
                loading={busy === 'sync'}
                disabled={Boolean(busy) && busy !== 'sync'}
                onClick={() => run('sync')}
            >
                Sinxronlash
            </Button>

            <Button
                icon={<CloudDownloadOutlined />}
                loading={busy === 'pull'}
                disabled={Boolean(busy) && busy !== 'pull'}
                onClick={() => run('pull')}
            >
                Xodimlarni tortish
            </Button>

            <Button
                icon={<UnlockOutlined />}
                loading={busy === 'open'}
                disabled={Boolean(busy) && busy !== 'open'}
                onClick={() => run('open')}
            >
                Eshikni ochish
            </Button>

            <Button
                icon={<ReloadOutlined />}
                loading={busy === 'restart'}
                disabled={Boolean(busy) && busy !== 'restart'}
                onClick={() => run('restart')}
            >
                Qayta yuklash
            </Button>

            <Button
                danger
                loading={busy === 'clear'}
                disabled={Boolean(busy) && busy !== 'clear'}
                onClick={() => run('clear')}
            >
                Tozalash
            </Button>
        </div>
    );
}

export default function Show({ device }) {
    useInertiaPoll(['device'], 10000);

    const online = Boolean(device.is_connected);

    return (
        <AdminLayout title="Qurilma">
            <ShowPage
                backHref={route('admin.devices.index')}
                backLabel="Qurilmalar ro‘yxati"
                title={device.name}
                subtitle={device.description || `${device.ip_address}:${device.port}`}
                editHref={route('admin.devices.edit', device.id)}
                deleteHref={route('admin.devices.destroy', device.id)}
            >
                <div
                    className={`rounded-lg border px-4 py-4 sm:px-5 ${
                        online
                            ? 'border-brand-600/30 bg-surface'
                            : 'border-line bg-surface-muted'
                    }`}
                >
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div className="min-w-0">
                            <div className="flex flex-wrap items-center gap-2">
                                <StatusTag tone={online ? 'success' : 'neutral'}>
                                    <span
                                        className={`mr-1.5 inline-flex h-2 w-2 rounded-full ${
                                            online ? 'bg-positive' : 'bg-ink-subtle'
                                        }`}
                                        aria-hidden="true"
                                    />
                                    {online ? 'Ulangan' : 'Ulanmagan'}
                                </StatusTag>
                                <StatusTag tone={device.type === 2 ? 'warning' : 'info'}>
                                    {device.type_label || 'Tur noma’lum'}
                                </StatusTag>
                                {device.category?.name ? (
                                    <StatusTag tone="neutral">{device.category.name}</StatusTag>
                                ) : null}
                            </div>
                            <p className="mt-2 text-sm text-ink-muted">
                                Oxirgi tekshiruv:
                                {' '}
                                <span className="font-semibold text-ink">
                                    {device.last_ping_at || 'hali tekshirilmagan'}
                                </span>
                                {device.last_connected_at ? (
                                    <>
                                        {' · '}
                                        Ulangan:
                                        {' '}
                                        <span className="font-semibold text-ink">
                                            {device.last_connected_at}
                                        </span>
                                    </>
                                ) : null}
                            </p>
                        </div>
                    </div>

                    <div className="mt-4 border-t border-line/70 pt-4">
                        <p className="mb-2 text-xs font-extrabold uppercase tracking-wide text-ink-muted">
                            Tezkor amallar
                        </p>
                        <DeviceQuickActions device={device} />
                    </div>
                </div>

                <div className="grid gap-8 lg:grid-cols-2">
                    <section>
                        <h2 className="mb-1 text-base font-extrabold text-ink">Qurilma haqida</h2>
                        <p className="mb-2 text-sm text-ink-muted">Asosiy identifikatsiya ma’lumotlari</p>
                        <dl>
                            <Field label="Nomi">{device.name}</Field>
                            <Field label="Tavsif">{device.description || '—'}</Field>
                            <Field label="Turi">{device.type_label || '—'}</Field>
                            <Field label="Kategoriya">{device.category?.name || '—'}</Field>
                            <Field label="Egasi">{device.owner?.name || '—'}</Field>
                            <Field label="Avtoulanish">
                                {device.autoconnect ? 'Yoqilgan' : 'O‘chirilgan'}
                            </Field>
                        </dl>
                    </section>

                    <section>
                        <h2 className="mb-1 text-base font-extrabold text-ink">Ulanish</h2>
                        <p className="mb-2 text-sm text-ink-muted">Tarmoq va kirish ma’lumotlari</p>
                        <dl>
                            <Field label="IP manzil">{device.ip_address}</Field>
                            <Field label="Port">{device.port}</Field>
                            <Field label="Protokol">{device.use_https ? 'HTTPS' : 'HTTP'}</Field>
                            <Field label="Manzil">
                                <span className="font-mono text-xs sm:text-sm">
                                    {device.isapi_base_url || '—'}
                                </span>
                            </Field>
                            <Field label="Login">{device.user}</Field>
                            <Field label="Parol">
                                <StatusTag tone={device.has_password ? 'success' : 'neutral'}>
                                    {device.has_password ? 'Sozlangan' : 'Yo‘q'}
                                </StatusTag>
                            </Field>
                            <Field label="Webhook">
                                <StatusTag tone={device.webhook_configured ? 'success' : 'neutral'}>
                                    {device.webhook_configured ? 'Sozlangan' : 'Sozlanmagan'}
                                </StatusTag>
                            </Field>
                        </dl>
                    </section>
                </div>

                <div className="border-t border-line pt-4">
                    <h2 className="mb-3 text-base font-extrabold text-ink">Vaqtlar</h2>
                    <div className="grid gap-3 sm:grid-cols-3">
                        <div>
                            <p className="text-xs font-bold text-ink-muted">Yaratilgan</p>
                            <p className="mt-1 text-sm font-semibold text-ink">{device.created_at || '—'}</p>
                        </div>
                        <div>
                            <p className="text-xs font-bold text-ink-muted">Yangilangan</p>
                            <p className="mt-1 text-sm font-semibold text-ink">{device.updated_at || '—'}</p>
                        </div>
                        <div>
                            <p className="text-xs font-bold text-ink-muted">ID</p>
                            <p className="mt-1 text-sm font-semibold text-ink">
                                #
                                {device.id}
                            </p>
                        </div>
                    </div>
                </div>

                <DeviceEmployeesPanel deviceId={device.id} employees={device.employees ?? []} />
            </ShowPage>
        </AdminLayout>
    );
}
