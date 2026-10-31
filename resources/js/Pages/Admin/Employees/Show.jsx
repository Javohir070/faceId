import { Link, router, usePage } from '@inertiajs/react';
import { EditOutlined, PlusOutlined } from '@ant-design/icons';
import { Tooltip } from 'antd';
import dayjs from 'dayjs';
import AdminLayout from '../../../Layouts/AdminLayout';
import { route } from '../../../routes';
import { BackLink } from '../../../Components/PageHeader';
import { ShowHeader } from '../../../Components/ShowPage';
import DayDrawer from '../../../Components/Attendance/DayDrawer';
import EmployeeDevicesPanel from '../../../Components/EmployeeDevicesPanel';
import MonthCalendar from '../../../Components/Employees/MonthCalendar';
import PassesPanel from '../../../Components/Employees/PassesPanel';
import InitialsAvatar from '../../../Components/InitialsAvatar';
import StatusTag from '../../../Components/StatusTag';
import { formatDayTitle } from '../../../utils/uzDate';

const NOTE_TONES = { 1: 'warning', 2: 'warning', 3: 'info' };

function cleanQuery(query) {
    return Object.fromEntries(
        Object.entries(query).filter(([, value]) => value !== null && value !== undefined && value !== ''),
    );
}

/** Amal qilish muddati bo‘yicha holat: 'Y-m-d H:i' satrlar shu formatda solishtiriladi. */
function validity(employee) {
    const now = dayjs().format('YYYY-MM-DD HH:mm');

    if (employee.valid_to_dt && employee.valid_to_dt < now) {
        return { tone: 'danger', label: 'Muddati tugagan' };
    }
    if (employee.valid_from_dt && employee.valid_from_dt > now) {
        return { tone: 'warning', label: 'Hali boshlanmagan' };
    }

    return { tone: 'success', label: 'Faol' };
}

function DetailRow({ label, children }) {
    return (
        <div className="flex items-start justify-between gap-4 border-b border-line py-2.5 last:border-b-0">
            <dt className="shrink-0 text-sm text-ink-muted">{label}</dt>
            <dd className="min-w-0 text-right text-sm font-bold text-ink">{children}</dd>
        </div>
    );
}

function ProfileCard({ employee, canGroups }) {
    const status = validity(employee);

    return (
        <section className="card">
            <div className="flex flex-col items-center text-center">
                {employee.face_preview ? (
                    <img
                        src={employee.face_preview}
                        alt={employee.name}
                        className="h-24 w-24 rounded-full object-cover ring-4 ring-surface-muted"
                    />
                ) : (
                    <InitialsAvatar name={employee.name} size="xl" round />
                )}
                <p className="mt-3 text-base font-extrabold text-ink">{employee.name}</p>
                <p className="text-sm text-ink-muted">{employee.position || 'Lavozim ko‘rsatilmagan'}</p>
                <div className="mt-3 flex flex-wrap justify-center gap-2">
                    <StatusTag tone={status.tone}>{status.label}</StatusTag>
                    <StatusTag tone={employee.face_preview ? 'success' : 'neutral'}>
                        {employee.face_preview ? 'Yuz rasmi bor' : 'Yuz rasmi yo‘q'}
                    </StatusTag>
                </div>
            </div>

            <dl className="mt-5 border-t border-line pt-1">
                <DetailRow label="Karta">
                    <span className="tabular-nums">{employee.card}</span>
                </DetailRow>
                <DetailRow label="Bo‘lim">{employee.department?.name || '—'}</DetailRow>
                <DetailRow label="Guruh">
                    {employee.group ? (
                        canGroups ? (
                            <Link
                                href={route('admin.groups.show', employee.group.id)}
                                className="text-brand-700 hover:text-brand-600"
                            >
                                {employee.group.name}
                            </Link>
                        ) : employee.group.name
                    ) : '—'}
                </DetailRow>
                <DetailRow label="Ish jadvali">
                    {employee.schedule ? (
                        <>
                            {employee.schedule.name}
                            <span className="block text-xs font-semibold tabular-nums text-ink-muted">{employee.schedule.time}</span>
                        </>
                    ) : '—'}
                </DetailRow>
                <DetailRow label="Amal qilish">
                    <span className="block tabular-nums">{employee.valid_from_dt || '—'}</span>
                    <span className="block text-xs font-semibold tabular-nums text-ink-muted">
                        {employee.valid_to_dt ? `${employee.valid_to_dt} gacha` : 'muddatsiz'}
                    </span>
                </DetailRow>
                <DetailRow label="Jins">{employee.gender_label || '—'}</DetailRow>
                <DetailRow label="Davlat">{employee.country_name || '—'}</DetailRow>
            </dl>
        </section>
    );
}

