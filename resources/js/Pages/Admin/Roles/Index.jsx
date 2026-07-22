import AdminLayout from '../../../Layouts/AdminLayout';
import { route } from '../../../routes';
import PageHeader, { ResourceActions } from '../../../Components/PageHeader';
import Pagination from '../../../Components/Pagination';
import TableShell from '../../../Components/TableShell';

export default function Index({ roles }) {
    return (
        <AdminLayout title="Rollar">
            <PageHeader
                title="Rollar"
                subtitle="Foydalanuvchi rollari va ularga tegishli ruxsatlarni sozlang."
                createHref={route('admin.roles.create')}
                createLabel="Rol qo'shish"
            />
            <TableShell
                empty={roles.data.length === 0}
                createHref={route('admin.roles.create')}
                createLabel="Rol qo'shish"
                footer={roles.data.length > 0 ? (
                    <Pagination
                        links={roles.links}
                        perPage={roles.per_page}
                        total={roles.total}
                    />
                ) : null}
            >
                <table className="data-table">
                    <thead>
                        <tr>
                            <th className="w-16">N#</th>
                            <th>Nomi</th>
                            <th className="text-right">Amallar</th>
                        </tr>
                    </thead>
                    <tbody>
                        {roles.data.map((role, index) => (
                            <tr key={role.id}>
                                <td className="font-medium text-ink-subtle">{(roles.from ?? 1) + index}</td>
                                <td className="font-bold text-ink">{role.name}</td>
                                <td>
                                    <ResourceActions
                                        showHref={route('admin.roles.show', role.id)}
                                        editHref={route('admin.roles.edit', role.id)}
                                        deleteHref={route('admin.roles.destroy', role.id)}
                                    />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </TableShell>
        </AdminLayout>
    );
}
