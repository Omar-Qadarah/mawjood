import { useState } from 'react';
import { createRoot } from 'react-dom/client';
import { requestAdminPasswordReset, resetAdminPassword } from './api';
import { AuthLayout } from './AuthLayout';
import { NewPasswordFields, useNewPassword } from './NewPasswordFields';
import { Field, Notice } from './ui';
import './admin.css';

const root = document.querySelector('#mawjood-admin-recovery');
const { mode, signInUrl, requestUrl } = root.dataset;

function RequestResetLink() {
    const [sent, setSent] = useState(false);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');

    async function submit(event) {
        event.preventDefault();
        if (busy) return;

        const { email } = Object.fromEntries(new FormData(event.currentTarget));
        setError('');
        setSent(false);
        setBusy(true);
        try {
            await requestAdminPasswordReset(email);
            setSent(true);
        } catch (exception) {
            setError(exception.message);
        } finally {
            setBusy(false);
        }
    }

    return <>
        <h2>Forgot password</h2>
        {sent && <Notice>If an active admin account matches that email, we’ll send a reset link.</Notice>}
        <Notice error>{error}</Notice>
        <form onSubmit={submit}>
            <Field label="Admin email"><input name="email" type="email" autoComplete="email" maxLength={190} required /></Field>
            <button className="button primary" disabled={busy}>{busy ? 'Sending…' : 'Send reset link'}</button>
        </form>
    </>;
}

function ResetPassword() {
    const [link] = useState(() => new URLSearchParams(window.location.hash.slice(1)));
    const email = link.get('email');
    const token = link.get('token');
    const [updated, setUpdated] = useState(false);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const password = useNewPassword();

    async function submit(event) {
        event.preventDefault();
        if (busy || !password.ready) return;

        setError('');
        setBusy(true);
        try {
            await resetAdminPassword({
                email, token, password: password.password, password_confirmation: password.confirmation,
            });
            password.reset();
            window.history.replaceState(window.history.state, '', window.location.pathname);
            setUpdated(true);
        } catch (exception) {
            setError(exception.message);
        } finally {
            setBusy(false);
        }
    }

    return <>
        <h2>Reset administrator password</h2>
        {updated ? <Notice>Password reset. You can now sign in with your new password.</Notice> : <>
            <Notice error>{error}</Notice>
            {email && token ? <form onSubmit={submit}>
                <Field label="Admin email"><input name="email" type="email" autoComplete="username" value={email} readOnly /></Field>
                <NewPasswordFields setup={password} passwordLabel="New password" confirmationLabel="Confirm new password" />
                <button className="button primary" disabled={busy || !password.ready}>{busy ? 'Resetting…' : 'Reset password'}</button>
            </form> : <Notice error>This reset link is invalid or expired.</Notice>}
            <a className="button secondary login-switch" href={requestUrl}>Request a new link</a>
        </>}
    </>;
}

createRoot(root).render(
    <AuthLayout title="Manage the pharmacy network." subtitle="Review listings and coordinate the Mawjood directory.">
        {mode === 'reset' ? <ResetPassword /> : <RequestResetLink />}
        <a className="button secondary login-switch" href={signInUrl}>Back to admin sign in</a>
    </AuthLayout>,
);
