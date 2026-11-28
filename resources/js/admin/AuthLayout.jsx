import { Brand } from './ui';

export function AuthLayout({ title, subtitle, brandSubtitle, children }) {
    return <main className="login-page">
        <section className="login-identity">
            <Brand subtitle={brandSubtitle} />
            <div className="login-intro"><h1>{title}</h1><p>{subtitle}</p></div>
            <div className="login-decoration" aria-hidden="true" />
        </section>
        <section className="login-content"><div className="login-box">{children}</div></section>
    </main>;
}
