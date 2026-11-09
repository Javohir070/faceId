import { ClusterOutlined } from '@ant-design/icons';
import { Input, Tooltip } from 'antd';
import AdminLayout from '../../../Layouts/AdminLayout';
import { route } from '../../../routes';
import PageHeader, { ResourceActions } from '../../../Components/PageHeader';
import DevicesPanel, { useDevicesPanel } from '../../../Components/DevicesPanel';
import FormDrawer, { useFormDrawer } from '../../../Components/FormDrawer';
import FormField from '../../../Components/FormField';
import Pagination from '../../../Components/Pagination';
import TableShell from '../../../Components/TableShell';

export default function Index({ categories, drawer: initialDrawer = null }) {
    const drawer = useFormDrawer(initialDrawer, 'admin.categories.index');
    const devices = useDevicesPanel();

    return (
        <AdminLayout title="Kategoriyalar">
            <PageHeader
                title="Kategoriyalar"
                subtitle="Qurilmalar uchun kategoriyalarni boshqaring."
                resource="categories"
                onCreate={drawer.openCreate}
                createLabel="Kategoriya qo'shish"
            />
            <TableShell
                empty={categories.data.length === 0}
                resource="categories"
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
                                <td>
                                    <button
                                        type="button"
                                        className="font-bold text-ink transition-colors duration-150 ease-productive hover:text-brand-700"
                                        onClick={() => devices.open(`category:${category.id}`)}
                                    >
                                        {category.name}
                                    </button>
                                </td>
                                <td className="font-medium tabular-nums">
                                    <button
                                        type="button"
                                        className="hover:text-brand-700"
                                        aria-label={`«${category.name}» qurilmalari soni`}
                                        onClick={() => devices.open(`category:${category.id}`)}
                                    >
                                        {category.devices_count ?? 0}
                                    </button>
                                </td>
                                <td>
                                    <div className="flex items-center justify-end gap-2">
                                        <Tooltip title="Qurilmalarni ko‘rish">
                                            <button
                                                type="button"
                                                className="table-action-view"
                                                aria-label={`«${category.name}» qurilmalarini ko‘rish`}
                                                onClick={() => devices.open(`category:${category.id}`)}
                                            >
                                                <ClusterOutlined aria-hidden="true" />
                                            </button>
                                        </Tooltip>
                                        <ResourceActions
                                            resource="categories"
                                            onEdit={() => drawer.openEdit({ id: category.id, name: category.name })}
                                            deleteHref={route('admin.categories.destroy', category.id)}
                                        />
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </TableShell>

            <DevicesPanel state={devices} />

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
