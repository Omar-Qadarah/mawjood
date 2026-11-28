import { Children, cloneElement, useEffect, useId, useRef, useState } from 'react';
import logo from './mawjood-logo.png';

export function Icon({ name, size = 19 }) {
    const paths = {
        overview: 'M3 3h7v7H3zm11 0h7v7h-7zM3 14h7v7H3zm11 0h7v7h-7z',
        pharmacies: 'M4 12h16c-.8 3.2-3.7 5.5-8 5.5S4.8 15.2 4 12zM12 17.5V21m-4 0h8M12 12c0-1.6 1-2.2 2.4-2.9 1.3-.6 2.1-1.5 1.9-2.8-.2-1.8-2-2.9-3.8-2.5-1 .2-1.8.9-2.2 1.8L9 6l1.6.7M13 5.5h.05',
        medicines: 'M18.4 5.6a4.5 4.5 0 0 1 0 6.4l-6.4 6.4a4.5 4.5 0 0 1-6.4-6.4L12 5.6a4.5 4.5 0 0 1 6.4 0zM8.8 8.8l6.4 6.4',
        expiry: 'M6 3v4m12-4v4M3 9h18M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2zm7 7v3m0 3h.01',
        inventory: 'M3 7l9-4 9 4v10l-9 4-9-4zm0 0 9 4 9-4M12 11v10M7.5 5l9 4',
        reservations: 'M8 4H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-2M8 2h8v5H8zM8 12h8m-8 5h5',
        customers: 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m20 0v-2a4 4 0 0 0-3-4M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm8-7a4 4 0 0 1 0 7',
        arrow: 'M5 12h14m-6-6 6 6-6 6',
        location: 'M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0zM12 12a2 2 0 1 0 0-4 2 2 0 0 0 0 4z',
        view: 'M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6-10-6-10-6zm10-2.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5z',
        license: 'M4 5h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1zm10 5h4m-4 4h4M6 15a3 3 0 0 1 6 0m-3-3.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z',
        edit: 'm14 5 5 5M4 20l5-1 11-11a2.8 2.8 0 0 0-4-4L5 15z',
        delete: 'M4 7h16M9 7V4h6v3m3 0-1 13H7L6 7m4 3v7m4-7v7',
        adjust: 'M4 7h8m4 0h4M4 12h2m4 0h10M4 17h10m4 0h2M12 5v4M6 10v4m8 1v4',
        search: 'm20 20-4-4m2-5a7 7 0 1 1-14 0 7 7 0 0 1 14 0z',
        profile: 'M10 3h4l.7 2.6 2 .9 2.5-1.2 2 3.4-1.8 1.9v2.8l1.8 1.9-2 3.4-2.5-1.2-2 .9L14 21h-4l-.7-2.6-2-.9-2.5 1.2-2-3.4 1.8-1.9v-2.8L2.8 8.7l2-3.4 2.5 1.2 2-.9L10 3zM15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z',
        menu: 'M4 6h16M4 12h16M4 18h16',
        more: 'M5 12h.01M12 12h.01M19 12h.01',
        copy: 'M9 8h10a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H9a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1zm7 0V5a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3',
        check: 'm5 12 4 4L19 6',
        chevron: 'm6 9 6 6 6-6',
        logout: 'M10 17l5-5-5-5m5 5H3m12-8h5a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-5',
        close: 'm6 6 12 12M18 6 6 18',
        plus: 'M12 5v14M5 12h14',
    };
    return <svg aria-hidden="true" width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.65" strokeLinecap="round" strokeLinejoin="round"><path d={paths[name]} /></svg>;
}

export function TableAction({ icon, label, title = label, onClick, disabled, inMenu = false }) {
    const style = inMenu ? 'icon-button' : 'icon-button table-action';
    return <button className={style + (icon === 'delete' ? ' table-action-danger' : '')}
        type="button" aria-label={label} title={title} onClick={onClick} disabled={disabled}>
        <Icon name={icon} size={17} />
    </button>;
}

