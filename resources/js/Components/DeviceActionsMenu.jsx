import { router, usePage } from '@inertiajs/react';
import { LoadingOutlined, MoreOutlined } from '@ant-design/icons';
import { App as AntApp, Dropdown } from 'antd';
import { useState } from 'react';
import { route } from '../routes';

function postDeviceAction(url, { setBusy, action, resolve, reject }) {
    setBusy(action);
    router.post(url, {}, {
        preserveScroll: true,
        onSuccess: resolve,
        onError: reject,
        onFinish: () => setBusy(null),
    });
}

function runDeviceAction(deviceId, action, { modal, setBusy, busy }) {
    if (busy) {
        return;
    }

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

    const confirmConfigs = {
        clear: {
            title: 'Qurilmani tozalash',
            content: 'Barcha xodimlar ushbu qurilmadan o‘chiriladi. Davom etasizmi?',
            okText: 'Ha, tozalash',
            okButtonProps: { danger: true },
        },
        restart: {
            title: 'Qurilmani qayta yuklash',
            content: 'Qurilma qayta yuklanadi. Davom etasizmi?',
            okText: 'Ha, qayta yuklash',
        },
        sync: {
            title: 'Xodimlarni sinxronlash',
            content: 'Faqat ushbu qurilmaga ulangan xodimlar qayta yuboriladi. Davom etasizmi?',
            okText: 'Ha, sinxronlash',
        },
    };

    const confirm = confirmConfigs[action];

    if (confirm) {
        modal.confirm({
            ...confirm,
            cancelText: 'Bekor qilish',
            onOk: () => new Promise((resolve, reject) => {
                postDeviceAction(url, {
                    setBusy, action, resolve, reject,
                });
            }),
        });

        return;
    }

    postDeviceAction(url, {
        setBusy,
        action,
        resolve: () => {},
        reject: () => {},
    });
}

export default function DeviceActionsMenu({ device }) {
    const { can = {} } = usePage().props;
    const canClear = Boolean(can['devices.delete']);
    const canOperate = Boolean(can['devices.edit']);
    const { modal } = AntApp.useApp();
    const [busy, setBusy] = useState(null);

    const run = (action) => runDeviceAction(device.id, action, { modal, setBusy, busy });

    // Faqat ko‘rish huquqida holatni tekshirish qoladi: qolgan amallar qurilmani o‘zgartiradi.
    const operations = canOperate ? [
        {
            key: 'disconnect',
            label: 'Uzish',
            disabled: !device.is_online || Boolean(busy),
            onClick: () => run('disconnect'),
        },
        {
            key: 'connect',
            label: 'Ulash',
            disabled: device.is_online || Boolean(busy),
            onClick: () => run('connect'),
        },
        {
            key: 'open',
            label: busy === 'open' ? 'Yuborilmoqda…' : 'Ochish',
            disabled: Boolean(busy),
            onClick: () => run('open'),
        },
        {
            key: 'restart',
            label: busy === 'restart' ? 'Yuklanmoqda…' : 'Qayta yuklash',
            disabled: Boolean(busy),
            onClick: () => run('restart'),
        },
        ...(canClear ? [{
            key: 'clear',
            label: busy === 'clear' ? 'Tozalanmoqda…' : 'Tozalash',
            disabled: Boolean(busy),
            danger: true,
            onClick: () => run('clear'),
        }] : []),
        {
            key: 'sync',
            label: busy === 'sync' ? 'Sinxronlanmoqda…' : 'Sinxronlash',
            disabled: Boolean(busy),
            onClick: () => run('sync'),
        },
    ] : [];

    const items = [
        {
            key: 'ping',
            label: busy === 'ping' ? 'Tekshirilmoqda…' : 'Tekshirish (ping)',
            disabled: Boolean(busy),
            onClick: () => run('ping'),
        },
        ...operations,
    ];

    return (
        <Dropdown menu={{ items }} trigger={['click']} placement="bottomRight" disabled={Boolean(busy)}>
            <button
                type="button"
                className="inline-flex h-8 w-8 items-center justify-center rounded-md border border-line bg-surface text-ink-muted transition hover:bg-surface-muted hover:text-ink disabled:cursor-wait disabled:opacity-60"
                aria-label="Amallar"
                disabled={Boolean(busy)}
                aria-busy={Boolean(busy)}
            >
                {busy ? <LoadingOutlined aria-hidden="true" /> : <MoreOutlined aria-hidden="true" />}
            </button>
        </Dropdown>
    );
}
