import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader, { ResourceActions } from '../../../Components/PageHeader';
import Pagination from '../../../Components/Pagination';
import TableShell from '../../../Components/TableShell';

export default function Index({ permissions }) {
    return (
        <AdminLayout title="Ruxsatlar">
            <PageHeader
                title="Ruxsatlar"
                subtitle="Tizim bo‘limlariga kirish huquqlarini boshqaring."
                createHref="/admin/permissions/create"
                createLabel="Ruxsat qo'shish"
            />
            <TableShell
                empty={permissions.data.length === 0}
                createHref="/admin/permissions/create"
                createLabel="Ruxsat qo'shish"
                footer={permissions.data.length > 0 ? (
                    <Pagination
                        links={permissions.links}
                        perPage={permissions.per_page}
                        total={permissions.total}
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
                        {permissions.data.map((permission, index) => (
                            <tr key={permission.id}>
                                <td className="font-medium text-ink-subtle">{(permissions.from ?? 1) + index}</td>
                                <td className="font-bold text-ink">{permission.name}</td>
                                <td>
                                    <ResourceActions
                                        showHref={`/admin/permissions/${permission.id}`}
                                        editHref={`/admin/permissions/${permission.id}/edit`}
                                        deleteHref={`/admin/permissions/${permission.id}`}
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
