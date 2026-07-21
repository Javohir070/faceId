import GuestLayout from '../../Layouts/GuestLayout';
import InputError from '../../Components/InputError';
import { useForm, usePage } from '@inertiajs/react';
import { LockOutlined, LoginOutlined, MailOutlined } from '@ant-design/icons';
import { Alert, Button, Checkbox, Input } from 'antd';

export default function Login() {
    const { flash } = usePage().props;
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();
        post('/login', {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout
            title="Tizimga kirish"
            subtitle="EIdora boshqaruv paneliga kirish uchun elektron pochta va parolingizni kiriting."
        >
            {flash?.status && (
                <Alert className="mb-5" type="success" showIcon message={flash.status} />
            )}

            <form onSubmit={submit} className="space-y-6">
                <div>
                    <label htmlFor="email" className="label">Elektron pochta</label>
                    <Input
                        id="email"
                        type="email"
                        size="large"
                        prefix={<MailOutlined />}
                        value={data.email}
                        status={errors.email ? 'error' : undefined}
                        onChange={(e) => setData('email', e.target.value)}
                        placeholder="email@example.uz"
                        autoFocus
                        autoComplete="username"
                        required
                    />
                    <InputError message={errors.email} />
                </div>

                <div>
                    <label htmlFor="password" className="label">Parol</label>
                    <Input.Password
                        id="password"
                        size="large"
                        prefix={<LockOutlined />}
                        value={data.password}
                        status={errors.password ? 'error' : undefined}
                        onChange={(e) => setData('password', e.target.value)}
                        autoComplete="current-password"
                        required
                    />
                    <InputError message={errors.password} />
                </div>

                <Checkbox checked={data.remember} onChange={(e) => setData('remember', e.target.checked)}>
                    Meni eslab qolish
                </Checkbox>

                <Button
                    type="primary"
                    htmlType="submit"
                    size="large"
                    className="h-12 w-full text-base font-bold"
                    icon={<LoginOutlined />}
                    loading={processing}
                >
                    Kirish
                </Button>
            </form>
        </GuestLayout>
    );
}
