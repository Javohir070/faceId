import { Tag } from 'antd';
import AdminLayout from '../../../Layouts/AdminLayout';
import { route } from '../../../routes';
import PageHeader, { ResourceActions } from '../../../Components/PageHeader';
import Pagination from '../../../Components/Pagination';
import TableShell from '../../../Components/TableShell';

export default function Index({ employees }) {
    return (
        <AdminLayout title="Xodimlar">
            <PageHeader
                title="Xodimlar"
                subtitle="Xodimlar, kartalar, yuz rasmlari va bo'limlarni boshqaring."
                createHref={route('admin.employees.create')}
                createLabel="Xodim qo'shish"
            />
            <TableShell
                empty={employees.data.length === 0}
                createHref={route('admin.employees.create')}
                createLabel="Xodim qo'shish"
                footer={employees.data.length > 0 ? (
                    <Pagination
                        links={employees.links}
                        perPage={employees.per_page}
                        total={employees.total}
                    />
                ) : null}
            >
                <table className="data-table">
                    <thead>
                        <tr>
                            <th className="w-16">N#</th>
                            <th>F.I.Sh.</th>
                            <th>Karta</th>
                            <th>Bo'lim</th>
                            <th>Jadval</th>
                            <th>Yuz</th>
                            <th>Amal qilish</th>
                            <th className="text-right">Amallar</th>
                        </tr>
                    </thead>
                    <tbody>
                        {employees.data.map((employee, index) => (
                            <tr key={employee.id}>
                                <td className="font-medium text-ink-subtle">{(employees.from ?? 1) + index}</td>
                                <td className="font-bold text-ink">{employee.name}</td>
                                <td className="font-medium">{employee.card}</td>
                                <td className="font-medium">{employee.department?.name || '—'}</td>
                                <td className="font-medium">{employee.schedule?.name || '—'}</td>
                                <td>
                                    <Tag color={employee.has_face ? 'success' : 'default'}>
                                        {employee.has_face ? 'Bor' : "Yo'q"}
                                    </Tag>
                                </td>
                                <td className="font-medium text-ink-muted">
                                    {employee.valid_from_dt}
                                    {' — '}
                                    {employee.valid_to_dt}
                                </td>
                                <td>
                                    <ResourceActions
                                        showHref={route('admin.employees.show', employee.id)}
                                        editHref={route('admin.employees.edit', employee.id)}
                                        deleteHref={route('admin.employees.destroy', employee.id)}
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
