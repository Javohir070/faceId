import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader, { ResourceActions } from '../../../Components/PageHeader';
import Pagination from '../../../Components/Pagination';
import TableShell from '../../../Components/TableShell';

export default function Index({ schedules }) {
    return (
        <AdminLayout title="Jadvallar">
            <PageHeader
                title="Jadvallar"
                subtitle="Kirish va chiqish vaqtlarini boshqaring."
                createHref="/admin/schedules/create"
                createLabel="Jadval qo'shish"
            />
            <TableShell
                empty={schedules.data.length === 0}
                createHref="/admin/schedules/create"
                createLabel="Jadval qo'shish"
                footer={schedules.data.length > 0 ? (
                    <Pagination
                        links={schedules.links}
                        perPage={schedules.per_page}
                        total={schedules.total}
                    />
                ) : null}
            >
                <table className="data-table">
                    <thead>
                        <tr>
                            <th className="w-16">N#</th>
                            <th>Nomi</th>
                            <th>Kirish vaqti</th>
                            <th>Chiqish vaqti</th>
                            <th className="text-right">Amallar</th>
                        </tr>
                    </thead>
                    <tbody>
                        {schedules.data.map((schedule, index) => (
                            <tr key={schedule.id}>
                                <td className="font-medium text-ink-subtle">{(schedules.from ?? 1) + index}</td>
                                <td className="font-bold text-ink">{schedule.name}</td>
                                <td className="font-medium">{schedule.enter_time}</td>
                                <td className="font-medium">{schedule.exit_time}</td>
                                <td>
                                    <ResourceActions
                                        showHref={`/admin/schedules/${schedule.id}`}
                                        editHref={`/admin/schedules/${schedule.id}/edit`}
                                        deleteHref={`/admin/schedules/${schedule.id}`}
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
