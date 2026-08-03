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

export default function Show({ group }) {
    return (
        <AdminLayout title="Guruh">
            <ShowPage
                backHref={route('admin.groups.index')}
                title={group.name}
                editHref={route('admin.groups.edit', group.id)}
                deleteHref={route('admin.groups.destroy', group.id)}
            >
                <dl>
                    <DetailRow label="Bo‘lim">{group.department?.name || '—'}</DetailRow>
                    <DetailRow label="Parent">{group.parent?.name || '—'}</DetailRow>
                </dl>
            </ShowPage>
        </AdminLayout>
    );
}
