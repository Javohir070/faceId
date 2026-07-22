import GuestLayout from '../../Layouts/GuestLayout';
import InputError from '../../Components/InputError';
import { useForm, usePage } from '@inertiajs/react';
import { LockOutlined, LoginOutlined, MailOutlined } from '@ant-design/icons';
import { Alert, Button, Checkbox, Input } from 'antd';

export default function Login() {
    const { flash, appName } = usePage().props;
    const brandName = appName || 'EIdora';
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
            subtitle={`${brandName} boshqaruv paneliga kirish uchun elektron pochta va parolingizni kiriting.`}
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
                    <div className="mb-1.5 flex items-center justify-between gap-3">
                        <label htmlFor="password" className="label mb-0">Parol</label>
                    </div>
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

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Checkbox checked={data.remember} onChange={(e) => setData('remember', e.target.checked)}>
                        Meni eslab qolish
                    </Checkbox>
                </div>

                <Button
                    type="primary"
                    htmlType="submit"
                    size="large"
                    block
                    className="!h-12 !text-base !font-bold"
                    icon={<LoginOutlined />}
                    loading={processing}
                >
                    Kirish
                </Button>
            </form>
        </GuestLayout>
    );
}
