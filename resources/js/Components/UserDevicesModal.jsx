import { router } from '@inertiajs/react';
import { SearchOutlined } from '@ant-design/icons';
import { Checkbox, Input, Modal } from 'antd';
import { useEffect, useMemo, useState } from 'react';
import { route } from '../routes';

/**
 * Foydalanuvchiga Face ID qurilmalarini bog‘lash oynasi. Foydalanuvchi o‘zi qo‘shgan qurilmalar
 * har doim ko‘rinadi, shuning uchun ular belgilangan va o‘chirib bo‘lmaydigan holda turadi.
 */
export default function UserDevicesModal({ user, devices = [], open, onClose }) {
    const [selected, setSelected] = useState([]);
    const [search, setSearch] = useState('');
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (open && user) {
            setSelected(user.device_ids ?? []);
            setSearch('');
        }
    }, [open, user]);

    const needle = search.trim().toLowerCase();
    const visible = useMemo(() => devices.filter((device) => !needle
        || device.name.toLowerCase().includes(needle)
        || device.ip_address.toLowerCase().includes(needle)
        || (device.category ?? '').toLowerCase().includes(needle)), [devices, needle]);

    const selectable = visible.filter((device) => device.owner_id !== user?.id);
    const allSelected = selectable.length > 0 && selectable.every((device) => selected.includes(device.id));
    const someSelected = selectable.some((device) => selected.includes(device.id)) && !allSelected;

    const toggle = (ids, checked) => {
        setSelected((current) => (checked
            ? [...new Set([...current, ...ids])]
            : current.filter((id) => !ids.includes(id))));
    };

    const save = () => {
        setSaving(true);
        router.put(route('admin.users.devices.update', user.id), { device_ids: selected }, {
            preserveScroll: true,
            onSuccess: onClose,
            onFinish: () => setSaving(false),
        });
    };

    return (
        <Modal
            open={open}
            onCancel={onClose}
            onOk={save}
            okText="Saqlash"
            cancelText="Bekor qilish"
            confirmLoading={saving}
            destroyOnHidden
            width={560}
            title={user ? `Qurilmalarni bog‘lash — ${user.name}` : 'Qurilmalarni bog‘lash'}
        >
            <p className="mb-4 text-sm text-ink-muted">
                Foydalanuvchi faqat o‘zi qo‘shgan va shu yerda belgilangan qurilmalarni ko‘radi va boshqaradi.
            </p>

            <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
                <Input
                    allowClear
                    className="min-w-[200px] flex-1"
                    placeholder="Qurilma nomi, IP yoki kategoriya…"
                    prefix={<SearchOutlined className="text-ink-muted" />}
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                />
                <span className="text-sm font-bold text-ink">
                    {selected.length}
                    {' ta tanlandi'}
                </span>
            </div>

            <div className="overflow-hidden rounded-lg border border-line">
                <label className="flex cursor-pointer items-center gap-3 border-b border-line bg-surface-muted px-4 py-2.5 text-sm font-bold text-ink">
                    <Checkbox
                        checked={allSelected}
                        indeterminate={someSelected}
                        disabled={selectable.length === 0}
                        onChange={(e) => toggle(selectable.map((device) => device.id), e.target.checked)}
                    />
                    {needle ? 'Topilganlarning hammasi' : 'Hammasini tanlash'}
                </label>
                <ul className="max-h-80 overflow-y-auto">
                    {visible.length ? visible.map((device) => {
                        const owned = device.owner_id === user?.id;

                        return (
                            <li key={device.id} className="border-b border-line last:border-b-0">
                                <label className={`flex items-center gap-3 px-4 py-2.5 transition-colors duration-150 ease-productive ${owned ? 'cursor-default' : 'cursor-pointer hover:bg-surface-muted'}`}>
                                    <Checkbox
                                        checked={owned || selected.includes(device.id)}
                                        disabled={owned}
                                        onChange={(e) => toggle([device.id], e.target.checked)}
                                    />
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate text-sm font-bold text-ink">{device.name}</span>
                                        <span className="block truncate text-xs text-ink-muted">
                                            {[device.category, device.ip_address].filter(Boolean).join(' · ')}
                                        </span>
                                    </span>
                                    {owned ? (
                                        <span className="shrink-0 text-xs font-semibold text-ink-muted">o‘zi qo‘shgan</span>
                                    ) : null}
                                </label>
                            </li>
                        );
                    }) : (
                        <li className="px-4 py-8 text-center text-sm text-ink-muted">Qurilma topilmadi.</li>
                    )}
                </ul>
            </div>
        </Modal>
    );
}
