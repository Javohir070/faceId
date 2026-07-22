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

export default function Show({ schedule }) {
    return (
        <AdminLayout title="Jadval">
            <ShowPage
                backHref={route('admin.schedules.index')}
                title={schedule.name}
                editHref={route('admin.schedules.edit', schedule.id)}
                deleteHref={route('admin.schedules.destroy', schedule.id)}
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
