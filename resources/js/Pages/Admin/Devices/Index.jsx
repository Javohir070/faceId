import { router } from '@inertiajs/react';
import {
    CaretDownOutlined,
    CaretUpOutlined,
    MoreOutlined,
} from '@ant-design/icons';
import { App as AntApp, Checkbox, Dropdown } from 'antd';
import { useMemo, useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import { route } from '../../../routes';
import PageHeader from '../../../Components/PageHeader';
import Pagination from '../../../Components/Pagination';
import StatusTag from '../../../Components/StatusTag';
import TableShell from '../../../Components/TableShell';
import useInertiaPoll from '../../../hooks/useInertiaPoll';

const LIVE_POLL_MS = 10000;

function SortableHeader({ children }) {
    return (
        <span className="inline-flex items-center gap-1.5">
            {children}
            <span className="inline-flex flex-col text-[9px] leading-none text-ink-subtle/50">
                <CaretUpOutlined aria-hidden="true" />
                <CaretDownOutlined aria-hidden="true" />
            </span>
        </span>
    );
}

function runDeviceAction(deviceId, action, { modal, onDone }) {
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
        router.post(url, {}, {
            preserveScroll: true,
            onFinish: onDone,
        });
    };

    if (action === 'clear') {
        modal.confirm({
            title: 'Qurilmani tozalash',
            content: 'Barcha xodimlar ushbu qurilmadan o‘chiriladi. Davom etasizmi?',
            okText: 'Ha, tozalash',
            cancelText: 'Bekor qilish',
            okButtonProps: { danger: true },
            onOk: submit,
        });

        return;
    }

    if (action === 'restart') {
        modal.confirm({
            title: 'Qurilmani qayta yuklash',
            content: 'Qurilma qayta yuklanadi. Davom etasizmi?',
            okText: 'Ha, qayta yuklash',
            cancelText: 'Bekor qilish',
            onOk: submit,
        });

        return;
    }

    submit();
}

function DeviceActionsMenu({ device }) {
    const { modal } = AntApp.useApp();

    const items = [
        {
            key: 'disconnect',
            label: 'Uzish',
            disabled: !device.is_connected,
            onClick: () => runDeviceAction(device.id, 'disconnect', { modal }),
        },
        {
            key: 'connect',
            label: 'Ulash',
            disabled: device.is_connected,
            onClick: () => runDeviceAction(device.id, 'connect', { modal }),
        },
        {
            key: 'ping',
            label: 'Tekshirish (ping)',
            onClick: () => runDeviceAction(device.id, 'ping', { modal }),
        },
        {
            key: 'open',
            label: 'Ochish',
            onClick: () => runDeviceAction(device.id, 'open', { modal }),
        },
        {
            key: 'restart',
            label: 'Qayta yuklash',
            onClick: () => runDeviceAction(device.id, 'restart', { modal }),
        },
        {
            key: 'clear',
            label: 'Tozalash',
            onClick: () => runDeviceAction(device.id, 'clear', { modal }),
        },
        {
            key: 'sync',
            label: 'Sinxronlash',
            onClick: () => runDeviceAction(device.id, 'sync', { modal }),
        },
    ];

    return (
        <Dropdown menu={{ items }} trigger={['click']} placement="bottomRight">
            <button
                type="button"
                className="inline-flex h-8 w-8 items-center justify-center rounded-md border border-line bg-surface text-ink-muted transition hover:bg-surface-muted hover:text-ink"
                aria-label="Amallar"
            >
                <MoreOutlined aria-hidden="true" />
            </button>
        </Dropdown>
    );
}

