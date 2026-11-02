import { Input } from 'antd';
import AdminLayout from '../../../Layouts/AdminLayout';
import { route } from '../../../routes';
import PageHeader, { ResourceActions } from '../../../Components/PageHeader';
import FormDrawer, { useFormDrawer } from '../../../Components/FormDrawer';
import FormField from '../../../Components/FormField';
import Pagination from '../../../Components/Pagination';
import TableShell from '../../../Components/TableShell';

export default function Index({ permissions, drawer: initialDrawer = null }) {
    const drawer = useFormDrawer(initialDrawer, 'admin.permissions.index');

    return (
        <AdminLayout title="Ruxsatlar">
            <PageHeader
                title="Ruxsatlar"
                subtitle="Tizim bo‘limlariga kirish huquqlarini boshqaring."
                resource="permissions"
                onCreate={drawer.openCreate}
                createLabel="Ruxsat qo'shish"
            />
            <TableShell
                empty={permissions.data.length === 0}
                resource="permissions"
                onCreate={drawer.openCreate}
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
                                        resource="permissions"
                                        onEdit={() => drawer.openEdit({ id: permission.id, name: permission.name })}
                                        deleteHref={route('admin.permissions.destroy', permission.id)}
                                    />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </TableShell>

            <FormDrawer
                drawer={drawer}
                title={{ create: 'Yangi ruxsat', edit: 'Ruxsatni tahrirlash' }}
                initialValues={(record) => ({ name: record?.name || '' })}
                storeUrl={route('admin.permissions.store')}
                updateUrl={(record) => route('admin.permissions.update', record.id)}
            >
                {({ data, setData, errors }) => (
                    <FormField label="Ruxsat nomi" error={errors.name} required>
                        <Input
                            autoFocus
                            size="large"
                            value={data.name}
                            status={errors.name ? 'error' : undefined}
                            onChange={(e) => setData('name', e.target.value)}
                        />
                        <p className="mt-2 text-sm text-ink-muted">
                            Menyu bo‘limlari ruxsatga aynan shu nom bilan bog‘langan. Mavjud ruxsat nomini
                            o‘zgartirsangiz, unga bog‘langan bo‘lim foydalanuvchilarga yopilib qolishi mumkin.
                        </p>
                    </FormField>
                )}
            </FormDrawer>
        </AdminLayout>
    );
}
