import { useForm } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';
import InputError from '../../Components/InputError';
import PageHeader from '../../Components/PageHeader';
import { DeleteOutlined, KeyOutlined, UserOutlined } from '@ant-design/icons';
import { App as AntApp, Button, Input } from 'antd';

export default function Edit({ user }) {
    const { modal } = AntApp.useApp();
    const profileForm = useForm({
        name: user.name || '',
        email: user.email || '',
    });

    const passwordForm = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const deleteForm = useForm({
        password: '',
    });

    return (
        <AdminLayout title="Profil">
            <PageHeader title="Profil sozlamalari" subtitle="Shaxsiy ma’lumotlaringiz va hisob xavfsizligini boshqaring." />

            <div className="grid items-start gap-5 xl:grid-cols-2">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        profileForm.patch('/profile');
                    }}
                    className="card space-y-4"
                >
                    <div className="flex items-start gap-3 border-b border-line pb-4">
                        <span className="flex h-9 w-9 items-center justify-center rounded-md bg-brand-50 text-brand-700"><UserOutlined /></span>
                        <div>
                            <h2 className="text-base font-extrabold text-ink">Profil ma’lumotlari</h2>
                            <p className="mt-1 text-sm text-ink-muted">Ism va elektron pochta manzilingizni yangilang.</p>
                        </div>
                    </div>
                    <div>
                        <label htmlFor="profile_name" className="label">Ism</label>
                        <Input id="profile_name" value={profileForm.data.name} status={profileForm.errors.name ? 'error' : undefined} onChange={(e) => profileForm.setData('name', e.target.value)} />
                        <InputError message={profileForm.errors.name} />
                    </div>
                    <div>
                        <label htmlFor="profile_email" className="label">Elektron pochta</label>
                        <Input id="profile_email" type="email" value={profileForm.data.email} status={profileForm.errors.email ? 'error' : undefined} onChange={(e) => profileForm.setData('email', e.target.value)} />
                        <InputError message={profileForm.errors.email} />
                    </div>
                    <Button type="primary" htmlType="submit" loading={profileForm.processing}>Saqlash</Button>
                </form>

                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        passwordForm.put('/password', {
                            onSuccess: () => passwordForm.reset(),
                        });
                    }}
                    className="card space-y-4"
                >
                    <div className="flex items-start gap-3 border-b border-line pb-4">
                        <span className="flex h-9 w-9 items-center justify-center rounded-md bg-brand-50 text-brand-700"><KeyOutlined /></span>
                        <div>
                            <h2 className="text-base font-extrabold text-ink">Parolni yangilash</h2>
                            <p className="mt-1 text-sm text-ink-muted">Hisobingiz uchun kuchli va yangi parol o‘rnating.</p>
                        </div>
                    </div>
                    <div>
                        <label htmlFor="current_password" className="label">Joriy parol</label>
                        <Input.Password id="current_password" value={passwordForm.data.current_password} status={passwordForm.errors.current_password ? 'error' : undefined} onChange={(e) => passwordForm.setData('current_password', e.target.value)} autoComplete="current-password" />
                        <InputError message={passwordForm.errors.current_password} />
                    </div>
                    <div>
                        <label htmlFor="new_password" className="label">Yangi parol</label>
                        <Input.Password id="new_password" value={passwordForm.data.password} status={passwordForm.errors.password ? 'error' : undefined} onChange={(e) => passwordForm.setData('password', e.target.value)} autoComplete="new-password" />
                        <InputError message={passwordForm.errors.password} />
                    </div>
                    <div>
                        <label htmlFor="password_confirmation" className="label">Parolni tasdiqlash</label>
                        <Input.Password id="password_confirmation" value={passwordForm.data.password_confirmation} onChange={(e) => passwordForm.setData('password_confirmation', e.target.value)} autoComplete="new-password" />
                    </div>
                    <Button type="primary" htmlType="submit" loading={passwordForm.processing}>Parolni yangilash</Button>
                </form>

                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        modal.confirm({
                            title: 'Hisobni o‘chirish',
                            content: 'Bu amalni ortga qaytarib bo‘lmaydi. Davom etilsinmi?',
                            okText: 'Ha, o‘chirish',
                            cancelText: 'Bekor qilish',
                            okButtonProps: { danger: true },
                            onOk: () => deleteForm.delete('/profile'),
                        });
                    }}
                    className="card space-y-4 border-negative/30 bg-negative/10 xl:col-span-2"
                >
                    <div className="flex items-start gap-3">
                        <span className="flex h-9 w-9 items-center justify-center rounded-md bg-negative/15 text-negative"><DeleteOutlined /></span>
                        <div>
                            <h2 className="text-base font-extrabold text-negative">Hisobni o‘chirish</h2>
                            <p className="mt-1 text-sm text-negative">Bu amalni ortga qaytarib bo‘lmaydi. Davom etish uchun parolingizni kiriting.</p>
                        </div>
                    </div>
                    <div className="max-w-md">
                        <label htmlFor="delete_password" className="label">Parolingizni kiriting</label>
                        <Input.Password id="delete_password" value={deleteForm.data.password} status={deleteForm.errors.password ? 'error' : undefined} onChange={(e) => deleteForm.setData('password', e.target.value)} />
                        <InputError message={deleteForm.errors.password} />
                    </div>
                    <Button danger type="primary" htmlType="submit" loading={deleteForm.processing}>Hisobni o‘chirish</Button>
                </form>
            </div>
        </AdminLayout>
    );
}
