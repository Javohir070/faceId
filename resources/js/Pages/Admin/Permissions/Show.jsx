import AdminLayout from '../../../Layouts/AdminLayout';
import ShowPage from '../../../Components/ShowPage';

export default function Show({ permission }) {
    return (
        <AdminLayout title="Ruxsat">
            <ShowPage
                backHref="/admin/permissions"
                title={permission.name}
                subtitle={`Guard: ${permission.guard_name}`}
                editHref={`/admin/permissions/${permission.id}/edit`}
                deleteHref={`/admin/permissions/${permission.id}`}
                maxWidth="max-w-md"
            >
                <p className="text-sm leading-6 text-ink-muted">
                    Bu ruxsat rollarga biriktirilganda, foydalanuvchilar tegishli bo‘limga kirishi mumkin.
                </p>
            </ShowPage>
        </AdminLayout>
    );
}
