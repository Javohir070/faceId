import { useForm } from '@inertiajs/react';
import { Checkbox, Input, InputNumber, Select } from 'antd';
import AdminLayout from '../../../Layouts/AdminLayout';
import FormCard from '../../../Components/FormCard';
import FormField from '../../../Components/FormField';

export default function Form({ device, categories = [] }) {
    const isEdit = !!device;
    const { data, setData, post, put, processing, errors } = useForm({
        category_id: device?.category_id || null,
        name: device?.name || '',
        description: device?.description || '',
        ip_address: device?.ip_address || '',
        port: device?.port ?? 80,
        use_https: device?.use_https ?? false,
        user: device?.user || '',
        password: '',
        type: device?.type ?? 0,
        autoconnect: device?.autoconnect ?? false,
    });

    const submit = (e) => {
        e.preventDefault();
        if (isEdit) put(`/admin/devices/${device.id}`);
        else post('/admin/devices');
    };

    return (
        <AdminLayout title={isEdit ? 'Qurilmani tahrirlash' : "Qurilma qo'shish"}>
            <FormCard
                title={isEdit ? 'Qurilmani tahrirlash' : 'Yangi qurilma'}
                subtitle="Ulanish ma'lumotlari va kategoriya ni belgilang."
                backHref="/admin/devices"
                onSubmit={submit}
                processing={processing}
                cancelHref="/admin/devices"
                width="lg"
            >
                <FormField label="Kategoriya" error={errors.category_id} required>
                    <Select
                        className="w-full"
                        placeholder="Kategoriya tanlang"
                        value={data.category_id}
                        status={errors.category_id ? 'error' : undefined}
                        options={categories.map((category) => ({
                            value: category.id,
                            label: category.name,
                        }))}
                        onChange={(value) => setData('category_id', value)}
                    />
                </FormField>

                <div className="grid gap-4 md:grid-cols-2">
                    <FormField label="Nomi" error={errors.name} required>
                        <Input
                            value={data.name}
                            status={errors.name ? 'error' : undefined}
                            onChange={(e) => setData('name', e.target.value)}
                        />
                    </FormField>
                    <FormField label="Turi" error={errors.type} required>
                        <InputNumber
                            className="w-full"
                            min={0}
                            max={255}
                            value={data.type}
                            status={errors.type ? 'error' : undefined}
                            onChange={(value) => setData('type', value ?? 0)}
                        />
                    </FormField>
                </div>

                <FormField label="Tavsif" error={errors.description} required>
                    <Input
                        value={data.description}
                        status={errors.description ? 'error' : undefined}
                        onChange={(e) => setData('description', e.target.value)}
                    />
                </FormField>

                <div className="grid gap-4 md:grid-cols-2">
                    <FormField label="IP manzil" error={errors.ip_address} required>
                        <Input
                            value={data.ip_address}
                            status={errors.ip_address ? 'error' : undefined}
                            onChange={(e) => setData('ip_address', e.target.value)}
                        />
                    </FormField>
                    <FormField label="Port" error={errors.port} required>
                        <InputNumber
                            className="w-full"
                            min={1}
                            max={65535}
                            value={data.port}
                            status={errors.port ? 'error' : undefined}
                            onChange={(value) => setData('port', value ?? 80)}
                        />
                        <p className="mt-1.5 text-xs text-ink-muted">
                            ISAPI odatda 80 (HTTP) yoki 443 (HTTPS). 8000 ba&apos;zan noto&apos;g&apos;ri port.
                        </p>
                    </FormField>
                </div>

                <FormField label="Ulanish protokoli" error={errors.use_https}>
                    <Checkbox
                        checked={data.use_https}
                        onChange={(e) => setData('use_https', e.target.checked)}
                    >
                        HTTPS (qurilma xavfsiz ulanish talab qilsa)
                    </Checkbox>
                </FormField>

                <div className="grid gap-4 md:grid-cols-2">
                    <FormField label="Login" error={errors.user} required>
                        <Input
                            value={data.user}
                            status={errors.user ? 'error' : undefined}
                            onChange={(e) => setData('user', e.target.value)}
                        />
                    </FormField>
                    <FormField
                        label={`Parol ${isEdit ? '(ixtiyoriy)' : ''}`}
                        error={errors.password}
                        required={!isEdit}
                    >
                        <Input.Password
                            value={data.password}
                            status={errors.password ? 'error' : undefined}
                            onChange={(e) => setData('password', e.target.value)}
                        />
                    </FormField>
                </div>

                <FormField label="Avtoulanish" error={errors.autoconnect}>
                    <Checkbox
                        checked={data.autoconnect}
                        onChange={(e) => setData('autoconnect', e.target.checked)}
                    >
                        Qurilmaga avtomatik ulanish
                    </Checkbox>
                </FormField>
            </FormCard>
        </AdminLayout>
    );
}
