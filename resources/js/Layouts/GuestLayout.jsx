import { Head, usePage } from '@inertiajs/react';
import {
    IdcardOutlined,
    SafetyCertificateOutlined,
    TeamOutlined,
} from '@ant-design/icons';
import { Card } from 'antd';

const highlights = [
    { icon: IdcardOutlined, text: 'Xodimlar va yuz ma’lumotlari' },
    { icon: SafetyCertificateOutlined, text: 'Biometrik kirish nazorati' },
    { icon: TeamOutlined, text: 'Bo‘limlar va jadvallar boshqaruvi' },
];

export default function GuestLayout({ children, title, subtitle }) {
    const { appName } = usePage().props;
    const brandName = appName || 'EIdora';

    return (
        <>
            <Head title={title} />
            <div className="min-h-screen bg-canvas lg:grid lg:grid-cols-[minmax(340px,0.95fr)_minmax(480px,1.05fr)]">
                <aside className="relative hidden min-h-screen flex-col justify-between overflow-hidden bg-brand-900 p-10 text-white lg:flex xl:p-14">
                    <div className="absolute -right-16 top-24 h-56 w-56 rounded-full bg-brand-700/30 blur-3xl" aria-hidden="true" />
                    <div className="absolute -bottom-20 -left-10 h-64 w-64 rounded-full bg-brand-600/20 blur-3xl" aria-hidden="true" />

                    <div className="relative">
                        <img
                            src="/admin/assets/img/eidora-logo.png"
                            alt={brandName}
                            className="h-24 w-auto max-w-[220px] rounded-xl bg-white p-2 object-contain shadow-ambient"
                        />
                        <p className="mt-4 text-sm font-semibold text-brand-100">
                            Biometrik kirish nazorati tizimi
                        </p>
                    </div>

                    <div className="relative max-w-lg">
                        <h2 className="text-3xl font-extrabold leading-tight text-balance">
                            Xodimlar, qurilmalar va ruxsatlarni bir joyda boshqaring
                        </h2>
                        <p className="mt-4 max-w-md text-base leading-7 text-brand-100">
                            {brandName} orqali tashkilotingizda xavfsiz kirish, yuz identifikatsiyasi va
                            smena jadvallarini boshqaring.
                        </p>
                        <ul className="mt-8 space-y-3 text-sm font-semibold text-brand-100">
                            {highlights.map(({ icon: Icon, text }) => (
                                <li key={text} className="flex items-center gap-3">
                                    <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-800/80">
                                        <Icon className="text-brand-200" />
                                    </span>
                                    {text}
                                </li>
                            ))}
                        </ul>
                    </div>

                    <p className="relative text-xs font-semibold text-brand-200">
                        © {new Date().getFullYear()} {brandName}
                    </p>
                </aside>

                <main className="flex min-h-screen items-center justify-center px-4 py-10 sm:px-8">
                    <div className="w-full max-w-lg">
                        <div className="mb-8 flex flex-col items-center text-center lg:hidden">
                            <img
                                src="/admin/assets/img/eidora-logo.png"
                                alt={brandName}
                                className="h-20 w-auto max-w-[180px] rounded-xl bg-white p-2 object-contain shadow-ambient"
                            />
                            <p className="mt-3 text-sm font-semibold text-ink-subtle">
                                Biometrik kirish nazorati tizimi
                            </p>
                        </div>

                        <div className="mb-7 text-center lg:text-left">
                            <p className="mb-2 hidden text-xs font-extrabold uppercase tracking-[0.18em] text-brand-600 lg:block">
                                {brandName}
                            </p>
                            <h1 className="text-3xl font-extrabold tracking-tight text-ink">{title}</h1>
                            {subtitle ? (
                                <p className="mt-2 max-w-[52ch] text-sm leading-6 text-ink-muted lg:mx-0 mx-auto">
                                    {subtitle}
                                </p>
                            ) : null}
                        </div>

                        <Card className="shadow-ambient" styles={{ body: { padding: 'clamp(1.75rem, 4vw, 2.25rem)' } }}>
                            {children}
                        </Card>

                        <p className="mt-6 text-center text-xs font-semibold text-ink-subtle lg:hidden">
                            © {new Date().getFullYear()} {brandName}
                        </p>
                    </div>
                </main>
            </div>
        </>
    );
}
