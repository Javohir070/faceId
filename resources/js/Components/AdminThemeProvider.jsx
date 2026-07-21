import { App as AntApp, ConfigProvider, theme as antdTheme } from 'antd';
import {
    createContext,
    useCallback,
    useContext,
    useLayoutEffect,
    useMemo,
    useState,
} from 'react';

const STORAGE_KEY = 'biocontrol.admin.theme';
const AdminThemeContext = createContext(null);

function isAdminPath(pathname) {
    return pathname === '/dashboard'
        || pathname === '/profile'
        || pathname.startsWith('/admin/');
}

function getInitialMode() {
    try {
        const stored = window.localStorage.getItem(STORAGE_KEY);
        return stored === 'light' || stored === 'dark' ? stored : 'dark';
    } catch {
        return 'dark';
    }
}

export function useAdminTheme() {
    const context = useContext(AdminThemeContext);

    if (!context) {
        throw new Error('useAdminTheme must be used inside AdminThemeProvider');
    }

    return context;
}

export default function AdminThemeProvider({ children }) {
    const [mode, setMode] = useState(getInitialMode);
    const [adminActive, setAdminActive] = useState(() =>
        isAdminPath(window.location.pathname),
    );

    const updateMode = useCallback((nextMode) => {
        setMode(nextMode);
        try {
            window.localStorage.setItem(STORAGE_KEY, nextMode);
        } catch {
            // The selected mode still works for this session when storage is blocked.
        }
    }, []);

    const toggleMode = useCallback(() => {
        updateMode(mode === 'dark' ? 'light' : 'dark');
    }, [mode, updateMode]);

    useLayoutEffect(() => {
        if (adminActive) {
            document.documentElement.dataset.adminTheme = mode;
        } else {
            delete document.documentElement.dataset.adminTheme;
        }

        return () => {
            delete document.documentElement.dataset.adminTheme;
        };
    }, [adminActive, mode]);

    const theme = useMemo(() => {
        const dark = adminActive && mode === 'dark';

        return {
            algorithm: dark
                ? antdTheme.darkAlgorithm
                : antdTheme.defaultAlgorithm,
            token: {
                colorPrimary: dark ? '#4aba82' : '#228f5c',
                colorInfo: dark ? '#60a5fa' : '#2563eb',
                colorSuccess: dark ? '#4ade80' : '#228f5c',
                colorWarning: dark ? '#fb923c' : '#ea580c',
                colorError: dark ? '#f87171' : '#dc2626',
                colorText: dark ? '#e5e7eb' : '#1f2937',
                colorTextSecondary: dark ? '#cbd5e1' : '#4b5563',
                colorTextTertiary: dark ? '#94a3b8' : '#6b7280',
                colorBgBase: dark ? '#111827' : '#ffffff',
                colorBgLayout: dark ? '#0b1220' : '#f3f4f6',
                colorBgContainer: dark ? '#182132' : '#ffffff',
                colorBgElevated: dark ? '#151e2d' : '#ffffff',
                colorBgSpotlight: dark ? '#263247' : '#1f2937',
                colorBorder: dark ? '#334155' : '#e5e7eb',
                colorBorderSecondary: dark ? '#1f2937' : '#f3f4f6',
                colorTextLightSolid: '#f8fafc',
                borderRadius: 8,
                borderRadiusLG: 10,
                controlHeight: 40,
                fontFamily: '"Nunito Sans", ui-sans-serif, system-ui, sans-serif',
            },
            components: {
                Button: {
                    fontWeight: 700,
                    primaryShadow: 'none',
                },
                Card: {
                    boxShadowTertiary: dark
                        ? '0 1px 3px 0 rgba(0, 0, 0, 0.3)'
                        : '0 1px 3px 0 rgba(15, 23, 42, 0.06)',
                },
                Input: {
                    activeBg: dark ? '#1d293d' : '#ffffff',
                    hoverBg: dark ? '#1d293d' : '#ffffff',
                },
                InputNumber: {
                    activeBg: dark ? '#1d293d' : '#ffffff',
                    hoverBg: dark ? '#1d293d' : '#ffffff',
                },
                Modal: {
                    contentBg: dark ? '#151e2d' : '#ffffff',
                    headerBg: dark ? '#151e2d' : '#ffffff',
                    titleColor: dark ? '#f1f5f9' : '#1f2937',
                },
                Select: {
                    selectorBg: dark ? '#182132' : '#ffffff',
                },
                Table: {
                    headerBg: dark ? '#172033' : '#f9fafb',
                    headerColor: dark ? '#aebbd0' : '#6b7280',
                    rowHoverBg: dark ? '#172b22' : '#eefaf3',
                },
                Pagination: {
                    itemActiveBg: dark ? '#172b22' : '#ffffff',
                },
            },
        };
    }, [adminActive, mode]);

    const value = useMemo(
        () => ({
            mode,
            setAdminActive,
            toggleMode,
        }),
        [mode, toggleMode],
    );

    return (
        <AdminThemeContext.Provider value={value}>
            <ConfigProvider theme={theme} componentSize="middle">
                <AntApp>{children}</AntApp>
            </ConfigProvider>
        </AdminThemeContext.Provider>
    );
}
