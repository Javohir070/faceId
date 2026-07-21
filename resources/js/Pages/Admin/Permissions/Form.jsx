import { useForm } from '@inertiajs/react';
import { Input } from 'antd';
import AdminLayout from '../../../Layouts/AdminLayout';
import FormCard from '../../../Components/FormCard';
import FormField from '../../../Components/FormField';

export default function Form({ permission }) {
    const isEdit = !!permission;
    const { data, setData, post, put, processing, errors } = useForm({
        name: permission?.name || '',
    });

    const submit = (e) => {
        e.preventDefault();
        if (isEdit) put(`/admin/permissions/${permission.id}`);
        else post('/admin/permissions');
    };

    return (
        <AdminLayout title={isEdit ? 'Ruxsatni tahrirlash' : "Ruxsat qo'shish"}>
            <FormCard
                title={isEdit ? 'Ruxsatni tahrirlash' : "Yangi ruxsat"}
                subtitle="Ruxsat nomini kiriting (masalan: users.view)."
                backHref="/admin/permissions"
                onSubmit={submit}
                processing={processing}
                cancelHref="/admin/permissions"
                width="sm"
            >
                <FormField label="Ruxsat nomi" error={errors.name} required>
                    <Input
                        value={data.name}
                        status={errors.name ? 'error' : undefined}
                        onChange={(e) => setData('name', e.target.value)}
                    />
                </FormField>
            </FormCard>
        </AdminLayout>
    );
}
