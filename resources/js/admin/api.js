const settings = document.querySelector('#mawjood-admin').dataset;

export const places = JSON.parse(settings.places);

export async function api(path, { method = 'GET', body } = {}) {
    const headers = { Accept: 'application/json' };
    if (body !== undefined) {
        headers['Content-Type'] = 'application/json';
    }
    const token = document.cookie.split('; ').find((cookie) => cookie.startsWith('XSRF-TOKEN='));
    if (token && method !== 'GET') {
        headers['X-XSRF-TOKEN'] = decodeURIComponent(token.slice('XSRF-TOKEN='.length));
    }

    const response = await fetch(settings.apiBase + path, {
        method, headers, credentials: 'same-origin',
        body: body === undefined ? undefined : JSON.stringify(body),
    });
    const result = response.status === 204 ? null : await response.json().catch(() => null);
    if (!response.ok) {
        const errors = Object.values(result?.errors ?? {}).flat();
        throw new Error(errors.join('\n') || result?.message || 'The request could not be completed.');
    }

    return result;
}

export async function signIn(credentials) {
    const response = await fetch(settings.csrfUrl, { credentials: 'same-origin' });
    if (!response.ok) throw new Error('Could not start the sign-in session.');

    return (await api('/auth/login', { method: 'POST', body: credentials })).data;
}