function NotesPanel({ employeeId, notes, canManage }) {
    return (
        <section className="table-shell">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
                <div className="min-w-0">
                    <h2 className="text-lg font-extrabold text-ink">Ruxsatlar</h2>
                    <p className="mt-0.5 text-sm text-ink-muted">
                        Ish vaqtida ruxsatlar va sababli kunlar
                        {notes.length ? ` · jami ${notes.length} ta` : ''}
                    </p>
                </div>
                {canManage ? (
                    <Link
                        href={`${route('admin.notes.create')}?employee_id=${employeeId}`}
                        className="inline-flex h-9 items-center gap-2 rounded-lg bg-brand-600 px-4 text-sm font-bold text-white transition-colors duration-150 ease-productive hover:bg-brand-700 dark:text-brand-950 dark:hover:bg-brand-500"
                    >
                        <PlusOutlined aria-hidden="true" />
                        Ruxsat qo‘shish
                    </Link>
                ) : null}
            </div>
            {notes.length ? (
                <ul>
                    {notes.map((note) => (
                        <li key={note.id} className="flex items-start gap-4 border-b border-line px-5 py-3 last:border-b-0">
                            <div className="min-w-0 flex-1">
                                <p className="flex flex-wrap items-center gap-2">
                                    <span className="text-sm font-bold text-ink">{formatDayTitle(note.dt)}</span>
                                    <StatusTag tone={NOTE_TONES[note.type] ?? 'neutral'}>{note.type_label}</StatusTag>
                                </p>
                                {note.note ? <p className="mt-1 text-sm text-ink-muted">{note.note}</p> : null}
                            </div>
                            {canManage ? (
                                <Tooltip title="Tahrirlash">
                                    <Link
                                        href={route('admin.notes.edit', note.id)}
                                        className="table-action-edit shrink-0"
                                        aria-label="Ruxsatni tahrirlash"
                                    >
                                        <EditOutlined aria-hidden="true" />
                                    </Link>
                                </Tooltip>
                            ) : null}
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="px-5 py-8 text-center text-sm text-ink-muted">Xodim hali ruxsat olmagan.</p>
            )}
        </section>
    );
}

export default function Show({
    employee,
    notes = [],
    filters,
    month,
    passes,
    passes_summary: passesSummary,
    pass_devices: passDevices = [],
    day = null,
}) {
    const { can = {} } = usePage().props;
    const currentMonth = dayjs().format('YYYY-MM');
    const monthStart = dayjs(`${month.month}-01`);
    const monthRange = {
        from: monthStart.format('YYYY-MM-DD'),
        to: monthStart.endOf('month').format('YYYY-MM-DD'),
    };

    const visit = (changes, only) => {
        router.get(
            route('admin.employees.show', employee.id),
            cleanQuery({
                month: filters.month,
                from: filters.from,
                to: filters.to,
                type: filters.type,
                device_id: filters.device_id,
                ...changes,
            }),
            { only, preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const subtitle = [employee.department?.name, employee.group?.name].filter(Boolean).join(' · ');

    return (
        <AdminLayout title={employee.name}>
            <BackLink href={route('admin.employees.index')} label="Xodimlar" />
            <ShowHeader
                title={employee.name}
                subtitle={subtitle || null}
                editHref={route('admin.employees.edit', employee.id)}
                deleteHref={route('admin.employees.destroy', employee.id)}
            />

            <div className="grid items-start gap-5 xl:grid-cols-[320px_minmax(0,1fr)]">
                <aside className="space-y-5">
                    <ProfileCard employee={employee} canGroups={Boolean(can.groups)} />
                    <EmployeeDevicesPanel employeeId={employee.id} devices={employee.devices ?? []} />
                </aside>

                <div className="min-w-0 space-y-5">
                    <MonthCalendar
                        month={month}
                        currentMonth={currentMonth}
                        // Oy almashsa kirish-chiqishlar ham shu oyga o‘tadi.
                        onMonthChange={(value) => visit(
                            { month: value, from: null, to: null },
                            ['month', 'passes', 'passes_summary', 'filters'],
                        )}
                        onDayOpen={(date) => visit({ day: date }, ['day'])}
                    />
                    <PassesPanel
                        passes={passes}
                        summary={passesSummary}
                        devices={passDevices}
                        filters={filters}
                        monthRange={monthRange}
                        onChange={(changes) => visit(changes, ['passes', 'passes_summary', 'filters'])}
                    />
                    <NotesPanel employeeId={employee.id} notes={notes} canManage={Boolean(can.notes)} />
                </div>
            </div>

            <DayDrawer
                day={day}
                open={Boolean(day)}
                canAddNote={Boolean(can.notes)}
                onClose={() => visit({ day: null }, ['day'])}
                onAddNote={(employeeId, date) => router.visit(
                    `${route('admin.notes.create')}?employee_id=${employeeId}&dt=${date}`,
                )}
            />
        </AdminLayout>
    );
}
