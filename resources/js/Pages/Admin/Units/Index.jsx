import { usePage } from '@inertiajs/react';
import { Empty, Segmented } from 'antd';
import { useEffect, useMemo, useState } from 'react';
import PageHeader from '../../../Components/PageHeader';
import UnitCard from '../../../Components/Units/UnitCard';
import UnitPanel from '../../../Components/Units/UnitPanel';
import AdminLayout from '../../../Layouts/AdminLayout';
import useInertiaPoll from '../../../hooks/useInertiaPoll';

/** Filtr → kartochkada qoladigan xodimlar. `count` — tugma yonidagi son (board.counts dan). */
const FILTERS = [
    { value: 'all', label: 'Hammasi', count: 'total', match: () => true },
    { value: 'inside', label: 'Binoda', count: 'inside', match: (employee) => employee.inside },
    { value: 'outside', label: 'Binoda emas', count: 'outside', match: (employee) => !employee.inside },
    { value: 'excused', label: 'Sabablilar', count: 'excused', match: (employee) => employee.excused },
    { value: 'unexcused', label: 'Sababsiz', count: 'unexcused', match: (employee) => employee.unexcused },
];

export default function Index({ board }) {
    const { can = {} } = usePage().props;
    const [filter, setFilter] = useState('all');
    const [openKey, setOpenKey] = useState(null);
    const canOpenProfile = Boolean(can.employees);

    useInertiaPoll(['board'], 30000);

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
