import AdminLayout from '../../../Layouts/AdminLayout';
import { route } from '../../../routes';
import PageHeader, { ResourceActions } from '../../../Components/PageHeader';
import Pagination from '../../../Components/Pagination';
import TableShell from '../../../Components/TableShell';

export default function Index({ categories }) {
    return (
        <AdminLayout title="Kategoriyalar">
            <PageHeader
                title="Kategoriyalar"
                subtitle="Qurilmalar uchun kategoriyalarni boshqaring."
                createHref={route('admin.categories.create')}
                createLabel="Kategoriya qo'shish"
            />
            <TableShell
                empty={categories.data.length === 0}
                createHref={route('admin.categories.create')}
                createLabel="Kategoriya qo'shish"
                footer={categories.data.length > 0 ? (
                    <Pagination
                        links={categories.links}
                        perPage={categories.per_page}
                        total={categories.total}
                    />
                ) : null}
            >
                <table className="data-table">
                    <thead>
                        <tr>
                            <th className="w-16">N#</th>
                            <th>Nomi</th>
                            <th>Qurilmalar</th>
                            <th className="text-right">Amallar</th>
                        </tr>
                    </thead>
                    <tbody>
                        {categories.data.map((category, index) => (
                            <tr key={category.id}>
                                <td className="font-medium text-ink-subtle">{(categories.from ?? 1) + index}</td>
                                <td className="font-bold text-ink">{category.name}</td>
                                <td className="font-medium">{category.devices_count ?? 0}</td>
                                <td>
                                    <ResourceActions
                                        showHref={route('admin.categories.show', category.id)}
                                        editHref={route('admin.categories.edit', category.id)}
                                        deleteHref={route('admin.categories.destroy', category.id)}
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
