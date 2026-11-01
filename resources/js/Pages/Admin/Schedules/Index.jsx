import { Input } from 'antd';
import AdminLayout from '../../../Layouts/AdminLayout';
import { route } from '../../../routes';
import PageHeader, { ResourceActions } from '../../../Components/PageHeader';
import FormDrawer, { useFormDrawer } from '../../../Components/FormDrawer';
import FormField from '../../../Components/FormField';
import Pagination from '../../../Components/Pagination';
import TableShell from '../../../Components/TableShell';

export default function Index({ schedules, drawer: initialDrawer = null }) {
    const drawer = useFormDrawer(initialDrawer, 'admin.schedules.index');

    return (
        <AdminLayout title="Jadvallar">
            <PageHeader
                title="Jadvallar"
                subtitle="Kirish va chiqish vaqtlarini boshqaring."
                onCreate={drawer.openCreate}
                createLabel="Jadval qo'shish"
            />
            <TableShell
                empty={schedules.data.length === 0}
                onCreate={drawer.openCreate}
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
                                        onEdit={() => drawer.openEdit({
                                            id: schedule.id,
                                            name: schedule.name,
                                            enter_time: schedule.enter_time,
                                            exit_time: schedule.exit_time,
                                        })}
                                        deleteHref={route('admin.schedules.destroy', schedule.id)}
                                    />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </TableShell>

            <FormDrawer
                drawer={drawer}
                title={{ create: 'Yangi jadval', edit: 'Jadvalni tahrirlash' }}
                initialValues={(record) => ({
                    name: record?.name || '',
                    enter_time: record?.enter_time || '',
                    exit_time: record?.exit_time || '',
                })}
                storeUrl={route('admin.schedules.store')}
                updateUrl={(record) => route('admin.schedules.update', record.id)}
            >
                {({ data, setData, errors }) => (
                    <>
                        <FormField label="Nomi" error={errors.name} required>
                            <Input
                                autoFocus
                                size="large"
                                placeholder="Masalan: Standart"
                                value={data.name}
                                status={errors.name ? 'error' : undefined}
                                onChange={(e) => setData('name', e.target.value)}
                            />
                        </FormField>
                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Kirish vaqti" error={errors.enter_time} required>
                                <Input
                                    type="time"
                                    size="large"
                                    value={data.enter_time}
                                    status={errors.enter_time ? 'error' : undefined}
                                    onChange={(e) => setData('enter_time', e.target.value)}
                                />
                            </FormField>
                            <FormField label="Chiqish vaqti" error={errors.exit_time} required>
                                <Input
                                    type="time"
                                    size="large"
                                    value={data.exit_time}
                                    status={errors.exit_time ? 'error' : undefined}
                                    onChange={(e) => setData('exit_time', e.target.value)}
                                />
                            </FormField>
                        </div>
                    </>
                )}
            </FormDrawer>
        </AdminLayout>
    );
}
