import {
    ApartmentOutlined,
    AppstoreOutlined,
    BankOutlined,
    BarChartOutlined,
    CalendarOutlined,
    ClusterOutlined,
    FormOutlined,
    HistoryOutlined,
    HomeOutlined,
    IdcardOutlined,
    KeyOutlined,
    SafetyCertificateOutlined,
    ScanOutlined,
    SettingOutlined,
    SyncOutlined,
    TeamOutlined,
    UserAddOutlined,
} from '@ant-design/icons';
import { route } from '../routes';

/** Sidebar guruhlari — tartib muhim */
export const adminNavGroups = [
    {
        id: 'main',
        title: null,
        items: [
            { key: 'home', href: route('home.index'), label: 'Bosh sahifa', icon: HomeOutlined },
            { key: 'live', href: route('admin.live.index'), label: 'Jonli kuzatuv', icon: ScanOutlined },
            { key: 'categories', href: route('admin.categories.index'), label: 'Kategoriyalar', icon: AppstoreOutlined },
            { key: 'devices', href: route('admin.devices.index'), label: 'Qurilmalar', icon: ClusterOutlined },
            { key: 'sync', href: route('admin.sync.index'), label: 'Sinxronizatsiya', icon: SyncOutlined },
            { key: 'departments', href: route('admin.departments.index'), label: "Bo'limlar", icon: BankOutlined },
            { key: 'groups', href: route('admin.groups.index'), label: 'Guruhlar', icon: ApartmentOutlined },
            { key: 'schedules', href: route('admin.schedules.index'), label: 'Jadvallar', icon: CalendarOutlined },
            {
                key: 'employees-group',
                permission: 'employees',
                label: 'Xodimlar',
                icon: IdcardOutlined,
                href: route('admin.employees.index'),
            },
            {
                key: 'notes',
                permission: 'notes',
                href: route('admin.notes.index'),
                label: 'Ish vaqtida ruxsatlar',
                icon: FormOutlined,
            },
            {
                key: 'reports-group',
                label: 'Hisobotlar',
                permission: 'reports',
                href: route('admin.reports.index'),
                icon: BarChartOutlined,
            },
            {
                key: 'settings',
                label: 'Sozlamalar',
                icon: SettingOutlined,
                children: [
                    { key: 'users', href: route('admin.users.index'), label: 'Foydalanuvchilar', icon: TeamOutlined },
                    { key: 'roles', href: route('admin.roles.index'), label: 'Rollar', icon: SafetyCertificateOutlined },
                    { key: 'permissions', href: route('admin.permissions.index'), label: 'Ruxsatlar', icon: KeyOutlined },
                    {
                        key: 'reports-pull',
                        permission: 'reports-pull',
                        href: route('admin.reports.pull'),
                        label: 'Event tortish',
                        icon: HistoryOutlined,
                    },
                    {
                        key: 'employees-pull',
                        permission: 'employees-pull',
                        href: route('admin.employees.pull'),
                        label: 'Xodim tortish',
                        icon: UserAddOutlined,
                    },
                ],
            },
        ],
    },
];

function permissionKey(item) {
    return item.permission ?? item.key;
}

function isItemVisible(item, can = {}) {
    if (item.key === 'home') {
        return true;
    }

    if (item.children?.length) {
        return item.children.some((child) => can[permissionKey(child)]);
    }

    return Boolean(can[permissionKey(item)]);
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
                        children: item.children.filter((child) => can[permissionKey(child)]),
                    };
                })
                .filter((item) => isItemVisible(item, can)),
        }))
        .filter((group) => group.items.length > 0);
}
