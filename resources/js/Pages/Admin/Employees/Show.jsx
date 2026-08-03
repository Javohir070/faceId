import { router } from '@inertiajs/react';
import { App as AntApp, Button, Checkbox, Input, Select } from 'antd';
import { useMemo, useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import { route } from '../../../routes';
import ShowPage from '../../../Components/ShowPage';

function DetailRow({ label, children }) {
    return (
        <div className="grid grid-cols-[140px_1fr] gap-3 border-b border-line/60 py-3 last:border-b-0">
            <dt className="text-xs font-extrabold uppercase tracking-wide text-ink-subtle">{label}</dt>
            <dd className="text-sm font-semibold text-ink">{children}</dd>
        </div>
    );
}

function EmployeeDevicesPanel({ employeeId, devices }) {
    const { modal } = AntApp.useApp();
    const [selectedIds, setSelectedIds] = useState([]);
    const [search, setSearch] = useState('');
    const [pageSize, setPageSize] = useState(50);
    const [removing, setRemoving] = useState(false);

    const filtered = useMemo(() => {
        const query = search.trim().toLowerCase();

        if (!query) {
            return devices;
        }

        return devices.filter((device) => (
            device.name.toLowerCase().includes(query)
            || device.ip_address.toLowerCase().includes(query)
        ));
    }, [devices, search]);

    const visible = filtered.slice(0, pageSize);
    const allVisibleSelected = visible.length > 0 && visible.every((device) => selectedIds.includes(device.id));
    const someVisibleSelected = visible.some((device) => selectedIds.includes(device.id)) && !allVisibleSelected;

    const toggleAll = (checked) => {
        const visibleIds = visible.map((device) => device.id);
        setSelectedIds((prev) => (
            checked
                ? [...new Set([...prev, ...visibleIds])]
                : prev.filter((id) => !visibleIds.includes(id))
        ));
    };

    const toggleOne = (id, checked) => {
        setSelectedIds((prev) => (
            checked ? [...prev, id] : prev.filter((item) => item !== id)
        ));
    };

    const removeSelected = () => {
        modal.confirm({
            title: 'Qurilmalardan o‘chirish',
            content: 'Tanlangan qurilmalardan xodim ma’lumotlari o‘chiriladi. Davom etasizmi?',
            okText: 'Ha, o‘chirish',
            cancelText: 'Bekor qilish',
            okButtonProps: { danger: true },
            onOk: () => new Promise((resolve, reject) => {
                setRemoving(true);
                router.post(route('admin.employees.remove-devices', employeeId), {
                    device_ids: selectedIds,
                }, {
                    preserveScroll: true,
                    onSuccess: () => {
                        setSelectedIds([]);
                        resolve();
                    },
                    onError: reject,
                    onFinish: () => setRemoving(false),
                });
            }),
        });
    };

    return (
        <div className="rounded-xl border border-line bg-surface shadow-ambient">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-line px-4 py-3">
                <h2 className="text-base font-extrabold text-ink">Qurilmalar</h2>
                <Button
                    danger
                    type="primary"
                    className="!font-bold"
                    disabled={!selectedIds.length}
                    loading={removing}
                    onClick={removeSelected}
                >
                    O'chirish
                </Button>
            </div>

            <div className="flex flex-wrap items-center gap-3 border-b border-line px-4 py-3">
                <div className="flex items-center gap-2 text-sm text-ink-muted">
                    <span>Ko'rsatish</span>
                    <Select
                        value={pageSize}
                        options={[25, 50, 100].map((size) => ({ value: size, label: String(size) }))}
                        onChange={setPageSize}
                        className="min-w-20"
                    />
                    <span>ta yozuv</span>
                </div>
                <div className="ml-auto flex items-center gap-2">
                    <span className="text-sm text-ink-muted">Qidiruv:</span>
                    <Input
                        allowClear
                        placeholder="Nima qidiryapsiz?"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="min-w-[220px]"
                    />
                </div>
            </div>

            <div className="overflow-x-auto">
                <table className="data-table devices-table min-w-full">
                    <thead>
                        <tr>
                            <th className="devices-table__checkbox">
                                <Checkbox
                                    checked={allVisibleSelected}
                                    indeterminate={someVisibleSelected}
                                    onChange={(e) => toggleAll(e.target.checked)}
                                    disabled={!visible.length}
                                />
                            </th>
                            <th className="w-12">№</th>
                            <th>Nomi</th>
                        </tr>
                    </thead>
                    <tbody>
                        {visible.length === 0 ? (
                            <tr>
                                <td colSpan={3} className="py-10 text-center text-sm text-ink-muted">
                                    Xodim hali hech qaysi qurilmaga biriktirilmagan.
                                </td>
                            </tr>
                        ) : visible.map((device, index) => (
                            <tr key={device.id}>
                                <td className="devices-table__checkbox">
                                    <Checkbox
                                        checked={selectedIds.includes(device.id)}
                                        onChange={(e) => toggleOne(device.id, e.target.checked)}
                                    />
                                </td>
                                <td className="font-medium text-ink-subtle">{index + 1}</td>
                                <td>
                                    <button
                                        type="button"
                                        className="font-medium text-brand-600 transition hover:text-brand-700 hover:underline"
                                        onClick={() => router.visit(route('admin.devices.show', device.id))}
                                    >
                                        {device.name}
                                    </button>
                                    {!device.is_synced ? (
                                        <span className="ml-2 text-xs font-semibold text-amber-600">
                                            sinxronlanmoqda
                                        </span>
                                    ) : null}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <div className="border-t border-line px-4 py-3 text-sm text-ink-muted">
                1 dan
                {' '}
                {visible.length}
                {' '}
                gacha, jami
                {' '}
                {filtered.length}
                {' '}
                ta yozuv
            </div>
        </div>
    );
}

function EmployeeNotesPanel({ employeeId, notes }) {
    const createHref = `${route('admin.notes.create')}?employee_id=${employeeId}`;

    return (
        <div className="mt-5 rounded-xl border border-line bg-surface shadow-ambient">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-line px-4 py-3">
                <h2 className="text-base font-extrabold text-ink">Izohlar</h2>
                <Button type="primary" className="!font-bold" href={createHref}>
                    Izoh qo‘shish
                </Button>
            </div>

            <div className="overflow-x-auto">
                <table className="data-table min-w-full">
                    <thead>
                        <tr>
                            <th className="w-16">№</th>
                            <th>Tip</th>
                            <th>Sana</th>
                            <th>Izoh</th>
                        </tr>
                    </thead>
                    <tbody>
                        {notes.length === 0 ? (
                            <tr>
                                <td colSpan={4} className="py-10 text-center text-sm text-ink-muted">
                                    Xodim uchun izohlar mavjud emas.
                                </td>
                            </tr>
                        ) : notes.map((note, index) => (
                            <tr key={note.id}>
                                <td className="font-medium text-ink-subtle">{index + 1}</td>
                                <td className="whitespace-nowrap font-semibold text-ink">
                                    {note.type_label}
                                </td>
                                <td className="whitespace-nowrap font-medium text-ink-muted">
                                    {note.dt || '—'}
                                </td>
                                <td className="max-w-xl whitespace-normal font-medium text-ink-muted">
                                    {note.note || '—'}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

export default function Show({ employee, notes = [] }) {
    return (
        <AdminLayout title="Xodim">
            <ShowPage
                backHref={route('admin.employees.index')}
                title={employee.name}
                subtitle={`Karta: ${employee.card}`}
                editHref={route('admin.employees.edit', employee.id)}
                deleteHref={route('admin.employees.destroy', employee.id)}
            >
                <div className="grid gap-5 xl:grid-cols-[minmax(0,360px)_1fr]">
                    <div>
                        {employee.face_preview ? (
                            <img
                                src={employee.face_preview}
                                alt={employee.name}
                                className="mb-4 h-40 w-40 rounded-lg object-cover ring-1 ring-line"
                            />
                        ) : null}

                        <dl>
                            <DetailRow label="Bo'lim">{employee.department?.name || '—'}</DetailRow>
                            <DetailRow label="Lavozim">{employee.position || '—'}</DetailRow>
                            <DetailRow label="Jins">{employee.gender_label || '—'}</DetailRow>
                            <DetailRow label="Jadval">{employee.schedule?.name || '—'}</DetailRow>
                            <DetailRow label="Boshlanish">{employee.valid_from_dt || '—'}</DetailRow>
                            <DetailRow label="Tugash">{employee.valid_to_dt || '—'}</DetailRow>
                            <DetailRow label="Yuz rasmi">{employee.face_preview ? 'Biriktirilgan' : "Yo'q"}</DetailRow>
                        </dl>
                    </div>

                    <EmployeeDevicesPanel
                        employeeId={employee.id}
                        devices={employee.devices ?? []}
                    />
                </div>

                <EmployeeNotesPanel employeeId={employee.id} notes={notes} />
            </ShowPage>
        </AdminLayout>
    );
}
