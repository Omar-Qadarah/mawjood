import { useState } from 'react';
import { api } from './api';
import { Field, Notice } from './ui';

export function ProfileSettings({ user, onUpdated }) {
    const [feedback, setFeedback] = useState({});
    const [saving, setSaving] = useState('');

    async function save(event, section) {
        event.preventDefault();
        const form = event.currentTarget;
        const data = new FormData(form);
        setSaving(section);
        setFeedback({});
        try {
            if (section === 'profile') {
                if (!data.get('avatar')?.size) data.delete('avatar');
                const { data: updated } = await api('/admin/profile', { method: 'POST', body: data });
                onUpdated(updated);
            } else {
                await api('/admin/profile/password', { method: 'PATCH', body: Object.fromEntries(data) });
            }
            form.reset();
            setFeedback({ section, message: section === 'profile' ? 'Profile saved.' : 'Password changed.' });
        } catch (exception) {
            setFeedback({ section, error: true, message: exception.message });
        } finally {
            setSaving('');
        }
    }

    return <section aria-labelledby="profile-heading">
        <header className="page-heading"><h1 id="profile-heading">Profile settings</h1></header>
        <div className="profile-grid">
            <form className="panel profile-form" onSubmit={(event) => save(event, 'profile')} aria-label="Profile details">
                <h2>Profile details</h2>
                {feedback.section === 'profile' && <Notice error={feedback.error}>{feedback.message}</Notice>}
                <span className="avatar profile-avatar">
                    {user.avatar_url ? <img src={user.avatar_url} alt="Profile photo" /> : user.name?.slice(0, 1).toUpperCase()}
                </span>
                <Field label="Name"><input key={user.name} name="name" defaultValue={user.name} autoComplete="name" maxLength={255} required /></Field>
                <Field label="Profile photo"><input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" aria-describedby="profile-photo-help" /></Field>
                <p id="profile-photo-help">JPEG, PNG, or WebP up to 2 MB.</p>
                <button className="button primary" disabled={Boolean(saving)}>{saving === 'profile' ? 'Saving…' : 'Save profile'}</button>
            </form>
            <form className="panel profile-form" onSubmit={(event) => save(event, 'password')} aria-label="Change password">
                <h2>Change password</h2>
                {feedback.section === 'password' && <Notice error={feedback.error}>{feedback.message}</Notice>}
                <Field label="Current password"><input name="current_password" type="password" autoComplete="current-password" required /></Field>
                <Field label="New password"><input name="password" type="password" autoComplete="new-password" minLength={8} required /></Field>
                <Field label="Confirm new password"><input name="password_confirmation" type="password" autoComplete="new-password" minLength={8} required /></Field>
                <button className="button primary" disabled={Boolean(saving)}>{saving === 'password' ? 'Changing…' : 'Change password'}</button>
            </form>
        </div>
    </section>;
}
