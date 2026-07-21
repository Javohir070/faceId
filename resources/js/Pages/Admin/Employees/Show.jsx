import AdminLayout from '../../../Layouts/AdminLayout';
import ShowPage from '../../../Components/ShowPage';

function DetailRow({ label, children }) {
    return (
        <div className="grid grid-cols-[140px_1fr] gap-3 border-b border-line/60 py-3 last:border-b-0">
            <dt className="text-xs font-extrabold uppercase tracking-wide text-ink-subtle">{label}</dt>
            <dd className="text-sm font-semibold text-ink">{children}</dd>
        </div>
    );
}

export default function Show({ employee }) {
    return (
        <AdminLayout title="Xodim">
            <ShowPage
                backHref="/admin/employees"
                title={employee.name}
                subtitle={`Karta: ${employee.card}`}
                editHref={`/admin/employees/${employee.id}/edit`}
                deleteHref={`/admin/employees/${employee.id}`}
                maxWidth="max-w-2xl"
            >
                {employee.face_preview ? (
                    <img
                        src={employee.face_preview}
                        alt={employee.name}
                        className="mb-4 h-40 w-40 rounded-lg object-cover ring-1 ring-line"
                    />
                ) : null}

                <dl>
                    <DetailRow label="Bo'lim">{employee.department?.name || '—'}</DetailRow>
                    <DetailRow label="Jadval">{employee.schedule?.name || '—'}</DetailRow>
                    <DetailRow label="Boshlanish">{employee.valid_from_dt || '—'}</DetailRow>
                    <DetailRow label="Tugash">{employee.valid_to_dt || '—'}</DetailRow>
                    <DetailRow label="Yuz rasmi">{employee.face_preview ? 'Biriktirilgan' : "Yo'q"}</DetailRow>
                </dl>
            </ShowPage>
        </AdminLayout>
    );
}
