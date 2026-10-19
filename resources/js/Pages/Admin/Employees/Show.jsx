import { Button } from 'antd';
import AdminLayout from '../../../Layouts/AdminLayout';
import { route } from '../../../routes';
import EmployeeDevicesPanel from '../../../Components/EmployeeDevicesPanel';
import ShowPage from '../../../Components/ShowPage';

function DetailRow({ label, children }) {
    return (
        <div className="grid grid-cols-[140px_1fr] gap-3 border-b border-line/60 py-3 last:border-b-0">
            <dt className="text-xs font-extrabold uppercase tracking-wide text-ink-subtle">{label}</dt>
            <dd className="text-sm font-semibold text-ink">{children}</dd>
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
                            <DetailRow label="Guruh">{employee.group?.name || '—'}</DetailRow>
                            <DetailRow label="Lavozim">{employee.position || '—'}</DetailRow>
                            <DetailRow label="Jins">{employee.gender_label || '—'}</DetailRow>
                            <DetailRow label="Davlat">{employee.country_name || '—'}</DetailRow>
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
