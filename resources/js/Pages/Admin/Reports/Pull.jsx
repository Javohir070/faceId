import { Link, router, usePage } from '@inertiajs/react';
import { Button, Checkbox, DatePicker, Input, Select } from 'antd';
import dayjs from 'dayjs';
import { useMemo, useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import { route } from '../../../routes';
import PageHeader from '../../../Components/PageHeader';
import StatusTag from '../../../Components/StatusTag';

const reconciliationStatus = {
    ok: { tone: 'success', label: 'Mos' },
    mismatch: { tone: 'danger', label: 'Farq bor' },
    unreachable: { tone: 'neutral', label: 'Javob bermadi' },
};

const countOrDash = (value) => (value === null || value === undefined ? '—' : value);

export default function Pull({ devices = [], categories = [], reconciliations = [] }) {
    const { can = {} } = usePage().props;
    const [recheckingId, setRecheckingId] = useState(null);

    const recheck = (row) => {
        setRecheckingId(row.id);
        router.post(route('admin.reports.reconcile'), {
            device_id: row.device_id,
            date: row.date,
        }, {
            preserveScroll: true,
            onFinish: () => setRecheckingId(null),
        });
    };
    const [selectedIds, setSelectedIds] = useState([]);
    const [search, setSearch] = useState('');
    const [categoryFilter, setCategoryFilter] = useState(null);
    const [dateFrom, setDateFrom] = useState(dayjs().startOf('day'));
    const [dateTo, setDateTo] = useState(dayjs().endOf('day'));
    const [pulling, setPulling] = useState(false);

    const filteredDevices = useMemo(() => {
        let rows = devices;

        if (categoryFilter) {
            rows = rows.filter((device) => device.category?.id === categoryFilter);
        }

        const query = search.trim().toLowerCase();

        if (!query) {
            return rows;
        }

        return rows.filter((device) => [
            device.name,
            device.ip_address,
            device.type_label,
            device.category?.name,
        ].some((value) => String(value || '').toLowerCase().includes(query)));
    }, [devices, categoryFilter, search]);

    const allFilteredSelected = filteredDevices.length > 0
        && filteredDevices.every((device) => selectedIds.includes(device.id));
    const someFilteredSelected = filteredDevices.some((device) => selectedIds.includes(device.id))
        && !allFilteredSelected;

    const toggleOne = (id, checked) => {
        setSelectedIds((prev) => (checked ? [...prev, id] : prev.filter((item) => item !== id)));
    };

    const toggleAll = (checked) => {
        const ids = filteredDevices.map((device) => device.id);
        setSelectedIds((prev) => {
            if (!checked) {
                return prev.filter((id) => !ids.includes(id));
            }

            return [...new Set([...prev, ...ids])];
        });
    };

    const submitPull = () => {
        if (!dateFrom || !dateTo || selectedIds.length === 0) {
            return;
        }

        setPulling(true);
        router.post(route('admin.reports.pull.store'), {
            device_ids: selectedIds,
            date_from: dateFrom.format('YYYY-MM-DD'),
            date_to: dateTo.format('YYYY-MM-DD'),
        }, {
            preserveScroll: true,
            onFinish: () => setPulling(false),
        });
    };

    const canSubmit = selectedIds.length > 0 && dateFrom && dateTo && !pulling
        && !dateTo.isBefore(dateFrom, 'day');

    return (
        <AdminLayout title="Event tortish">
            <PageHeader
                title="Event tortish"
                subtitle="Tanlangan sana oralig‘ida qurilmalardan kirish/chiqish yozuvlarini tortib oladi. Takrorlar bazaga yozilmaydi."
                actions={can.reports ? (
                    <Link
                        href={route('admin.reports.index')}
                        className="inline-flex h-10 items-center rounded-lg border border-line px-4 text-sm font-semibold text-ink hover:bg-surface-muted"
                    >
                        Hisobotlarga qaytish
                    </Link>
                ) : null}
            />

            <div className="mb-5 rounded-xl border border-line bg-surface p-4 shadow-ambient">
                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <DatePicker
                        className="w-full"
                        placeholder="Sana dan"
                        value={dateFrom}
                        onChange={(value) => setDateFrom(value)}
                        allowClear={false}
                    />
                    <DatePicker
                        className="w-full"
                        placeholder="Sana gacha"
                        value={dateTo}
                        onChange={(value) => setDateTo(value)}
                        allowClear={false}
                    />
                    <Select
                        allowClear
                        placeholder="Kategoriya"
                        className="w-full"
                        value={categoryFilter}
                        options={categories.map((item) => ({ value: item.id, label: item.name }))}
                        onChange={setCategoryFilter}
                    />
                    <Button
                        type="primary"
                        className="!font-bold"
                        loading={pulling}
                        disabled={!canSubmit}
                        onClick={submitPull}
                    >
                        {pulling
                            ? 'Tortib olinmoqda...'
                            : `Tortib olish${selectedIds.length ? ` (${selectedIds.length})` : ''}`}
                    </Button>
                </div>
            </div>

            <div className="rounded-xl border border-line bg-surface shadow-ambient">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-line px-4 py-3">
                    <div>
                        <h2 className="text-base font-extrabold text-ink">Qurilmalar</h2>
                        <p className="mt-0.5 text-sm text-ink-muted">
                            Tanlangan:
                            {' '}
                            <span className="font-semibold text-ink">{selectedIds.length}</span>
                        </p>
                    </div>
                    <Input
                        allowClear
                        placeholder="Qidiruv..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="max-w-xs"
                    />
                </div>

                <div className="overflow-x-auto">
                    <table className="data-table devices-table min-w-full">
                        <thead>
                            <tr>
                                <th className="devices-table__checkbox">
                                    <Checkbox
                                        checked={allFilteredSelected}
                                        indeterminate={someFilteredSelected}
                                        onChange={(e) => toggleAll(e.target.checked)}
                                    />
                                </th>
                                <th className="w-12">№</th>
                                <th>Holat</th>
                                <th>IP</th>
                                <th>Nomi</th>
                                <th>Tur</th>
                            </tr>
                        </thead>
                        <tbody>
                            {filteredDevices.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="py-10 text-center text-sm text-ink-muted">
                                        Qurilmalar topilmadi
                                    </td>
                                </tr>
                            ) : filteredDevices.map((device, index) => (
                                <tr
                                    key={device.id}
                                    className={selectedIds.includes(device.id) ? 'bg-brand-50/60' : undefined}
                                >
                                    <td className="devices-table__checkbox">
                                        <Checkbox
                                            checked={selectedIds.includes(device.id)}
                                            disabled={!device.has_credentials}
                                            onChange={(e) => toggleOne(device.id, e.target.checked)}
                                        />
                                    </td>
                                    <td className="font-medium text-ink-subtle">{index + 1}</td>
                                    <td>
                                        <StatusTag tone={device.is_online ? 'success' : 'neutral'}>
                                            {device.is_online ? 'Ulangan' : 'Ulanmagan'}
                                        </StatusTag>
                                    </td>
                                    <td className="font-medium">{device.ip_address}</td>
                                    <td className="font-medium">
                                        <div>{device.name}</div>
                                        {device.category?.name ? (
                                            <div className="text-xs font-medium text-ink-muted">
                                                {device.category.name}
                                            </div>
                                        ) : null}
                                    </td>
                                    <td className="font-medium">{device.type_label || '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            <div className="mt-5 rounded-xl border border-line bg-surface shadow-ambient">
                <div className="border-b border-line px-4 py-3">
                    <h2 className="text-base font-extrabold text-ink">Solishtirish</h2>
                    <p className="mt-0.5 text-sm text-ink-muted">
                        Har kecha 01:00 da qurilmadagi va tizimdagi kirish-chiqishlar solishtiriladi, yetishmagani
                        o‘zi tortib olinadi. Sonlar tizimdagi xodimlar bo‘yicha; begona — terminalda bor, lekin
                        tizimda yo‘q odamlar.
                    </p>
                </div>

                {/* Bo‘sh holat jadvaldan tashqarida: telefonda keng jadval ichida matn qirqilib qolardi. */}
                {reconciliations.length === 0 ? (
                    <p className="px-4 py-10 text-center text-sm text-ink-muted">
                        Hali solishtirish o‘tkazilmagan. Birinchi tekshiruv kechasi 01:00 da bo‘ladi.
                    </p>
                ) : (
                <div className="overflow-x-auto">
                    <table className="data-table min-w-full">
                        <thead>
                            <tr>
                                <th>Sana</th>
                                <th>Qurilma</th>
                                <th className="text-right">Qurilmada</th>
                                <th className="text-right">Tizimda</th>
                                <th className="text-right">Tiklandi</th>
                                <th className="text-right">Begona</th>
                                <th>Holat</th>
                                <th>Tekshirildi</th>
                                <th className="text-right">Amal</th>
                            </tr>
                        </thead>
                        <tbody>
                            {reconciliations.map((row) => {
                                const status = reconciliationStatus[row.status] ?? reconciliationStatus.unreachable;

                                return (
                                    <tr key={row.id}>
                                        <td className="whitespace-nowrap font-medium tabular-nums">{row.date}</td>
                                        <td className="font-medium">{row.device_name || '—'}</td>
                                        <td className="text-right tabular-nums">{countOrDash(row.device_count)}</td>
                                        <td className="text-right tabular-nums">{countOrDash(row.system_count)}</td>
                                        <td className="text-right tabular-nums">{row.recovered || '—'}</td>
                                        <td className="text-right tabular-nums text-ink-muted">{countOrDash(row.strangers)}</td>
                                        <td>
                                            <StatusTag tone={status.tone} title={row.error || undefined}>
                                                {status.label}
                                            </StatusTag>
                                        </td>
                                        <td className="whitespace-nowrap text-sm tabular-nums text-ink-muted">{row.checked_at || '—'}</td>
                                        <td className="whitespace-nowrap text-right">
                                            <Button
                                                size="small"
                                                loading={recheckingId === row.id}
                                                disabled={recheckingId !== null && recheckingId !== row.id}
                                                onClick={() => recheck(row)}
                                            >
                                                Qayta tekshirish
                                            </Button>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
                )}
            </div>
        </AdminLayout>
    );
}
