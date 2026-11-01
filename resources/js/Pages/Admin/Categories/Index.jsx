import { Input } from 'antd';
import AdminLayout from '../../../Layouts/AdminLayout';
import { route } from '../../../routes';
import PageHeader, { ResourceActions } from '../../../Components/PageHeader';
import FormDrawer, { useFormDrawer } from '../../../Components/FormDrawer';
import FormField from '../../../Components/FormField';
import Pagination from '../../../Components/Pagination';
import TableShell from '../../../Components/TableShell';

export default function Index({ categories, drawer: initialDrawer = null }) {
    const drawer = useFormDrawer(initialDrawer, 'admin.categories.index');

    return (
        <AdminLayout title="Kategoriyalar">
            <PageHeader
                title="Kategoriyalar"
                subtitle="Qurilmalar uchun kategoriyalarni boshqaring."
                onCreate={drawer.openCreate}
                createLabel="Kategoriya qo'shish"
            />
            <TableShell
                empty={categories.data.length === 0}
                onCreate={drawer.openCreate}
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
                                        onEdit={() => drawer.openEdit({ id: category.id, name: category.name })}
                                        deleteHref={route('admin.categories.destroy', category.id)}
                                    />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </TableShell>

            <FormDrawer
                drawer={drawer}
                title={{ create: 'Yangi kategoriya', edit: 'Kategoriyani tahrirlash' }}
                initialValues={(record) => ({ name: record?.name || '' })}
                storeUrl={route('admin.categories.store')}
                updateUrl={(record) => route('admin.categories.update', record.id)}
            >
                {({ data, setData, errors }) => (
                    <FormField label="Nomi" error={errors.name} required>
                        <Input
                            autoFocus
                            size="large"
                            placeholder="Masalan: Asosiy kirish"
                            value={data.name}
                            status={errors.name ? 'error' : undefined}
                            onChange={(e) => setData('name', e.target.value)}
                        />
                        <p className="mt-2 text-sm text-ink-muted">
                            Kategoriya qurilmalarni guruhlash uchun: masalan, bino yoki kirish joyi bo‘yicha.
                        </p>
                    </FormField>
                )}
            </FormDrawer>
        </AdminLayout>
    );
}
