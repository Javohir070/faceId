import { Input } from 'antd';
import AdminLayout from '../../../Layouts/AdminLayout';
import { route } from '../../../routes';
import PageHeader, { ResourceActions } from '../../../Components/PageHeader';
import FormDrawer, { useFormDrawer } from '../../../Components/FormDrawer';
import FormField from '../../../Components/FormField';
import Pagination from '../../../Components/Pagination';
import TableShell from '../../../Components/TableShell';

export default function Index({ departments, drawer: initialDrawer = null }) {
    const drawer = useFormDrawer(initialDrawer, 'admin.departments.index');

    return (
        <AdminLayout title="Bo'limlar">
            <PageHeader
                title="Bo'limlar"
                subtitle="Tashkilot bo'limlarini boshqaring."
                resource="departments"
                onCreate={drawer.openCreate}
                createLabel="Bo'lim qo'shish"
            />
            <TableShell
                empty={departments.data.length === 0}
                resource="departments"
                onCreate={drawer.openCreate}
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
                                        resource="departments"
                                        onEdit={() => drawer.openEdit({ id: department.id, name: department.name })}
                                        deleteHref={route('admin.departments.destroy', department.id)}
                                    />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </TableShell>

            <FormDrawer
                drawer={drawer}
                title={{ create: 'Yangi bo‘lim', edit: 'Bo‘limni tahrirlash' }}
                initialValues={(record) => ({ name: record?.name || '' })}
                storeUrl={route('admin.departments.store')}
                updateUrl={(record) => route('admin.departments.update', record.id)}
            >
                {({ data, setData, errors }) => (
                    <FormField label="Nomi" error={errors.name} required>
                        <Input
                            autoFocus
                            size="large"
                            placeholder="Masalan: Buxgalteriya"
                            value={data.name}
                            status={errors.name ? 'error' : undefined}
                            onChange={(e) => setData('name', e.target.value)}
                        />
                    </FormField>
                )}
            </FormDrawer>
        </AdminLayout>
    );
}
