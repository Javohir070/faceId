import { Link, usePage } from '@inertiajs/react';
import { LinkOutlined } from '@ant-design/icons';
import { Tooltip } from 'antd';
import { useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import { route } from '../../../routes';
import PageHeader, { ResourceActions, RoleBadge } from '../../../Components/PageHeader';
import Pagination from '../../../Components/Pagination';
import StatusTag from '../../../Components/StatusTag';
import TableShell from '../../../Components/TableShell';
import UserDevicesModal from '../../../Components/UserDevicesModal';

/** Foydalanuvchiga ko‘rinadigan qurilmalar: super-admin — hammasi, qolganlar — qo‘shgan + bog‘langan. */
function DevicesCell({ user }) {
    if (user.is_super_admin) {
        return <StatusTag tone="success">Barchasi</StatusTag>;
    }

    const total = user.device_ids.length + user.owned_devices_count;

    return total > 0 ? (
        <span className="font-bold tabular-nums text-ink">
            {total}
            {' ta'}
        </span>
    ) : (
        <span className="text-xs font-semibold text-ink-subtle">Bog‘lanmagan</span>
    );
}

export default function Index({ users, devices = [] }) {
    const can = usePage().props.can || {};
    const [linking, setLinking] = useState(null);

    const pagination = (
        <Pagination
            links={users.links}
            perPage={users.per_page}
            total={users.total}
        />
    );

    return (
        <AdminLayout title="Foydalanuvchilar">
            <PageHeader
                title="Foydalanuvchilar"
                subtitle="Tizimdagi barcha foydalanuvchilar ro'yxati"
                resource="users"
                createHref={route('admin.users.create')}
                createLabel="Foydalanuvchi qo'shish"
            />
            <TableShell
                empty={users.data.length === 0}
                resource="users"
                createHref={route('admin.users.create')}
                createLabel="Foydalanuvchi qo'shish"
                footer={users.data.length > 0 ? pagination : null}
            >
                <table className="data-table">
                    <thead>
                        <tr>
                            <th className="w-16">N#</th>
                            <th>Foydalanuvchi</th>
                            <th>Elektron pochta</th>
                            <th>Bo‘lim</th>
                            <th>Rollar</th>
                            <th>Qurilmalar</th>
                            <th className="text-right">Amallar</th>
                        </tr>
                    </thead>
                    <tbody>
                        {users.data.map((user, index) => {
                            const rowNumber = (users.from ?? 1) + index;
                            const handle = user.email?.split('@')[0] || 'user';

                            return (
                                <tr key={user.id}>
                                    <td className="font-medium text-ink-subtle">{rowNumber}</td>
                                    <td>
                                        <div className="min-w-0">
                                            <Link
                                                href={route('admin.users.show', user.id)}
                                                className="font-bold text-ink transition-colors duration-150 ease-productive hover:text-brand-700"
                                            >
                                                {user.name}
                                            </Link>
                                            <p className="mt-0.5 text-xs font-medium text-ink-subtle">
                                                @
                                                {handle}
                                            </p>
                                        </div>
                                    </td>
                                    <td className="font-medium">{user.email}</td>
                                    <td className="font-medium">{user.department?.name || '—'}</td>
                                    <td>
                                        <div className="flex flex-wrap gap-1.5">
                                            {(user.roles || []).length > 0 ? (
                                                user.roles.map((role) => (
                                                    <RoleBadge key={role.id || role.name}>
                                                        {role.name}
                                                    </RoleBadge>
                                                ))
                                            ) : (
                                                <span className="text-xs font-medium text-ink-subtle">—</span>
                                            )}
                                        </div>
                                    </td>
                                    <td>
                                        <DevicesCell user={user} />
                                    </td>
                                    <td>
                                        <div className="flex items-center justify-end gap-2">
                                            {!user.is_super_admin && can['users.edit'] ? (
                                                <Tooltip title="Qurilmalarni bog‘lash">
                                                    <button
                                                        type="button"
                                                        className="table-action-view"
                                                        aria-label={`«${user.name}» ga qurilmalarni bog‘lash`}
                                                        onClick={() => setLinking(user)}
                                                    >
                                                        <LinkOutlined aria-hidden="true" />
                                                    </button>
                                                </Tooltip>
                                            ) : null}
                                            <ResourceActions
                                                resource="users"
                                                showHref={route('admin.users.show', user.id)}
                                                editHref={route('admin.users.edit', user.id)}
                                                deleteHref={route('admin.users.destroy', user.id)}
                                            />
                                        </div>
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </TableShell>

            <UserDevicesModal
                user={linking}
                devices={devices}
                open={Boolean(linking)}
                onClose={() => setLinking(null)}
            />
        </AdminLayout>
    );
}
