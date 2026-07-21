import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader, { ResourceActions } from '../../../Components/PageHeader';
import Pagination from '../../../Components/Pagination';
import TableShell from '../../../Components/TableShell';

export default function Index({ departments }) {
    return (
        <AdminLayout title="Bo'limlar">
            <PageHeader
                title="Bo'limlar"
                subtitle="Tashkilot bo'limlarini boshqaring."
                createHref="/admin/departments/create"
                createLabel="Bo'lim qo'shish"
            />
            <TableShell
                empty={departments.data.length === 0}
                createHref="/admin/departments/create"
                createLabel="Bo'lim qo'shish"
                footer={departments.data.length > 0 ? (
                    <Pagination
                        links={departments.links}
                        perPage={departments.per_page}
                        total={departments.total}
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
                        {departments.data.map((department, index) => (
                            <tr key={department.id}>
                                <td className="font-medium text-ink-subtle">{(departments.from ?? 1) + index}</td>
                                <td className="font-bold text-ink">{department.name}</td>
                                <td>
                                    <ResourceActions
                                        showHref={`/admin/departments/${department.id}`}
                                        editHref={`/admin/departments/${department.id}/edit`}
                                        deleteHref={`/admin/departments/${department.id}`}
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