function TablePopover({ icon, label, variant, onClose, children }) {
    const id = useId();
    const trigger = useRef(null);
    const menu = useRef(null);
    const [open, setOpen] = useState(false);

    useEffect(() => {
        if (!open) return;

        const panel = menu.current;
        function dismiss(event) {
            if (panel.matches(':popover-open') && !panel.contains(event.target instanceof Node ? event.target : null)) {
                panel.hidePopover();
            }
        }

        window.addEventListener('scroll', dismiss, true);
        window.addEventListener('resize', dismiss);
        return () => {
            window.removeEventListener('scroll', dismiss, true);
            window.removeEventListener('resize', dismiss);
        };
    }, [open]);

    function onToggle(event) {
        const panel = event.currentTarget;
        const shown = event.newState === 'open';
        setOpen(shown);
        if (!shown) {
            onClose?.();
            return;
        }

        const anchor = trigger.current.getBoundingClientRect();
        const { width, height } = panel.getBoundingClientRect();
        const below = anchor.bottom + 6;
        const above = anchor.top - height - 6;
        const top = below + height + 8 > window.innerHeight && above >= 8 ? above : below;
        panel.style.top = Math.max(8, Math.min(top, window.innerHeight - height - 8)) + 'px';
        panel.style.left = Math.max(8, Math.min(anchor.right - width, window.innerWidth - width - 8)) + 'px';
        (panel.querySelector('button:not(:disabled)') ?? panel).focus({ preventScroll: true });
    }

    return <>
        <button className={'icon-button ' + variant + '-trigger'} type="button" ref={trigger} popoverTarget={id}
            aria-label={label} title={label}>
            <Icon name={icon} size={19} />
        </button>
        <div className={'table-popover ' + variant + '-menu'} id={id} ref={menu} popover="auto" onToggle={onToggle}
            role="group" aria-label={label} tabIndex={-1}>
            {children(() => menu.current.hidePopover())}
        </div>
    </>;
}

export function RowActions({ rowName, children }) {
    return <>
        <div className={'row-actions-desktop' + (Children.count(children) > 1 ? ' row-actions' : '')}>{children}</div>
        <TablePopover icon="more" label={'Actions for ' + rowName} variant="row-action">
            {(close) => Children.map(children, (action) => cloneElement(action, {
                inMenu: true,
                onClick: () => {
                    close();
                    action.props.onClick();
                },
            }))}
        </TablePopover>
    </>;
}

export function CopyableValue({ value, copyLabel, children }) {
    const [feedback, setFeedback] = useState(null);
    const request = useRef(0);
    const copied = feedback?.message === 'Copied';

    useEffect(() => {
        if (feedback?.message !== 'Copied') return;
        const timer = window.setTimeout(() => setFeedback(null), 2000);
        return () => window.clearTimeout(timer);
    }, [feedback]);

    if (!value) return '—';

    async function copyValue(event) {
        const button = event.currentTarget;
        const attempt = ++request.current;
        let message;
        try {
            await navigator.clipboard.writeText(value);
            message = 'Copied';
        } catch {
            message = 'Couldn’t copy';
        }

        if (attempt === request.current && button.isConnected) setFeedback({ message });
    }

    return <span className="copyable-value">
        <span className="copyable-value-text">{children ?? value}</span>
        <button className="icon-button copy-value-button" type="button" aria-label={copyLabel}
            title={copyLabel} onClick={copyValue}>
            <Icon name={copied ? 'check' : 'copy'} size={18} />
        </button>
        <span className="copy-value-feedback" role="status">{feedback?.message}</span>
    </span>;
}

export function LicensePopover({ pharmacy, value }) {
    const [session, setSession] = useState(0);
    if (!value) return '—';

    return <TablePopover icon="license" label={'License number for ' + pharmacy} variant="license"
        onClose={() => setSession((previous) => previous + 1)}>
        {() => <CopyableValue key={session} value={value} copyLabel="Copy license number" />}
    </TablePopover>;
}

export function Brand() {
    return <div className="brand"><img className="brand-mark" src={logo} alt="" width="41" height="41" /><div>Mawjood<small>Administration</small></div></div>;
}

export function Modal({ title, onClose, children, wide = false }) {
    const ref = useRef(null);
    const backdropPointer = useRef(false);

    useEffect(() => {
        const dialog = ref.current;
        dialog.showModal();
        return () => dialog.close();
    }, []);

    return <dialog
        ref={ref}
        className={'modal' + (wide ? ' modal-wide' : '')}
        aria-label={title}
        onCancel={(event) => { event.preventDefault(); onClose(); }}
        onPointerDown={(event) => { backdropPointer.current = event.target === event.currentTarget; }}
        onClick={(event) => { if (backdropPointer.current && event.target === event.currentTarget) onClose(); }}
    >
        <div className="modal-card">
            <header className="modal-header"><h2>{title}</h2><button className="icon-button" type="button" aria-label="Close dialog" onClick={onClose}><Icon name="close" /></button></header>
            {children}
        </div>
    </dialog>;
}

export function Status({ status }) {
    return <span className={'status status-' + status}>{status}</span>;
}

export function Field({ label, wide, children }) {
    return <label className={wide ? 'field wide' : 'field'}><span>{label}</span>{children}</label>;
}

export function Notice({ error, children }) {
    return children ? <p className={'notice' + (error ? ' error' : '')} role={error ? 'alert' : 'status'}>{children}</p> : null;
}

export function Details({ values }) {
    return <dl className="details">{Object.entries(values).map(([label, value]) =>
        <div key={label}><dt>{label}</dt><dd>{value || '—'}</dd></div>
    )}</dl>;
}
