function resource(prefix, base) {
    return {
        [`${prefix}.index`]: () => base,
        [`${prefix}.create`]: () => `${base}/create`,
        [`${prefix}.store`]: () => base,
        [`${prefix}.show`]: (id) => `${base}/${id}`,
        [`${prefix}.edit`]: (id) => `${base}/${id}/edit`,
        [`${prefix}.update`]: (id) => `${base}/${id}`,
        [`${prefix}.destroy`]: (id) => `${base}/${id}`,
    };
}

const routeBuilders = {
    'home.index': () => '/dashboard',
    'profile.edit': () => '/profile',
    'logout': () => '/logout',

    ...resource('admin.categories', '/admin/categories'),
    ...resource('admin.devices', '/admin/devices'),
    'admin.devices.connect': (id) => `/admin/devices/${id}/connect`,
    'admin.devices.disconnect': (id) => `/admin/devices/${id}/disconnect`,
    'admin.devices.ping': (id) => `/admin/devices/${id}/ping`,
    'admin.devices.open': (id) => `/admin/devices/${id}/open`,
    'admin.devices.restart': (id) => `/admin/devices/${id}/restart`,
    'admin.devices.clear': (id) => `/admin/devices/${id}/clear`,
    'admin.devices.sync-employees': (id) => `/admin/devices/${id}/sync-employees`,
    'admin.devices.pull-employees': (id) => `/admin/devices/${id}/pull-employees`,
    'admin.devices.remove-employees': (id) => `/admin/devices/${id}/remove-employees`,

    'admin.sync.index': () => '/admin/sync',
    'admin.sync.transfer': () => '/admin/sync/transfer',
    'admin.sync.transfer-one': () => '/admin/sync/transfer-one',

    ...resource('admin.departments', '/admin/departments'),
    ...resource('admin.groups', '/admin/groups'),
    'admin.groups.bulk-destroy': () => '/admin/groups/bulk-destroy',
    ...resource('admin.schedules', '/admin/schedules'),
    ...resource('admin.employees', '/admin/employees'),
    ...resource('admin.notes', '/admin/notes'),
    'admin.employees.pull': () => '/admin/employees/pull',
    'admin.employees.pull.store': () => '/admin/employees/pull',
    'admin.employees.export': () => '/admin/employees/export',
    'admin.employees.remove-devices': (id) => `/admin/employees/${id}/remove-devices`,

    'admin.live.index': () => '/admin/live',
    'admin.live.stats': () => '/admin/live/stats',
    'admin.live.adjustments.index': () => '/admin/live/adjustments',
    'admin.live.adjustments.store': () => '/admin/live/adjustments',
    'admin.live.adjustments.destroy': (id) => `/admin/live/adjustments/${id}`,

    'admin.reports.index': () => '/admin/reports',
    'admin.reports.export': () => '/admin/reports/export',
    'admin.attendance.index': () => '/admin/attendance',
    'admin.attendance.export': () => '/admin/attendance/export',
    'admin.attendance.notes.store': () => '/admin/attendance/notes',
    'admin.reports.pull': () => '/admin/reports/pull',
    'admin.reports.pull.store': () => '/admin/reports/pull',

    ...resource('admin.users', '/admin/users'),
    ...resource('admin.roles', '/admin/roles'),
    ...resource('admin.permissions', '/admin/permissions'),
};

/**
 * Laravel route nomlariga mos URL yaratadi.
 *
 * @param {string} name
 * @param {number|string} [param]
 * @returns {string}
 */
export function route(name, param) {
    const builder = routeBuilders[name];

    if (!builder) {
        throw new Error(`Route [${name}] is not defined.`);
    }

    if (param === undefined || param === null) {
        return builder();
    }

    return builder(param);
}
