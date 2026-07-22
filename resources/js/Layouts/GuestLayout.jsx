import { Head, usePage } from '@inertiajs/react';
import { Card } from 'antd';
import BiometricHeroVisual from '../Components/BiometricHeroVisual';

export default function GuestLayout({ children, title, subtitle }) {
    const { appName } = usePage().props;
    const brandName = appName || 'EIdora';

    return (
        <>
            <Head title={title} />
            <div className="min-h-screen bg-canvas lg:grid lg:grid-cols-2">
                <aside className="relative hidden min-h-screen items-center justify-center overflow-hidden bg-brand-900 px-10 py-14 text-white lg:flex xl:px-14">
                    <div className="absolute -right-16 top-10 h-40 w-40 rounded-full bg-brand-700/30 blur-3xl sm:top-24 sm:h-56 sm:w-56" aria-hidden="true" />
                    <div className="absolute -bottom-16 -left-10 h-48 w-48 rounded-full bg-brand-600/20 blur-3xl sm:-bottom-20 sm:h-64 sm:w-64" aria-hidden="true" />

                    <BiometricHeroVisual brandName={brandName} />
                </aside>

                <main className="flex min-h-screen flex-col lg:justify-center">
                    <div className="border-b border-brand-800 bg-brand-900 px-4 py-4 text-white lg:hidden">
                        <div className="mx-auto flex max-w-md items-center gap-3 sm:max-w-lg">
                            <img
                                src="/admin/assets/img/eidora-logo.png"
                                alt=""
                                className="h-10 w-10 shrink-0 rounded-lg object-cover object-top"
                            />
                            <div className="min-w-0 text-left">
                                <p className="truncate text-base font-extrabold tracking-tight">{brandName}</p>
                                <p className="text-xs font-semibold text-brand-100">Biometrik kirish nazorati</p>
                            </div>
                        </div>
                    </div>

                    <div className="flex flex-1 items-center justify-center px-4 py-8 sm:px-6 sm:py-10 md:px-8 lg:py-12">
                        <div className="w-full max-w-md sm:max-w-lg">
                            <div className="mb-6 text-center sm:mb-7 lg:text-left">
                                <h1 className="text-2xl font-extrabold tracking-tight text-ink sm:text-3xl">
                                    {title}
                                </h1>
                                {subtitle ? (
                                    <p className="mx-auto mt-2 max-w-[52ch] text-sm leading-6 text-ink-muted lg:mx-0">
                                        {subtitle}
                                    </p>
                                ) : null}
                            </div>

                            <Card
                                className="shadow-ambient"
                                styles={{ body: { padding: 'clamp(1.25rem, 4vw, 2.25rem)' } }}
                            >
                                {children}
                            </Card>

                            <p className="mt-5 text-center text-xs font-semibold text-ink-subtle sm:mt-6">
                                © {new Date().getFullYear()} {brandName}
                            </p>
                        </div>
                    </div>
                </main>
            </div>
        </>
    );
}