export default function Index({ devices }) {
    const [selectedIds, setSelectedIds] = useState([]);

    useInertiaPoll(['devices'], LIVE_POLL_MS);

    const pageIds = useMemo(
        () => devices.data.map((device) => device.id),
        [devices.data],
    );

    const allSelected = pageIds.length > 0 && pageIds.every((id) => selectedIds.includes(id));
    const someSelected = pageIds.some((id) => selectedIds.includes(id)) && !allSelected;

    const toggleAll = (checked) => {
        setSelectedIds(checked ? pageIds : []);
    };

    const toggleOne = (id, checked) => {
        setSelectedIds((prev) => (
            checked ? [...prev, id] : prev.filter((item) => item !== id)
        ));
    };

    const from = devices.from ?? (devices.data.length ? 1 : 0);
    const to = devices.to ?? devices.data.length;
    const total = devices.total ?? devices.data.length;
    const onlineCount = devices.data.filter((device) => device.is_connected).length;

    return (
        <AdminLayout title="Qurilmalar">
            <PageHeader
                title="Qurilmalar"
                subtitle={`Hikvision Face ID — holat har ${LIVE_POLL_MS / 1000} soniyada yangilanadi. Onlayn: ${onlineCount}/${devices.data.length}`}
                createHref={route('admin.devices.create')}
                createLabel="Qurilma qo'shish"
            />
            <TableShell
                empty={devices.data.length === 0}
                createHref={route('admin.devices.create')}
                createLabel="Qurilma qo'shish"
                footer={devices.data.length > 0 ? (
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
                            links={devices.links}
                            perPage={devices.per_page}
                            total={devices.total}
                        />
                    </div>
                ) : null}
            >
                <table className="data-table devices-table">
                    <thead>
                        <tr>
                            <th className="devices-table__checkbox">
                                <Checkbox
                                    checked={allSelected}
                                    indeterminate={someSelected}
                                    onChange={(e) => toggleAll(e.target.checked)}
                                    aria-label="Hammasini tanlash"
                                />
                            </th>
                            <th className="w-12">№</th>
                            <th><SortableHeader>Nomi</SortableHeader></th>
                            <th><SortableHeader>IP</SortableHeader></th>
                            <th><SortableHeader>Port</SortableHeader></th>
                            <th><SortableHeader>Foydalanuvchi</SortableHeader></th>
                            <th><SortableHeader>Parol</SortableHeader></th>
                            <th><SortableHeader>Turi</SortableHeader></th>
                            <th><SortableHeader>Holat</SortableHeader></th>
                            <th className="w-12" aria-label="Amallar" />
                        </tr>
                    </thead>
                    <tbody>
                        {devices.data.map((device, index) => (
                            <tr key={device.id}>
                                <td className="devices-table__checkbox">
                                    <Checkbox
                                        checked={selectedIds.includes(device.id)}
                                        onChange={(e) => toggleOne(device.id, e.target.checked)}
                                        aria-label={`${device.name} ni tanlash`}
                                    />
                                </td>
                                <td className="font-medium text-ink-subtle">{(from || 1) + index}</td>
                                <td>
                                    <button
                                        type="button"
                                        className="font-medium text-brand-600 transition hover:text-brand-700 hover:underline"
                                        onClick={() => router.visit(route('admin.devices.show', device.id))}
                                    >
                                        {device.name}
                                    </button>
                                </td>
                                <td className="font-medium">{device.ip_address}</td>
                                <td className="font-medium">{device.port}</td>
                                <td className="font-medium">{device.user}</td>
                                <td>
                                    <StatusTag tone={device.has_password ? 'success' : 'neutral'}>
                                        {device.has_password ? 'Sozlangan' : "Yo'q"}
                                    </StatusTag>
                                </td>
                                <td className="font-medium">{device.type_label}</td>
                                <td>
                                    <div>
                                        <StatusTag tone={device.is_connected ? 'success' : 'neutral'}>
                                            {device.is_connected ? 'Ulangan' : 'Ulanmagan'}
                                        </StatusTag>
                                        {device.last_ping_at ? (
                                            <p className="mt-0.5 text-xs text-ink-subtle">
                                                Ping:
                                                {' '}
                                                {device.last_ping_at}
                                            </p>
                                        ) : null}
                                    </div>
                                </td>
                                <td className="text-right">
                                    <DeviceActionsMenu device={device} />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </TableShell>
        </AdminLayout>
    );
}
