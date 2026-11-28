import { useId, useState } from 'react';
import { Icon } from './ui';

export function PasswordField({ label, ...inputProps }) {
    const id = useId();
    const [visible, setVisible] = useState(false);
    const action = (visible ? 'Hide ' : 'Show ') + label.toLowerCase();

    return <div className="field">
        <label htmlFor={id}>{label}</label>
        <div className="password-control">
            <input {...inputProps} id={id} type={visible ? 'text' : 'password'} />
            <button type="button" className="icon-button password-toggle" aria-label={action} aria-controls={id}
                onClick={() => setVisible((current) => !current)}>
                <Icon name={visible ? 'viewOff' : 'view'} size={19} />
            </button>
        </div>
    </div>;
}
