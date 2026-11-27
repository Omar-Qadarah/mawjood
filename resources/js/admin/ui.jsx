import { useEffect, useRef } from 'react';

export function Icon({ name, size = 19 }) {
    const paths = {
        overview: 'M3 3h7v7H3zm11 0h7v7h-7zM3 14h7v7H3zm11 0h7v7h-7z',
        pharmacies: 'M3 21h18M5 21V8l7-4 7 4v13M12 10v6m-3-3h6',
        medicines: 'M18.4 5.6a4.5 4.5 0 0 1 0 6.4l-6.4 6.4a4.5 4.5 0 0 1-6.4-6.4L12 5.6a4.5 4.5 0 0 1 6.4 0zM8.8 8.8l6.4 6.4',
        inventory: 'M3 7l9-4 9 4v10l-9 4-9-4zm0 0 9 4 9-4M12 11v10M7.5 5l9 4',
        reservations: 'M8 4H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-2M8 2h8v5H8zM8 12h8m-8 5h5',
        customers: 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m20 0v-2a4 4 0 0 0-3-4M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm8-7a4 4 0 0 1 0 7',
        arrow: 'M5 12h14m-6-6 6 6-6 6',
        location: 'M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0zM12 12a2 2 0 1 0 0-4 2 2 0 0 0 0 4z',
        search: 'm20 20-4-4m2-5a7 7 0 1 1-14 0 7 7 0 0 1 14 0z',
        logout: 'M10 17l5-5-5-5m5 5H3m12-8h5a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-5',
        close: 'm6 6 12 12M18 6 6 18',
        plus: 'M12 5v14M5 12h14',
    };
    return <svg aria-hidden="true" width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.65" strokeLinecap="round" strokeLinejoin="round"><path d={paths[name]} /></svg>;
}

export function Brand() {
    return <div className="brand"><span className="brand-mark"><Icon name="pharmacies" size={23} /></span><div>Mawjood<small>Administration</small></div></div>;
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
