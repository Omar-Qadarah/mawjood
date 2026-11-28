import { useId, useState } from 'react';
import { Icon } from './ui';
import { PasswordField } from './PasswordField';

export function useNewPassword() {
    const [password, setPassword] = useState('');
    const [confirmation, setConfirmation] = useState('');
    const matches = confirmation !== '' && password === confirmation;
    const requirements = [
        ['At least 8 characters', Array.from(password).length >= 8],
        ['At least one uppercase letter', /\p{Lu}/u.test(password)],
        ['At least one lowercase letter', /\p{Ll}/u.test(password)],
        ['At least one number', /\p{N}/u.test(password)],
        ['At least one symbol', /[\p{P}\p{S}]/u.test(password)],
    ];

    function reset() {
        setPassword('');
        setConfirmation('');
    }

    return {
        password, confirmation, setPassword, setConfirmation, requirements, matches, reset,
        ready: matches && requirements.every(([, met]) => met),
    };
}

export function NewPasswordFields({ setup, passwordLabel = 'Password', confirmationLabel = 'Confirm password' }) {
    const id = useId();
    const { password, confirmation, setPassword, setConfirmation, requirements, matches } = setup;
    const mismatch = confirmation !== '' && !matches;

    return <>
        <PasswordField label={passwordLabel} name="password" autoComplete="new-password"
            aria-describedby={id + '-checks'} required value={password} onChange={(event) => setPassword(event.target.value)} />
        <PasswordField label={confirmationLabel} name="password_confirmation" autoComplete="new-password"
            aria-describedby={confirmation ? id + '-match' : undefined} aria-invalid={mismatch} required value={confirmation}
            onChange={(event) => setConfirmation(event.target.value)} />
        <ul className="password-checks" id={id + '-checks'} aria-label="Password requirements">
            {requirements.map(([label, met]) => <li key={label} data-met={met}>
                <span className="password-check-icon" aria-hidden="true">{met && <Icon name="check" size={12} />}</span>
                {label}<span className="sr-only">{met ? ' — met' : ' — not met'}</span>
            </li>)}
        </ul>
        {confirmation && <p className="password-match" data-met={matches} data-mismatch={mismatch} id={id + '-match'} role="status">
            {matches && <Icon name="check" size={14} />}
            {matches ? 'Passwords match.' : 'Passwords do not match.'}
        </p>}
    </>;
}
