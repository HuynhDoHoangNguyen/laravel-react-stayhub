const HOME_BY_ROLE = { CUSTOMER: '/customer', HOST: '/host', ADMIN: '/admin' };
const PATHS_BY_ROLE = {
    CUSTOMER: /^\/customer(?:\/(?:bookings(?:\/[1-9]\d*)?|checkout|profile|invoices(?:\/[1-9]\d*)?|reviews))?\/?$/,
    HOST: /^\/host(?:\/(?:profile|invoices(?:\/(?:create|[1-9]\d*))?|bookings\/[1-9]\d*\/create-invoice|reviews))?\/?$/,
    ADMIN: /^\/admin(?:\/profile)?\/?$/,
};

function hasUnsafeCharacters(value) {
    return /[\\\s]/.test(value) || Array.from(value).some((character) => {
        const code = character.charCodeAt(0);
        return code < 32 || code === 127;
    });
}

export function getAuthDestination(role, returnTo) {
    const fallback = HOME_BY_ROLE[role] || '/';
    if (typeof returnTo !== 'string' || !returnTo.startsWith('/') || returnTo.startsWith('//')
        || hasUnsafeCharacters(returnTo)) return fallback;

    try {
        const url = new URL(returnTo, 'https://stayhub.invalid');
        const pathname = decodeURIComponent(url.pathname);
        if (url.origin !== 'https://stayhub.invalid' || hasUnsafeCharacters(pathname)
            || pathname.includes('//') || pathname.includes('%')) return fallback;

        const publicPath = /^(?:\/|\/search\/?|\/properties\/[1-9]\d*\/?)$/;
        return (PATHS_BY_ROLE[role]?.test(pathname) || publicPath.test(pathname))
            ? url.pathname + url.search + url.hash
            : fallback;
    } catch {
        return fallback;
    }
}
