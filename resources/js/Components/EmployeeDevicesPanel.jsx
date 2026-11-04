import { router, usePage } from '@inertiajs/react';
import { App as AntApp, Button, Checkbox, Input, Select } from 'antd';
import { useMemo, useState } from 'react';
import { route } from '../routes';
import StatusTag from './StatusTag';

export default function EmployeeDevicesPanel({ employeeId, devices }) {
    const canRemove = Boolean(usePage().props.can?.['employees.delete']);
    const { modal } = AntApp.useApp();
    const [selectedIds, setSelectedIds] = useState([]);
    const [search, setSearch] = useState('');
    const [pageSize, setPageSize] = useState(50);
    const [removing, setRemoving] = useState(false);

    const filtered = useMemo(() => {
        const query = search.trim().toLowerCase();

        if (!query) {
            return devices;
        }

        return devices.filter((device) => (
            device.name.toLowerCase().includes(query)
            || device.ip_address.toLowerCase().includes(query)
        ));
    }, [devices, search]);

    const visible = filtered.slice(0, pageSize);
    const allVisibleSelected = visible.length > 0 && visible.every((device) => selectedIds.includes(device.id));
    const someVisibleSelected = visible.some((device) => selectedIds.includes(device.id)) && !allVisibleSelected;

    const toggleAll = (checked) => {
        const visibleIds = visible.map((device) => device.id);
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
            title: 'Qurilmalardan o‘chirish',
            content: 'Tanlangan qurilmalardan xodim ma’lumotlari o‘chiriladi. Davom etasizmi?',
            okText: 'Ha, o‘chirish',
            cancelText: 'Bekor qilish',
            okButtonProps: { danger: true },
            onOk: () => new Promise((resolve, reject) => {
                setRemoving(true);
                router.post(route('admin.employees.remove-devices', employeeId), {
                    device_ids: selectedIds,
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
                <h2 className="text-base font-extrabold text-ink">Qurilmalar</h2>
                {canRemove ? (
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
                ) : null}
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
                        placeholder="Nima qidiryapsiz?"
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
                            {canRemove ? (
                                <th className="devices-table__checkbox">
                                    <Checkbox
                                        checked={allVisibleSelected}
                                        indeterminate={someVisibleSelected}
                                        onChange={(e) => toggleAll(e.target.checked)}
                                        disabled={!visible.length}
                                    />
                                </th>
                            ) : null}
                            <th className="w-12">№</th>
                            <th>Nomi</th>
                        </tr>
                    </thead>
                    <tbody>
                        {visible.length === 0 ? (
                            <tr>
                                <td colSpan={canRemove ? 3 : 2} className="py-10 text-center text-sm text-ink-muted">
                                    Xodim hali hech qaysi qurilmaga biriktirilmagan.
                                </td>
                            </tr>
                        ) : visible.map((device, index) => (
                            <tr key={device.id}>
                                {canRemove ? (
                                    <td className="devices-table__checkbox">
                                        <Checkbox
                                            checked={selectedIds.includes(device.id)}
                                            onChange={(e) => toggleOne(device.id, e.target.checked)}
                                        />
                                    </td>
                                ) : null}
                                <td className="font-medium text-ink-subtle">{index + 1}</td>
                                <td>
                                    <button
                                        type="button"
                                        className="font-medium text-brand-600 transition hover:text-brand-700 hover:underline"
                                        onClick={() => router.visit(route('admin.devices.show', device.id))}
                                    >
                                        {device.name}
                                    </button>
                                    {device.sync_error ? (
                                        <>
                                            <StatusTag tone="danger" className="ml-2">Xato</StatusTag>
                                            <span className="text-xs font-medium text-ink-muted">
                                                {device.sync_error}
                                            </span>
                                        </>
                                    ) : !device.is_synced ? (
                                        <StatusTag tone="warning" className="ml-2">Sinxronlanmagan</StatusTag>
                                    ) : null}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <div className="border-t border-line px-4 py-3 text-sm text-ink-muted">
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
