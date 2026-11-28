const settings = document.querySelector('#mawjood-admin, #mawjood-signup, #mawjood-admin-recovery').dataset;

export const places = JSON.parse(settings.places ?? '{}');

export async function api(path, { method = 'GET', body, signal } = {}) {
    const headers = { Accept: 'application/json' };
    const multipart = body instanceof FormData;
    if (body !== undefined && !multipart) {
        headers['Content-Type'] = 'application/json';
    }
    const token = document.cookie.split('; ').find((cookie) => cookie.startsWith('XSRF-TOKEN='));
    if (token && method !== 'GET') {
        headers['X-XSRF-TOKEN'] = decodeURIComponent(token.slice('XSRF-TOKEN='.length));
    }

    const response = await fetch(settings.apiBase + path, {
        method, headers, signal, credentials: 'same-origin',
        body: multipart || body === undefined ? body : JSON.stringify(body),
    });
    const result = response.status === 204 ? null : await response.json().catch(() => null);
    if (!response.ok) {
        const errors = Object.values(result?.errors ?? {}).flat();
        throw new Error(errors.join('\n') || result?.message || 'The request could not be completed.');
    }

    return result;
}

async function submitAuthForm(path, body) {
    const response = await fetch(settings.csrfUrl, { credentials: 'same-origin' });
    if (!response.ok) throw new Error('Could not start the sign-in session.');

    return (await api(path, { method: 'POST', body }))?.data;
}

export function signIn(credentials) {
    return submitAuthForm('/auth/login', credentials);
}

export function signUp(details) {
    return submitAuthForm('/auth/register', details);
}

export function requestAdminPasswordReset(email) {
    return submitAuthForm('/auth/admin/forgot-password', { email });
}

export function resetAdminPassword(details) {
    return submitAuthForm('/auth/admin/reset-password', details);
}
