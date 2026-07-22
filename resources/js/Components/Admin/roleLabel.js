export function roleLabel(roles = []) {
    if (!roles.length) {
        return 'FOYDALANUVCHI';
    }

    const primary = roles[0];
    if (primary === 'super-admin') {
        return 'TIZIM ADMINI';
    }

    return String(primary).replace(/-/g, ' ').toUpperCase();
}
