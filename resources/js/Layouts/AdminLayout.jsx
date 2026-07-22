import { Head, usePage } from '@inertiajs/react';
import { useEffect, useLayoutEffect, useMemo, useState } from 'react';
import AdminHeader from '../Components/Admin/AdminHeader';
import AdminSidebar from '../Components/Admin/AdminSidebar';
import { useAdminTheme } from '../Components/AdminThemeProvider';
import FlashMessages from '../Components/FlashMessages';
import { getVisibleNavGroups } from '../config/adminNav';
import { route } from '../routes';

export default function AdminLayout({ children, title }) {
    const page = usePage();
    const { auth, can, appName } = page.props;
    const [mobileOpen, setMobileOpen] = useState(false);
    const [collapsed, setCollapsed] = useState(false);
    const [openMenus, setOpenMenus] = useState({});
    const { mode, setAdminActive, toggleMode } = useAdminTheme();
    const current = typeof page.url === 'string' ? page.url : '';

    const navGroups = useMemo(() => getVisibleNavGroups(can || {}), [can]);
    const navItems = useMemo(
        () => navGroups.flatMap((group) => group.items),
        [navGroups],
    );

    const isActive = (href) => {
        if (!href) {
            return false;
        }
        if (href === route('home.index')) {
            return current === route('home.index') || current === `${route('home.index')}/`;
        }
        return current.startsWith(href);
    };

    const isGroupActive = (item) =>
        Boolean(item.children?.some((child) => isActive(child.href)));

    useEffect(() => {
        setOpenMenus((prev) => {
            const next = { ...prev };
            navItems.forEach((item) => {
                if (item.children?.length && isGroupActive(item)) {
                    next[item.key] = true;
                }
            });
            return next;
        });
    }, [current, navItems]);

    useEffect(() => {
        const closeOnEscape = (event) => {
            if (event.key === 'Escape') {
                setMobileOpen(false);
            }
        };

        document.addEventListener('keydown', closeOnEscape);
        return () => {
            document.removeEventListener('keydown', closeOnEscape);
        };
    }, []);

    useEffect(() => {
        setMobileOpen(false);
    }, [current]);

    useLayoutEffect(() => {
        setAdminActive(true);
        return () => setAdminActive(false);
    }, [setAdminActive]);

    return (
        <>
            <Head title={title} />
            <div className="admin-shell min-h-screen bg-canvas lg:flex">
                <AdminSidebar
                    appName={appName}
                    collapsed={collapsed}
                    mobileOpen={mobileOpen}
                    navItems={navItems}
                    openMenus={openMenus}
                    onCloseMobile={() => setMobileOpen(false)}
                    onToggleMenu={(key) => setOpenMenus((prev) => ({ ...prev, [key]: !prev[key] }))}
                    isActive={isActive}
                    isGroupActive={isGroupActive}
                />

                {mobileOpen ? (
                    <button
                        type="button"
                        className="fixed inset-0 z-30 bg-black/60 lg:hidden"
                        aria-label="Menyuni yopish"
                        onClick={() => setMobileOpen(false)}
                    />
                ) : null}

                <div className="flex min-h-screen min-w-0 flex-1 flex-col">
                    <AdminHeader
                        title={title}
                        auth={auth}
                        mode={mode}
                        mobileOpen={mobileOpen}
                        collapsed={collapsed}
                        onOpenMobile={() => setMobileOpen(true)}
                        onToggleCollapsed={() => setCollapsed((value) => !value)}
                        onToggleMode={toggleMode}
                    />

                    <main className="flex-1 px-4 py-5 sm:px-5 lg:px-6 lg:py-6">
                        <div className="page-section">
                            <FlashMessages />
                            {children}
                        </div>
                    </main>
                </div>
            </div>
        </>
    );
}
