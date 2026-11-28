import { useEffect, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { signUp } from './api';
import { AuthLayout } from './AuthLayout';
import { Field, Icon, Notice } from './ui';
import { NewPasswordFields, useNewPassword } from './NewPasswordFields';
import './admin.css';

function CustomerSignup() {
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);
    const [created, setCreated] = useState(false);
    const successHeading = useRef(null);
    const passwordSetup = useNewPassword();

    useEffect(() => {
        if (created) successHeading.current?.focus();
    }, [created]);

    async function submit(event) {
        event.preventDefault();
        if (busy || !passwordSetup.ready) return;

        const fields = new FormData(event.currentTarget);
        setError('');
        setBusy(true);
        try {
            await signUp({
                name: fields.get('name'),
                email: fields.get('email'),
                password: passwordSetup.password,
                password_confirmation: passwordSetup.confirmation,
            });
            passwordSetup.reset();
            setCreated(true);
        } catch (exception) {
            setError(exception.message);
        } finally {
            setBusy(false);
        }
    }

    return <AuthLayout title="Find medicine near you." subtitle="Search for medicines and reserve them at a pharmacy." brandSubtitle={null}>
        {created ? <h2 ref={successHeading} tabIndex={-1}>Customer account created</h2> : <>
            <h2>Create a customer account</h2>
            <Notice error>{error}</Notice>
            <form onSubmit={submit}>
                <Field label="Full name"><input name="name" autoComplete="name" maxLength={150} required /></Field>
                <Field label="Email"><input type="email" name="email" autoComplete="email" maxLength={190} required /></Field>
                <NewPasswordFields setup={passwordSetup} />
                <button className="button primary" disabled={busy || !passwordSetup.ready}>{busy ? 'Creating account…' : 'Create account'}<Icon name="arrow" /></button>
            </form>
        </>}
        <a className="button secondary login-switch" href="/">Back to home</a>
    </AuthLayout>;
}

createRoot(document.querySelector('#mawjood-signup')).render(<CustomerSignup />);
