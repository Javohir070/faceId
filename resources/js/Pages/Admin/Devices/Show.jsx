import { router } from '@inertiajs/react';
import {
    ApiOutlined,
    LinkOutlined,
    PoweroffOutlined,
    ReloadOutlined,
    SyncOutlined,
    UnlockOutlined,
} from '@ant-design/icons';
import { App as AntApp, Button } from 'antd';
import { useState } from 'react';
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

    submit();
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
            </ShowPage>
        </AdminLayout>
    );
}
