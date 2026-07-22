import { useForm } from '@inertiajs/react';
import { Input } from 'antd';
import AdminLayout from '../../../Layouts/AdminLayout';
import { route } from '../../../routes';
import FormCard from '../../../Components/FormCard';
import FormField from '../../../Components/FormField';

export default function Form({ permission }) {
    const isEdit = !!permission;
    const { data, setData, post, put, processing, errors } = useForm({
        name: permission?.name || '',
    });

    const submit = (e) => {
        e.preventDefault();
        if (isEdit) put(route('admin.permissions.update', permission.id));
        else post(route('admin.permissions.store'));
    };

    return (
        <AdminLayout title={isEdit ? 'Ruxsatni tahrirlash' : "Ruxsat qo'shish"}>
            <FormCard
                title={isEdit ? 'Ruxsatni tahrirlash' : "Yangi ruxsat"}
                subtitle="Ruxsat nomini kiriting (masalan: users.view)."
                backHref={route('admin.permissions.index')}
                onSubmit={submit}
                processing={processing}
                cancelHref={route('admin.permissions.index')}
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
