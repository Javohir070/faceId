import AdminLayout from '../../../Layouts/AdminLayout';
import PageHeader, { ResourceActions } from '../../../Components/PageHeader';
import Pagination from '../../../Components/Pagination';
import TableShell from '../../../Components/TableShell';

export default function Index({ devices }) {
    return (
        <AdminLayout title="Qurilmalar">
            <PageHeader
                title="Qurilmalar"
                subtitle="Biometrik va access control qurilmalarini boshqaring."
                createHref="/admin/devices/create"
                createLabel="Qurilma qo'shish"
            />
            <TableShell
                empty={devices.data.length === 0}
                createHref="/admin/devices/create"
                createLabel="Qurilma qo'shish"
                footer={devices.data.length > 0 ? (
                    <Pagination
                        links={devices.links}
                        perPage={devices.per_page}
                        total={devices.total}
                    />
                ) : null}
            >
                <table className="data-table">
                    <thead>
                        <tr>
                            <th className="w-16">N#</th>
                            <th>Nomi</th>
                            <th>Kategoriya</th>
                            <th>IP / Port</th>
                            <th>Turi</th>
                            <th className="text-right">Amallar</th>
                        </tr>
                    </thead>
                    <tbody>
                        {devices.data.map((device, index) => (
                            <tr key={device.id}>
                                <td className="font-medium text-ink-subtle">{(devices.from ?? 1) + index}</td>
                                <td>
                                    <div className="min-w-0">
                                        <p className="font-bold text-ink">{device.name}</p>
                                        <p className="mt-0.5 text-xs font-medium text-ink-subtle">
                                            {device.description}
                                        </p>
                                    </div>
                                </td>
                                <td className="font-medium">{device.category?.name || '—'}</td>
                                <td className="font-medium">
                                    {device.ip_address}
                                    :
                                    {device.port}
                                </td>
                                <td className="font-medium">{device.type}</td>
                                <td>
                                    <ResourceActions
                                        showHref={`/admin/devices/${device.id}`}
                                        editHref={`/admin/devices/${device.id}/edit`}
                                        deleteHref={`/admin/devices/${device.id}`}
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
