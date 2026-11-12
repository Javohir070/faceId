import { router, usePage } from '@inertiajs/react';
import { Empty, Segmented, Select } from 'antd';
import { useEffect, useMemo, useState } from 'react';
import PageHeader from '../../../Components/PageHeader';
import UnitCard from '../../../Components/Units/UnitCard';
import UnitPanel from '../../../Components/Units/UnitPanel';
import AdminLayout from '../../../Layouts/AdminLayout';
import useInertiaPoll from '../../../hooks/useInertiaPoll';
import { route } from '../../../routes';

/** Filtr → kartochkada qoladigan xodimlar. `count` — tugma yonidagi son (board.counts dan). */
const FILTERS = [
    { value: 'all', label: 'Hammasi', count: 'total', match: () => true },
    { value: 'inside', label: 'Binoda', count: 'inside', match: (employee) => employee.inside },
    { value: 'outside', label: 'Binoda emas', count: 'outside', match: (employee) => !employee.inside },
    { value: 'excused', label: 'Sabablilar', count: 'excused', match: (employee) => employee.excused },
    { value: 'unexcused', label: 'Sababsiz', count: 'unexcused', match: (employee) => employee.unexcused },
];

export default function Index({ board, filters = {}, departments = [], departmentLocked = false }) {
    const { can = {} } = usePage().props;
    const [filter, setFilter] = useState('all');
    const [openKey, setOpenKey] = useState(null);
    const canOpenProfile = Boolean(can.employees);

    // Bo‘lim URL'da turadi: 30 soniyalik yangilanish joriy URL'ni qayta so‘raydi, tanlov yo‘qolmaydi.
    useInertiaPoll(['board'], 30000);

    const changeDepartment = (value) => {
        setOpenKey(null);
        router.get(route('admin.units.index'), value ? { department_id: value } : {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    // Panel sahifadagi `board` dan o‘qiydi: yangilanishda u ham yangilanadi.
    const openUnit = openKey ? board.units.find((unit) => unit.key === openKey) ?? null : null;

    // Yangilanishdan keyin guruh ro‘yxatdan chiqib ketsa (masalan xodimlari boshqa guruhga o‘tkazilgan), panel yopiladi.
    useEffect(() => {
        if (openKey && !openUnit) {
            setOpenKey(null);
        }
    }, [openKey, openUnit]);

    const panelState = {
        isOpen: openUnit !== null,
        loading: false,
        panel: openUnit,
        close: () => setOpenKey(null),
    };

    const visible = useMemo(() => {
        const { match } = FILTERS.find((item) => item.value === filter) ?? FILTERS[0];

        return board.units
            .map((unit) => ({ unit, employees: unit.employees.filter(match) }))
            .filter((item) => item.employees.length > 0);
    }, [board, filter]);

    return (
        <AdminLayout title="Tarkibiy bo'linmalar">
            <PageHeader
                title="Tarkibiy bo'linmalar"
                subtitle="Bugun kim binoda. Kartochkani bosing — guruh tafsiloti, rasmni bosing — xodim ma’lumoti chiqadi. Sahifa har 30 soniyada yangilanadi."
            />

            <div className="flex flex-wrap items-center gap-3">
                <div className="max-w-full overflow-x-auto pb-1">
                    <Segmented
                        value={filter}
                        onChange={setFilter}
                        options={FILTERS.map((item) => ({
                            value: item.value,
                            label: (
                                <span className="whitespace-nowrap px-1 font-bold">
                                    {item.label} <span className="tabular-nums text-ink-muted">{board.counts[item.count]}</span>
                                </span>
                            ),
                        }))}
                    />
                </div>
                {/* Bo‘limga cheklangan foydalanuvchida bitta bo‘lim bor — tanlov kerak emas. */}
                {!departmentLocked && (
                    <Select
                        allowClear
                        showSearch
                        placeholder="Bo‘lim"
                        aria-label="Bo‘lim bo‘yicha filtr"
                        optionFilterProp="label"
                        value={filters.department_id ?? undefined}
                        options={departments.map((department) => ({ value: department.id, label: department.name }))}
                        onChange={(value) => changeDepartment(value ?? null)}
                        className="mb-1 w-full sm:w-64"
                    />
                )}
            </div>

            {visible.length > 0 ? (
                <div className="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                    {visible.map(({ unit, employees }) => (
                        <UnitCard
                            key={unit.key}
                            unit={unit}
                            employees={employees}
                            canOpenProfile={canOpenProfile}
                            onOpen={() => setOpenKey(unit.key)}
                        />
                    ))}
                </div>
            ) : (
                <div className="mt-4 rounded-xl border border-line bg-surface py-12 shadow-ambient">
                    <Empty description="Ko‘rsatiladigan xodim yo‘q" />
                </div>
            )}

            <UnitPanel state={panelState} canOpenProfile={canOpenProfile} />
        </AdminLayout>
    );
}
