import AdminLayout from '../../../Layouts/AdminLayout';
import ShowPage from '../../../Components/ShowPage';

export default function Show({ category }) {
    return (
        <AdminLayout title="Kategoriya">
            <ShowPage
                backHref="/admin/categories"
                title={category.name}
                subtitle={`${category.devices_count ?? 0} ta qurilma`}
                editHref={`/admin/categories/${category.id}/edit`}
                deleteHref={`/admin/categories/${category.id}`}
                maxWidth="max-w-md"
            >
                <p className="text-sm leading-6 text-ink-muted">
                    Bu kategoriya qurilmalarni guruhlash uchun ishlatiladi.
                </p>
            </ShowPage>
        </AdminLayout>
    );
}
