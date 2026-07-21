import AdminLayout from '../../../Layouts/AdminLayout';
import ShowPage from '../../../Components/ShowPage';

export default function Show({ department }) {
    return (
        <AdminLayout title="Bo'lim">
            <ShowPage
                backHref="/admin/departments"
                title={department.name}
                editHref={`/admin/departments/${department.id}/edit`}
                deleteHref={`/admin/departments/${department.id}`}
                maxWidth="max-w-md"
            >
                <p className="text-sm leading-6 text-ink-muted">
                    Bu bo&apos;lim tashkilot strukturasini boshqarish uchun ishlatiladi.
                </p>
            </ShowPage>
        </AdminLayout>
    );
}
