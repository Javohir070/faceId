import GuestLayout from '../../Layouts/GuestLayout';
import InputError from '../../Components/InputError';
import { useForm } from '@inertiajs/react';
import { Button, Input } from 'antd';

export default function ConfirmPassword() {
    const { data, setData, post, processing, errors, reset } = useForm({
        password: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post('/confirm-password', {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout title="Parolni tasdiqlang" subtitle="Davom etish uchun parolingizni kiriting">
            <form onSubmit={submit} className="space-y-4">
                <div>
                    <label htmlFor="password" className="label">Parol</label>
                    <Input.Password
                        id="password"
                        value={data.password}
                        status={errors.password ? 'error' : undefined}
                        onChange={(e) => setData('password', e.target.value)}
                        autoFocus
                        required
                    />
                    <InputError message={errors.password} />
                </div>
                <Button type="primary" htmlType="submit" className="w-full" loading={processing}>
                    Tasdiqlash
                </Button>
            </form>
        </GuestLayout>
    );
}
