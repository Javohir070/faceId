import { usePage } from '@inertiajs/react';
import { LinkOutlined } from '@ant-design/icons';
import { Button } from 'antd';
import { useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import { route } from '../../../routes';
import ShowPage from '../../../Components/ShowPage';
import StatusTag from '../../../Components/StatusTag';
import UserDevicesModal from '../../../Components/UserDevicesModal';

function SectionTitle({ children }) {
    return (
        <h2 className="mb-3 text-xs font-extrabold uppercase tracking-wide text-ink-subtle">
            {children}
        </h2>
    );
}

function DevicesSection({ user, onLink }) {
    const canLink = Boolean(usePage().props.can?.['users.edit']);

    if (user.is_super_admin) {
        return (
            <div>
                <SectionTitle>Qurilmalar</SectionTitle>
                <p className="text-sm font-semibold text-ink">Super-admin barcha qurilmalarni ko‘radi.</p>
            </div>
        );
    }

    const rows = [
        ...user.owned_devices.map((device) => ({ ...device, owned: true })),
        ...user.assigned_devices.filter((device) => !user.owned_devices.some((own) => own.id === device.id)),
    ];

    return (
        <div>
            <div className="mb-3 flex items-center justify-between gap-3">
                <h2 className="text-xs font-extrabold uppercase tracking-wide text-ink-subtle">
                    Qurilmalar
                    {rows.length ? ` · ${rows.length}` : ''}
                </h2>
                {canLink ? (
                    <Button icon={<LinkOutlined />} className="!font-bold" onClick={onLink}>
                        Qurilmalarni bog‘lash
                    </Button>
                ) : null}
            </div>
            {rows.length ? (
                <ul className="overflow-hidden rounded-lg border border-line">
                    {rows.map((device) => (
                        <li key={device.id} className="flex items-center gap-3 border-b border-line px-4 py-2.5 last:border-b-0">
                            <span className="min-w-0 flex-1">
                                <span className="block truncate text-sm font-bold text-ink">{device.name}</span>
                                <span className="block truncate text-xs text-ink-muted">
                                    {[device.category, device.ip_address].filter(Boolean).join(' · ')}
                                </span>
                            </span>
                            {device.owned ? <StatusTag tone="neutral">o‘zi qo‘shgan</StatusTag> : null}
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="text-sm text-ink-muted">
                    Hali qurilma bog‘lanmagan — foydalanuvchi hech qaysi qurilmani ko‘rmaydi.
                </p>
            )}
        </div>
    );
}

export default function Show({ user, devices = [] }) {
    const [linking, setLinking] = useState(false);

    return (
        <AdminLayout title="Foydalanuvchi">
            <ShowPage
                backHref={route('admin.users.index')}
                title={user.name}
                subtitle={user.email}
                resource="users"
                editHref={route('admin.users.edit', user.id)}
                deleteHref={route('admin.users.destroy', user.id)}
                maxWidth="max-w-2xl"
            >
                <div className="space-y-6">
                    <div>
                        <SectionTitle>Bo‘lim</SectionTitle>
                        <p className="text-sm font-semibold text-ink">
                            {user.department?.name || '—'}
                        </p>
                    </div>
                    <div>
                        <SectionTitle>Rollar</SectionTitle>
                        <div className="flex flex-wrap gap-2">
                            {user.roles?.length
                                ? user.roles.map((role) => (
                                    <StatusTag key={role.id} tone="info">{role.name}</StatusTag>
                                ))
                                : <span className="text-sm font-semibold text-ink-subtle">—</span>}
                        </div>
                    </div>
                    <DevicesSection user={user} onLink={() => setLinking(true)} />
                </div>
            </ShowPage>

            <UserDevicesModal
                user={linking ? user : null}
                devices={devices}
                open={linking}
                onClose={() => setLinking(false)}
            />
        </AdminLayout>
    );
}
