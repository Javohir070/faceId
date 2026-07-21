import { Tag } from 'antd';
import AdminLayout from '../../../Layouts/AdminLayout';
import ShowPage from '../../../Components/ShowPage';

export default function Show({ user }) {
    return (
        <AdminLayout title="Foydalanuvchi">
            <ShowPage
                backHref="/admin/users"
                title={user.name}
                subtitle={user.email}
                editHref={`/admin/users/${user.id}/edit`}
                deleteHref={`/admin/users/${user.id}`}
                maxWidth="max-w-2xl"
            >
                <div>
                    <h2 className="mb-3 text-xs font-extrabold uppercase tracking-wide text-ink-subtle">
                        Rollar
                    </h2>
                    <div className="flex flex-wrap gap-2">
                        {user.roles?.length
                            ? user.roles.map((role) => (
                                <Tag key={role.id} color="processing">{role.name}</Tag>
                            ))
                            : <span className="text-sm font-semibold text-ink-subtle">—</span>}
                    </div>
                </div>
            </ShowPage>
        </AdminLayout>
    );
}
