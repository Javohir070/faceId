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

export default function Show({ schedule }) {
    return (
        <AdminLayout title="Jadval">
            <ShowPage
                backHref="/admin/schedules"
                title={schedule.name}
                editHref={`/admin/schedules/${schedule.id}/edit`}
                deleteHref={`/admin/schedules/${schedule.id}`}
                maxWidth="max-w-md"
            >
                <dl>
                    <DetailRow label="Kirish vaqti">{schedule.enter_time || '—'}</DetailRow>
                    <DetailRow label="Chiqish vaqti">{schedule.exit_time || '—'}</DetailRow>
                </dl>
            </ShowPage>
        </AdminLayout>
    );
}
