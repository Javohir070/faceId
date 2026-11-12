import { useForm } from '@inertiajs/react';
import { Input, Select } from 'antd';
import { LockOutlined } from '@ant-design/icons';
import { useMemo } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import { route } from '../../../routes';
import EmployeeDevicesPanel from '../../../Components/EmployeeDevicesPanel';
import FaceCaptureField from '../../../Components/FaceCaptureField';
import FormCard from '../../../Components/FormCard';
import FormField from '../../../Components/FormField';

// Bo‘lim jadvali faqat shu bo‘lim xodimiga beriladi, umumiy jadval (bo‘limsiz) hammaga.
function isScheduleAvailable(schedule, departmentId) {
    return schedule.department_id === null || schedule.department_id === departmentId;
}

// datetime-local maydoni uchun hozirgi mahalliy vaqt: YYYY-MM-DDTHH:mm.
function currentDateTimeLocal() {
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());

    return now.toISOString().slice(0, 16);
}

export default function Form({
    employee,
    departments = [],
    schedules = [],
    groups = [],
    departmentLocked = false,
}) {
    const isEdit = !!employee;
    // Bo‘limga bog‘langan foydalanuvchi bo‘lim tanlamaydi: xodim doim uning bo‘limiga yoziladi (server ham shuni qiladi).
    const lockedDepartment = departmentLocked ? (departments[0] ?? null) : null;
    const linkedDevices = employee?.devices ?? [];
    const cardLocked = isEdit && linkedDevices.length > 0;

    const { data, setData, post, processing, errors } = useForm({
        // To‘liq ism (name) ni server shu uchtasidan yig‘adi: "Familiya Ism Otasining ismi".
        last_name: employee?.last_name || '',
        first_name: employee?.first_name || '',
        middle_name: employee?.middle_name || '',
        card: employee?.card || '',
        gender: employee?.gender ?? null,
        position: employee?.position || '',
        department_id: employee?.department_id || lockedDepartment?.id || null,
        group_id: employee?.group_id || null,
        schedule_id: employee?.schedule_id || null,
        valid_from_dt: employee?.valid_from_dt || (isEdit ? '' : currentDateTimeLocal()),
        valid_to_dt: employee?.valid_to_dt || '',
        face: null,
        remove_face: false,
        _method: isEdit ? 'put' : undefined,
    });

    const groupOptions = useMemo(() => {
        if (!data.department_id) {
            return [];
        }

        return groups
            .filter((group) => group.department_id === data.department_id)
            .map((group) => ({ value: group.id, label: group.name }));
    }, [groups, data.department_id]);

    const scheduleOptions = useMemo(() => schedules
        .filter((schedule) => isScheduleAvailable(schedule, data.department_id))
        .map((schedule) => ({ value: schedule.id, label: schedule.name })), [schedules, data.department_id]);

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
                        <div className="grid gap-4 sm:grid-cols-3">
                            <FormField label="Familiya" htmlFor="employee-last-name" error={errors.last_name} required>
                                <Input
                                    id="employee-last-name"
                                    size="large"
                                    autoComplete="family-name"
                                    maxLength={80}
                                    placeholder="Masalan: Valiyev"
                                    value={data.last_name}
                                    status={errors.last_name ? 'error' : undefined}
                                    onChange={(e) => setData('last_name', e.target.value)}
                                />
                            </FormField>

                            <FormField label="Ism" htmlFor="employee-first-name" error={errors.first_name} required>
                                <Input
                                    id="employee-first-name"
                                    size="large"
                                    autoComplete="given-name"
                                    maxLength={80}
                                    placeholder="Masalan: Ali"
                                    value={data.first_name}
                                    status={errors.first_name ? 'error' : undefined}
                                    onChange={(e) => setData('first_name', e.target.value)}
                                />
                            </FormField>

                            <FormField
                                label="Otasining ismi"
                                htmlFor="employee-middle-name"
                                error={errors.middle_name}
                                hint="Ixtiyoriy."
                            >
                                <Input
                                    id="employee-middle-name"
                                    size="large"
                                    autoComplete="additional-name"
                                    maxLength={80}
                                    placeholder="Masalan: Akmal o‘g‘li"
                                    value={data.middle_name}
                                    status={errors.middle_name ? 'error' : undefined}
                                    onChange={(e) => setData('middle_name', e.target.value)}
                                />
                            </FormField>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField
                                label="JSHSHIR"
                                error={errors.card}
                                required
                                hint={cardLocked
                                    ? 'JSHSHIRni o‘zgartirish uchun avval xodimni qurilmalardan o‘chiring.'
                                    : undefined}
                            >
                                <Input
                                    size="large"
                                    value={data.card}
                                    status={errors.card ? 'error' : undefined}
                                    inputMode="numeric"
                                    maxLength={14}
                                    placeholder="14 xonali son"
                                    disabled={cardLocked}
                                    onChange={(e) => {
                                        const digits = e.target.value.replace(/\D/g, '').slice(0, 14);
                                        setData('card', digits);
                                    }}
                                />
                            </FormField>

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
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField
                                label="Bo'lim"
                                error={errors.department_id}
                                required
                                hint={departmentLocked
                                    ? (lockedDepartment
                                        ? 'Xodim sizning bo‘limingizga yoziladi.'
                                        : 'Sizga bo‘lim biriktirilmagan. Administratorga murojaat qiling.')
                                    : undefined}
                            >
                                {departmentLocked ? (
                                    <Input
                                        disabled
                                        size="large"
                                        prefix={<LockOutlined aria-hidden="true" />}
                                        value={lockedDepartment?.name ?? '—'}
                                        status={errors.department_id ? 'error' : undefined}
                                    />
                                ) : (
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
                                        onChange={(value) => {
                                            setData({
                                                ...data,
                                                department_id: value,
                                                group_id: null,
                                                schedule_id: schedules.some((schedule) => schedule.id === data.schedule_id
                                                    && isScheduleAvailable(schedule, value))
                                                    ? data.schedule_id
                                                    : null,
                                            });
                                        }}
                                    />
                                )}
                            </FormField>

                            <FormField
                                label="Guruh"
                                error={errors.group_id}
                                hint={data.department_id
                                    ? 'Ixtiyoriy — shu bo‘limdagi guruh.'
                                    : 'Avval bo‘limni tanlang.'}
                            >
                                <Select
                                    allowClear
                                    showSearch
                                    className="w-full"
                                    size="large"
                                    placeholder="Guruh tanlang (ixtiyoriy)"
                                    optionFilterProp="label"
                                    value={data.group_id}
                                    status={errors.group_id ? 'error' : undefined}
                                    disabled={!data.department_id}
                                    options={groupOptions}
                                    onChange={(value) => setData('group_id', value ?? null)}
                                />
                            </FormField>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField label="Jadval" error={errors.schedule_id} required>
                                <Select
                                    className="w-full"
                                    size="large"
                                    placeholder="Jadval tanlang"
                                    value={data.schedule_id}
                                    status={errors.schedule_id ? 'error' : undefined}
                                    options={scheduleOptions}
                                    onChange={(value) => setData('schedule_id', value)}
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
