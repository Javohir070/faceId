import {
    AppstoreOutlined,
    BankOutlined,
    CalendarOutlined,
    ClusterOutlined,
    FileTextOutlined,
    HomeOutlined,
    IdcardOutlined,
    KeyOutlined,
    SafetyCertificateOutlined,
    SettingOutlined,
    SyncOutlined,
    TeamOutlined,
} from '@ant-design/icons';
import { route } from '../routes';

/** Sidebar guruhlari — tartib muhim */
export const adminNavGroups = [
    {
        id: 'main',
        title: null,
        items: [
            { key: 'home', href: route('home.index'), label: 'Bosh sahifa', icon: HomeOutlined },
            { key: 'categories', href: route('admin.categories.index'), label: 'Kategoriyalar', icon: AppstoreOutlined },
            { key: 'devices', href: route('admin.devices.index'), label: 'Qurilmalar', icon: ClusterOutlined },
            { key: 'sync', href: route('admin.sync.index'), label: 'Sinxronizatsiya', icon: SyncOutlined },
            { key: 'departments', href: route('admin.departments.index'), label: "Bo'limlar", icon: BankOutlined },
            { key: 'schedules', href: route('admin.schedules.index'), label: 'Jadvallar', icon: CalendarOutlined },
            { key: 'employees', href: route('admin.employees.index'), label: 'Xodimlar', icon: IdcardOutlined },
            { key: 'reports', href: route('admin.reports.index'), label: 'Hisobotlar', icon: FileTextOutlined },
            {
                key: 'settings',
                label: 'Sozlamalar',
                icon: SettingOutlined,
                children: [
                    { key: 'users', href: route('admin.users.index'), label: 'Foydalanuvchilar', icon: TeamOutlined },
                    { key: 'roles', href: route('admin.roles.index'), label: 'Rollar', icon: SafetyCertificateOutlined },
                    { key: 'permissions', href: route('admin.permissions.index'), label: 'Ruxsatlar', icon: KeyOutlined },
                ],
            },
        ],
    },
];

function isItemVisible(item, can = {}) {
    if (item.key === 'home') {
        return true;
    }

    if (item.children?.length) {
        return item.children.some((child) => can[child.key]);
    }

    return Boolean(can[item.key]);
}

/** Ruxsatga qarab faqat ko‘rinadigan guruhlarni qaytaradi */
export function getVisibleNavGroups(can = {}) {
    return adminNavGroups
        .map((group) => ({
            ...group,
            items: group.items
                .map((item) => {
                    if (!item.children?.length) {
                        return item;
                    }

                    return {
                        ...item,
                        children: item.children.filter((child) => can[child.key]),
                    };
                })
                .filter((item) => isItemVisible(item, can)),
        }))
        .filter((group) => group.items.length > 0);
}
