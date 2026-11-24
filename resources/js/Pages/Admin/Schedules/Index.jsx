import { LockOutlined } from '@ant-design/icons';
import { Input, InputNumber } from 'antd';
import AdminLayout from '../../../Layouts/AdminLayout';
import { route } from '../../../routes';
import PageHeader, { ResourceActions } from '../../../Components/PageHeader';
import FormDrawer, { useFormDrawer } from '../../../Components/FormDrawer';
import FormField from '../../../Components/FormField';
import InputError from '../../../Components/InputError';
import Pagination from '../../../Components/Pagination';
import StatusTag from '../../../Components/StatusTag';
import TableShell from '../../../Components/TableShell';

/** Chiqish kirishdan oldin bo‘lsa — tungi smena (AttendanceCalculator::isNightShift bilan bir xil). */
function isNightShift(enter, exit) {
    return Boolean(enter && exit) && exit < enter;
}

/** "09:00" + 15 → "09:15" (yarim tundan o‘tsa qaytadan boshlanadi). */
function addMinutes(time, minutes) {
    const [hours, mins] = time.split(':').map(Number);
    const total = (hours * 60 + mins + minutes) % 1440;

    return `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`;
}

function graceHint(enter, grace) {
    if (!enter || !grace) {
        return 'Kirish vaqtidan shuncha daqiqa ichida kelgan xodim kechikkan hisoblanmaydi.';
    }

    return `${addMinutes(enter, grace)} gacha kelgan xodim kechikkan hisoblanmaydi. Undan keyin kelsa, kechikish ${enter} dan hisoblanadi.`;
}

export default function Index({ schedules, drawer: initialDrawer = null }) {
    const drawer = useFormDrawer(initialDrawer, 'admin.schedules.index');

    return (
        <AdminLayout title="Jadvallar">
            <PageHeader
                title="Jadvallar"
                subtitle="Kirish va chiqish vaqtlarini boshqaring."
                resource="schedules"
                onCreate={drawer.openCreate}
                createLabel="Jadval qo'shish"
            />
            <TableShell
                empty={schedules.data.length === 0}
                resource="schedules"
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
                            <th>Bo&apos;lim</th>
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
                                <td className="font-medium">
                                    {schedule.department ?? <StatusTag tone="neutral">Umumiy</StatusTag>}
                                </td>
                                <td className="font-medium">
                                    {schedule.enter_time}
                                    {schedule.late_grace_minutes > 0 ? (
                                        <span className="ml-2 text-xs font-semibold text-ink-muted">
                                            +{schedule.late_grace_minutes} daq ruxsat
                                        </span>
                                    ) : null}
                                </td>
                                <td className="font-medium">
                                    {schedule.exit_time}
                                    {isNightShift(schedule.enter_time, schedule.exit_time) ? (
                                        <span className="ml-2 text-xs font-semibold text-ink-muted">ertasi kuni</span>
                                    ) : null}
                                </td>
                                <td>
                                    {schedule.can_manage ? (
                                        <ResourceActions
                                            resource="schedules"
                                            onEdit={() => drawer.openEdit({
                                                id: schedule.id,
                                                name: schedule.name,
                                                enter_time: schedule.enter_time,
                                                exit_time: schedule.exit_time,
                                                late_grace_minutes: schedule.late_grace_minutes,
                                            })}
                                            deleteHref={route('admin.schedules.destroy', schedule.id)}
                                        />
                                    ) : (
                                        <div className="flex items-center justify-end gap-1.5 whitespace-nowrap text-xs font-semibold text-ink-subtle">
                                            <LockOutlined aria-hidden="true" />
                                            Faqat ko‘rish
                                        </div>
                                    )}
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
                    late_grace_minutes: record?.late_grace_minutes ?? 0,
                })}
                storeUrl={route('admin.schedules.store')}
                updateUrl={(record) => route('admin.schedules.update', record.id)}
            >
                {({ data, setData, errors }) => (
                    <>
                        <InputError message={errors.department_id} />
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
                        <FormField
                            label="Kechikishga ruxsat (daqiqa)"
                            error={errors.late_grace_minutes}
                            hint={graceHint(data.enter_time, data.late_grace_minutes)}
                        >
                            <InputNumber
                                className="w-full"
                                size="large"
                                min={0}
                                max={120}
                                precision={0}
                                suffix="daqiqa"
                                value={data.late_grace_minutes}
                                status={errors.late_grace_minutes ? 'error' : undefined}
                                onChange={(value) => setData('late_grace_minutes', value ?? 0)}
                            />
                        </FormField>
                        {isNightShift(data.enter_time, data.exit_time) ? (
                            <p className="rounded-lg bg-surface-muted px-3 py-2 text-sm text-ink-muted">
                                Tungi smena: xodim
                                {' '}
                                <span className="font-bold text-ink">{data.enter_time}</span>
                                {' '}
                                da kelib, ertasi kuni
                                {' '}
                                <span className="font-bold text-ink">{data.exit_time}</span>
                                {' '}
                                da ketadi. Davomatda ikkalasi bitta ish kuniga yoziladi.
                            </p>
                        ) : null}
                    </>
                )}
            </FormDrawer>
        </AdminLayout>
    );
}
