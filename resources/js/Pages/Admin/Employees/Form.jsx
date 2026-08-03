import { useForm } from '@inertiajs/react';
import { Button, Input, Select, Space } from 'antd';
import { ReloadOutlined } from '@ant-design/icons';
import AdminLayout from '../../../Layouts/AdminLayout';
import { route } from '../../../routes';
import EmployeeDevicesPanel from '../../../Components/EmployeeDevicesPanel';
import FaceCaptureField from '../../../Components/FaceCaptureField';
import FormCard from '../../../Components/FormCard';
import FormField from '../../../Components/FormField';

function generateCardNumber() {
    return String(Math.floor(10000000 + Math.random() * 90000000));
}

export default function Form({ employee, suggestedCard = '', departments = [], schedules = [] }) {
    const isEdit = !!employee;
    const linkedDevices = employee?.devices ?? [];
    const cardLocked = isEdit && linkedDevices.length > 0;

    const { data, setData, post, processing, errors } = useForm({
        name: employee?.name || '',
        card: employee?.card || suggestedCard || generateCardNumber(),
        gender: employee?.gender ?? null,
        position: employee?.position || '',
        department_id: employee?.department_id || null,
        schedule_id: employee?.schedule_id || null,
        valid_from_dt: employee?.valid_from_dt || '',
        valid_to_dt: employee?.valid_to_dt || '',
        face: null,
        remove_face: false,
        _method: isEdit ? 'put' : undefined,
    });

    const submit = (e) => {
        e.preventDefault();
        if (isEdit) {
            post(route('admin.employees.update', employee.id), { forceFormData: true });
        } else {
            post(route('admin.employees.store'), { forceFormData: true });
        }
    };

    return (
        <AdminLayout title={isEdit ? 'Xodimni tahrirlash' : "Xodim qo'shish"}>
            <FormCard
                title={isEdit ? 'Xodimni tahrirlash' : 'Yangi xodim'}
                subtitle="Chapda ma'lumotlar, o'ngda yuz rasmi — ochiq va qulay."
                backHref={route('admin.employees.index')}
                onSubmit={submit}
                processing={processing}
                cancelHref={route('admin.employees.index')}
                width="full"
            >
                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(320px,420px)] xl:items-start">
                    <div className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField label="F.I.Sh." error={errors.name} required>
                                <Input
                                    size="large"
                                    value={data.name}
                                    status={errors.name ? 'error' : undefined}
                                    onChange={(e) => setData('name', e.target.value)}
                                />
                            </FormField>

                            <FormField
                                label="Karta"
                                error={errors.card}
                                required
                                hint={cardLocked
                                    ? 'Karta raqamini o‘zgartirish uchun avval xodimni qurilmalardan o‘chiring.'
                                    : undefined}
                            >
                                <Space.Compact className="w-full">
                                    <Input
                                        size="large"
                                        value={data.card}
                                        status={errors.card ? 'error' : undefined}
                                        inputMode="numeric"
                                        maxLength={14}
                                        placeholder="1–14 xonali son"
                                        disabled={cardLocked}
                                        onChange={(e) => {
                                            const digits = e.target.value.replace(/\D/g, '').slice(0, 14);
                                            setData('card', digits);
                                        }}
                                    />
                                    <Button
                                        size="large"
                                        icon={<ReloadOutlined />}
                                        title="Yangi raqam generatsiya qilish"
                                        disabled={cardLocked}
                                        onClick={() => setData('card', generateCardNumber())}
                                    />
                                </Space.Compact>
                            </FormField>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField label="Jins" error={errors.gender} required>
                                <Select
                                    className="w-full"
                                    size="large"
                                    placeholder="Jins tanlang"
                                    value={data.gender}
                                    status={errors.gender ? 'error' : undefined}
                                    options={[
                                        { value: 1, label: 'Erkak' },
                                        { value: 2, label: 'Ayol' },
                                    ]}
                                    onChange={(value) => setData('gender', value)}
                                />
                            </FormField>

                            <FormField label="Lavozim" error={errors.position}>
                                <Input
                                    size="large"
                                    value={data.position}
                                    status={errors.position ? 'error' : undefined}
                                    placeholder="Masalan: Operator"
                                    onChange={(e) => setData('position', e.target.value)}
                                />
                            </FormField>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField label="Bo'lim" error={errors.department_id} required>
                                <Select
                                    className="w-full"
                                    size="large"
                                    placeholder="Bo'lim tanlang"
                                    value={data.department_id}
                                    status={errors.department_id ? 'error' : undefined}
                                    options={departments.map((department) => ({
                                        value: department.id,
                                        label: department.name,
                                    }))}
                                    onChange={(value) => setData('department_id', value)}
                                />
                            </FormField>

                            <FormField label="Jadval" error={errors.schedule_id} required>
                                <Select
                                    className="w-full"
                                    size="large"
                                    placeholder="Jadval tanlang"
                                    value={data.schedule_id}
                                    status={errors.schedule_id ? 'error' : undefined}
                                    options={schedules.map((schedule) => ({
                                        value: schedule.id,
                                        label: schedule.name,
                                    }))}
                                    onChange={(value) => setData('schedule_id', value)}
                                />
                            </FormField>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField label="Amal qilish boshlanishi" error={errors.valid_from_dt} required>
                                <Input
                                    size="large"
                                    type="datetime-local"
                                    value={data.valid_from_dt}
                                    status={errors.valid_from_dt ? 'error' : undefined}
                                    onChange={(e) => setData('valid_from_dt', e.target.value)}
                                />
                            </FormField>

                            <FormField label="Amal qilish tugashi" error={errors.valid_to_dt} required>
                                <Input
                                    size="large"
                                    type="datetime-local"
                                    value={data.valid_to_dt}
                                    status={errors.valid_to_dt ? 'error' : undefined}
                                    onChange={(e) => setData('valid_to_dt', e.target.value)}
                                />
                            </FormField>
                        </div>
                    </div>

                    <aside className="rounded-2xl border border-line bg-surface-muted/50 p-5 xl:sticky xl:top-4">
                        <FaceCaptureField
                            value={data.face}
                            existingPreview={employee?.face_preview || null}
                            removeFace={data.remove_face}
                            required={!isEdit}
                            error={errors.face}
                            maxBytes={1024 * 1024}
                            onChange={(file) => setData('face', file)}
                            onRemoveChange={(remove) => setData('remove_face', remove)}
                        />
                    </aside>
                </div>
            </FormCard>

            {isEdit ? (
                <div className="mt-6">
                    <EmployeeDevicesPanel
                        employeeId={employee.id}
                        devices={linkedDevices}
                    />
                </div>
            ) : null}
        </AdminLayout>
    );
}
