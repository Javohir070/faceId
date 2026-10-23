import { DownloadOutlined, LeftOutlined, PlusOutlined, RightOutlined, SearchOutlined } from '@ant-design/icons';
import { router, usePage } from '@inertiajs/react';
import { Button, Input, Segmented, Select, Tabs } from 'antd';
import { useEffect, useMemo, useState } from 'react';
import AttendanceGrid from '../../../Components/Attendance/AttendanceGrid';
import AttendanceStats from '../../../Components/Attendance/AttendanceStats';
import DayDrawer from '../../../Components/Attendance/DayDrawer';
import NoteModal from '../../../Components/Attendance/NoteModal';
import NotesTab from '../../../Components/Attendance/NotesTab';
import PageHeader from '../../../Components/PageHeader';
import Pagination from '../../../Components/Pagination';
import AdminLayout from '../../../Layouts/AdminLayout';
import { route } from '../../../routes';

const PERIOD_OPTIONS = [
    { label: 'Hafta', value: 'week' },
    { label: '2 hafta', value: '2weeks' },
    { label: 'Oy', value: 'month' },
];

const LEGEND = [
    { label: 'O‘z vaqtida', className: 'bg-positive/10' },
    { label: 'Kech kelgan', className: 'bg-caution/10' },
    { label: 'Sababli', className: 'bg-surface ring-1 ring-inset ring-positive' },
    { label: 'Kelmagan', className: 'bg-negative/10' },
];

/** Bo‘sh qiymatlarni va standart tabni so‘rovdan olib tashlaydi. */
function cleanQuery(query) {
    const result = {};
    Object.entries(query).forEach(([key, value]) => {
        if (value === null || value === undefined || value === '' || (key === 'tab' && value === 'attendance')) {
            return;
        }
        result[key] = value;
    });

    return result;
}

