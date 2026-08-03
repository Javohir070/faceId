import AdminLayout from '../../../Layouts/AdminLayout';
import { route } from '../../../routes';
import ShowPage from '../../../Components/ShowPage';
import StatusTag from '../../../Components/StatusTag';

export default function Show({ user }) {
    return (
        <AdminLayout title="Foydalanuvchi">
            <ShowPage
                backHref={route('admin.users.index')}
                title={user.name}
                subtitle={user.email}
                editHref={route('admin.users.edit', user.id)}
                deleteHref={route('admin.users.destroy', user.id)}
                maxWidth="max-w-2xl"
            >
                <div className="space-y-6">
                    <div>
                        <h2 className="mb-3 text-xs font-extrabold uppercase tracking-wide text-ink-subtle">
                            Bo‘lim
                        </h2>
                        <p className="text-sm font-semibold text-ink">
                            {user.department?.name || '—'}
                        </p>
                    </div>
                    <div>
                        <h2 className="mb-3 text-xs font-extrabold uppercase tracking-wide text-ink-subtle">
                            Rollar
                        </h2>
                        <div className="flex flex-wrap gap-2">
                            {user.roles?.length
                                ? user.roles.map((role) => (
                                    <StatusTag key={role.id} tone="info">{role.name}</StatusTag>
                                ))
                                : <span className="text-sm font-semibold text-ink-subtle">—</span>}
                        </div>
                    </div>
                </div>
            </ShowPage>
        </AdminLayout>
    );
}
