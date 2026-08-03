import AdminLayout from '../../../Layouts/AdminLayout';
import { route } from '../../../routes';
import PageHeader, { ResourceActions, RoleBadge } from '../../../Components/PageHeader';
import Pagination from '../../../Components/Pagination';
import TableShell from '../../../Components/TableShell';

export default function Index({ users }) {
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
                createHref={route('admin.users.create')}
                createLabel="Foydalanuvchi qo'shish"
            />
            <TableShell
                empty={users.data.length === 0}
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
                                            <p className="font-bold text-ink">{user.name}</p>
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
                                        <ResourceActions
                                            editHref={route('admin.users.edit', user.id)}
                                            deleteHref={route('admin.users.destroy', user.id)}
                                        />
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </TableShell>
        </AdminLayout>
    );
}
