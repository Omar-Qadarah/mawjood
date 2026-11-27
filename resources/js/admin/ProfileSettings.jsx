import { useEffect, useRef, useState } from 'react';
import { api } from './api';
import { Field, Modal, Notice } from './ui';
import { ProfilePhotoCrop } from './ProfilePhotoCrop';

export function ProfileSettings({ user, onUpdated }) {
    const [feedback, setFeedback] = useState({});
    const [saving, setSaving] = useState('');
    const [previewUrl, setPreviewUrl] = useState(null);
    const [enlarged, setEnlarged] = useState(false);
    const [cropping, setCropping] = useState(false);
    const [crop, setCrop] = useState(null);
    const photoUrl = useRef(null);
    const photoInput = useRef(null);
    const previewButton = useRef(null);

    useEffect(() => () => {
        if (photoUrl.current) URL.revokeObjectURL(photoUrl.current);
    }, []);

    useEffect(() => {
        if (crop) return () => URL.revokeObjectURL(crop.url);
    }, [crop]);

    function previewPhoto(file) {
        setCrop(null);
        if (photoUrl.current) URL.revokeObjectURL(photoUrl.current);
        photoUrl.current = file ? URL.createObjectURL(file) : null;
        setPreviewUrl(photoUrl.current);
    }

    function cancelPhotoPreview() {
        photoInput.current.value = '';
        previewPhoto(null);
        photoInput.current.focus();
    }

    function closeEnlargedPreview() {
        setEnlarged(false);
        setCropping(false);
        requestAnimationFrame(() => previewButton.current?.focus());
    }

    function applyCrop(file, settings) {
        setCrop({ file, settings, url: URL.createObjectURL(file) });
        closeEnlargedPreview();
    }

    const displayedPreview = crop?.url ?? previewUrl;

    async function save(event, section) {
        event.preventDefault();
        const form = event.currentTarget;
        const data = new FormData(form);
        setSaving(section);
        setFeedback({});
        try {
            if (section === 'profile') {
                if (crop) data.set('avatar', crop.file);
                else if (!data.get('avatar')?.size) data.delete('avatar');
                const { data: updated } = await api('/admin/profile', { method: 'POST', body: data });
                onUpdated(updated);
                previewPhoto(null);
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
                <Field label="Name"><input key={user.name} name="name" defaultValue={user.name} autoComplete="name" maxLength={255} required /></Field>
                <div className="profile-photo">
                    {displayedPreview || user.avatar_url ? <button ref={previewButton} className="avatar profile-avatar profile-preview-button" type="button"
                        aria-label={previewUrl ? "Enlarge photo preview" : "View current profile photo full screen"}
                        aria-haspopup="dialog" onClick={() => setEnlarged(true)}>
                        <img src={displayedPreview ?? user.avatar_url} alt="" />
                    </button> : <span className="avatar profile-avatar">{user.name?.slice(0, 1).toUpperCase()}</span>}
                    <div className="profile-photo-control">
                        <Field label="Profile photo"><input ref={photoInput} type="file" name="avatar" accept="image/jpeg,image/png,image/webp" aria-describedby="profile-photo-help"
                            disabled={saving === 'profile'} onChange={event => previewPhoto(event.target.files[0])} /></Field>
                        <p id="profile-photo-help" aria-live="polite">{previewUrl ? "Select the preview to enlarge or crop it. Save profile to apply." : "JPEG, PNG, or WebP up to 2 MB."}</p>
                        {previewUrl && <button className="button small secondary" type="button" aria-label="Cancel photo preview"
                            disabled={saving === 'profile'} onClick={cancelPhotoPreview}>Cancel</button>}
                    </div>
                </div>
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
        {enlarged && !previewUrl && user.avatar_url && <Modal title="Current profile photo" onClose={closeEnlargedPreview}>
            <img className="profile-current-image" src={user.avatar_url} alt="Current profile photo" />
        </Modal>}
        {enlarged && previewUrl && <Modal title={cropping ? "Crop photo" : "Photo preview"} onClose={closeEnlargedPreview}>
            {cropping ? <ProfilePhotoCrop source={previewUrl} initialCrop={crop?.settings} onApply={applyCrop}
                onBack={() => setCropping(false)} /> : <>
                <img className="profile-preview-image" src={displayedPreview} alt="New profile photo" />
                <footer className="form-actions">
                    <button className="button secondary" type="button" onClick={() => setCropping(true)}>Crop photo</button>
                </footer>
            </>}
        </Modal>}
    </section>;
}