export default function Index({
    period,
    days,
    filters,
    stats,
    rows,
    department_counts: departmentCounts,
    day,
    notes,
    departments = [],
    groups = [],
    employees = [],
    types = [],
}) {
    const { can = {} } = usePage().props;
    const [search, setSearch] = useState(filters.search || '');
    const [noteModal, setNoteModal] = useState({ open: false, prefill: null });
    const page = rows?.current_page > 1 ? rows.current_page : null;
    const isNotesTab = filters.tab === 'notes';

    const visit = (changes, options = {}) => {
        router.get(route('admin.attendance.index'), cleanQuery({ ...filters, page, ...changes }), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            ...options,
        });
    };

    useEffect(() => {
        if (search === (filters.search || '')) {
            return undefined;
        }
        const timer = window.setTimeout(() => visit({ search: search || null, page: null }), 400);

        return () => window.clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const groupOptions = useMemo(
        () => groups
            .filter((group) => !filters.department_id || group.department_id === filters.department_id)
            .map((group) => ({ value: group.id, label: group.name })),
        [groups, filters.department_id],
    );

    const exportHref = () => {
        const query = new URLSearchParams(cleanQuery({ ...filters, tab: null }));
        const qs = query.toString();

        return qs ? `${route('admin.attendance.export')}?${qs}` : route('admin.attendance.export');
    };

    const openDay = (row, cell) => {
        visit({ day_employee_id: row.id, day_date: cell.date }, { only: ['day'] });
    };

    const closeDay = () => visit({}, { only: ['day'] });

    const openNoteModal = (employeeId = null, dt = '') => {
        setNoteModal({ open: true, prefill: { employee_id: employeeId, dt } });
    };

    return (
        <AdminLayout title="Davomat">
            <PageHeader
                title="Davomat"
                subtitle="Har katakda: yuqorida kelgan, pastda ketgan vaqt"
                actions={(
                    <div className="flex flex-wrap gap-2">
                        <Button size="large" icon={<DownloadOutlined />} href={exportHref()} className="!font-bold">
                            Hisobot
                        </Button>
                        {can.notes && (
                            <Button
                                type="primary"
                                size="large"
                                icon={<PlusOutlined />}
                                className="!font-bold"
                                onClick={() => openNoteModal()}
                            >
                                Ariza berish
                            </Button>
                        )}
                    </div>
                )}
            />

            <section className="mb-5 flex flex-wrap items-center gap-3 rounded-xl border border-line bg-surface p-4">
                <div className="flex items-center gap-2">
                    <Button
                        icon={<LeftOutlined />}
                        aria-label="Oldingi davr"
                        onClick={() => visit({ date: period.prev, page: null })}
                    />
                    <span className="min-w-[180px] rounded-lg border border-line px-4 py-1.5 text-center font-bold text-ink">
                        {period.label}
                    </span>
                    <Button
                        icon={<RightOutlined />}
                        aria-label="Keyingi davr"
                        onClick={() => visit({ date: period.next, page: null })}
                    />
                </div>
                <Segmented
                    options={PERIOD_OPTIONS}
                    value={period.type}
                    onChange={(value) => visit({ period: value, page: null })}
                />
                <Input
                    allowClear
                    className="!w-full sm:!w-auto sm:min-w-[200px] sm:flex-1"
                    prefix={<SearchOutlined className="text-ink-subtle" />}
                    placeholder="Ism yoki karta bo‘yicha qidirish"
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                />
                <Select
                    allowClear
                    className="w-full sm:w-44"
                    placeholder="Bo‘lim"
                    value={filters.department_id}
                    options={departments.map((department) => ({ value: department.id, label: department.name }))}
                    onChange={(value) => visit({ department_id: value ?? null, group_id: null, page: null })}
                />
                <Select
                    allowClear
                    className="w-full sm:w-44"
                    placeholder="Guruh"
                    value={filters.group_id}
                    options={groupOptions}
                    onChange={(value) => visit({ group_id: value ?? null, page: null })}
                />
            </section>

            <Tabs
                activeKey={isNotesTab ? 'notes' : 'attendance'}
                onChange={(key) => visit({ tab: key, page: null }, { only: ['notes', 'filters'] })}
                items={[
                    { key: 'attendance', label: 'Davomat' },
                    { key: 'notes', label: 'Arizalar' },
                ]}
            />

            {isNotesTab ? (
                <section className="rounded-xl border border-line bg-surface p-5">
                    <NotesTab notes={notes} canManage={Boolean(can.notes)} />
                </section>
            ) : (
                <div className="space-y-5">
                    <AttendanceStats stats={stats} />

                    <section className="rounded-xl border border-line bg-surface p-5">
                        <AttendanceGrid
                            days={days}
                            rows={rows?.data ?? []}
                            departmentCounts={departmentCounts}
                            onCellClick={openDay}
                        />
                        <div className="mt-4 flex flex-wrap items-start justify-between gap-4 border-t border-line pt-4">
                            <div className="space-y-2 text-sm text-ink-muted">
                                <div className="flex flex-wrap items-center gap-4">
                                    {LEGEND.map((item) => (
                                        <span key={item.label} className="flex items-center gap-2 font-semibold text-ink">
                                            <span className={`inline-block h-4 w-4 rounded ${item.className}`} aria-hidden="true" />
                                            {item.label}
                                        </span>
                                    ))}
                                    <span>· Ishladi — tushlik (1 soat) chegirib</span>
                                </div>
                                <p>Kunni bosing — o‘sha kungi kirish-chiqishlar va arizalar ochiladi.</p>
                            </div>
                            <Pagination links={rows?.links ?? []} total={rows?.total} perPage={rows?.per_page} />
                        </div>
                    </section>
                </div>
            )}

            <DayDrawer
                day={day}
                open={Boolean(day)}
                canAddNote={Boolean(can.notes)}
                onClose={closeDay}
                onAddNote={(employeeId, dt) => openNoteModal(employeeId, dt)}
            />

            <NoteModal
                open={noteModal.open}
                prefill={noteModal.prefill}
                employees={employees}
                types={types}
                onClose={() => setNoteModal({ open: false, prefill: null })}
            />
        </AdminLayout>
    );
}
