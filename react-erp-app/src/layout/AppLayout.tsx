import { useState } from "react";
import type { ReactNode } from "react";
import { useAuth } from "../features/auth/AuthContext";
import { Button, Badge, Notice } from "../components/ui";
const links = [
    ["/overview", "Overview"],
    ["/customers", "Customers"],
    ["/items", "Items & prices"],
    ["/stock", "Stock ledger"],
    ["/invoices", "Sales invoices"],
    ["/company", "Company Details"],
];
export function Sidebar({
    path,
    companyName,
    close,
}: {
    path: string;
    companyName: string;
    close: () => void;
}) {
    const { user, activeCompany, logout } = useAuth();
    const isCompanyConfigured = Boolean(activeCompany?.id && activeCompany?.ntn_cnic);
    const [signingOut, setSigningOut] = useState(false);
    const [error, setError] = useState("");
    return (
        <>
            <a className="brand"  href="#/overview">
                NEXA / ERP
            </a>
            <p className="eyebrow">BUSINESS WORKSPACE</p>
            <div className="workspace-name">{companyName}</div>
                        <p className="sidebar-muted">Company workspace • Pakistan</p>
            <nav aria-label="Main navigation">
                {user?.is_super_admin && (
                    <a
                        key="/admin/companies"
                        href="#/admin/companies"
                        onClick={close}
                        aria-current={path.startsWith("/admin/companies") ? "page" : undefined}
                        className={`${path.startsWith("/admin/companies") ? "nav-link active" : "nav-link"}`}
                        style={{ fontWeight: 600, color: "#38bdf8" }}
                    >
                        ⚖️ Client Companies Hub
                    </a>
                )}
                {links.map(([href, label]) => {
                    const requiresCompany = ["/customers", "/items", "/invoices", "/stock"].includes(href);
                    const disabled = requiresCompany && !isCompanyConfigured;
                    return (
                        <a
                            key={href}
                            href={`#${href}`}
                            onClick={event => {
                                if (disabled) {
                                    event.preventDefault();
                                    return;
                                }
                                close();
                            }}
                            aria-current={path.startsWith(href) ? "page" : undefined}
                            aria-disabled={disabled || undefined}
                            title={disabled ? "Please configure your company details first to unlock this section." : undefined}
                            className={`${path.startsWith(href) ? "nav-link active" : "nav-link"}${disabled ? " nav-link--disabled" : ""}`}
                        >
                            {label}{disabled && <span className="nav-lock-hint" aria-hidden="true"> · Setup required</span>}
                        </a>
                    );
                })}
            </nav>
            <div className="sidebar-bottom">
                <span className="sidebar-muted">{user?.email}</span>
                {error && <Notice tone="warning">{error}</Notice>}
                <Button variant="quiet" className="nav-link logout-button" disabled={signingOut} onClick={async () => {
                    setSigningOut(true); setError("");
                    try { await logout(); close(); }
                    catch (failure) { setError(failure instanceof Error ? failure.message : "Could not sign out."); }
                    finally { setSigningOut(false); }
                }}>{signingOut ? "Signing out…" : "Sign out"}</Button>
            </div>
        </>
    );
}
export function AppLayout({
    path,
    companyName,
    children,
}: {
    path: string;
    companyName: string;
    children: ReactNode;
}) {
    const { user } = useAuth();
    const [open, setOpen] = useState(false);
    const allLinks = [["/admin/companies", "Client Companies Hub"], ...links];
    const title =
        allLinks.find(([href]) => path.startsWith(href))?.[1] || "Workspace";
    return (
        <div className="app-shell">
            <a
                className="skip-link"
                href="#main-content"
                onClick={(event) => {
                    event.preventDefault();
                    document.getElementById("main-content")?.focus();
                }}
            >
                Skip to content
            </a>
            {open && (
                <button
                    className="sidebar-overlay"
                    aria-label="Close navigation"
                    onClick={() => setOpen(false)}
                />
            )}
            <aside className={`sidebar ${open ? "sidebar--open" : ""}`}>
                <Sidebar
                    {...{ path, companyName }}
                    close={() => setOpen(false)}
                />
            </aside>
            <main id="main-content" tabIndex={-1} className="workspace">
                <div className="topbar">
                    <div className="breadcrumb" style={{ display: "flex", alignItems: "center", gap: "0.5rem", flexWrap: "wrap" }}>
                        <Button
                            variant="quiet"
                            className="mobile-menu"
                            aria-label="Open navigation"
                            aria-expanded={open}
                            onClick={() => setOpen(!open)}
                        >
                            ☰
                        </Button>
                        <span>
                            {companyName}{" "}
                            <span className="breadcrumb-separator">/</span>{" "}
                            {title}
                        </span>
                        {user?.is_super_admin && path !== "/admin/companies" && (
                            <a
                                href="#/admin/companies"
                                style={{
                                    fontSize: "0.75rem",
                                    padding: "2px 8px",
                                    backgroundColor: "rgba(14, 165, 233, 0.15)",
                                    border: "1px solid rgba(14, 165, 233, 0.4)",
                                    borderRadius: "4px",
                                    color: "#0284c7",
                                    textDecoration: "none",
                                    fontWeight: 500,
                                }}
                            >
                                ⚖️ Switch Client Company
                            </a>
                        )}
                    </div>
                    <div style={{ display: "flex", gap: "0.5rem", alignItems: "center" }}>
                        {user?.is_super_admin && <Badge tone="neutral">Tax Consultant / Lawyer</Badge>}
                        <Badge tone="success">{path === "/admin/companies" ? "Client companies" : path === "/company" ? "Company profile" : path.startsWith("/customers") ? "Customer records" : path.startsWith("/items") ? "Item records" : path.startsWith("/invoices") ? "Invoice records" : path === "/stock" ? "Stock ledger" : "Live overview"}</Badge>
                    </div>
                </div>
                {children}
            </main>
        </div>
    );
}
export function AuthLayout({ children }: { children: ReactNode }) {
    return (
        <div className="auth-shell">
            <aside className="auth-sidebar">
                <a href="#/login" className="brand">
                    NEXA / ERP
                </a>
                <p className="eyebrow">BUSINESS ACCESS</p>
                <nav aria-label="Account navigation">
                    <a className="nav-link" href="#/login">
                        Login
                    </a>
                 </nav>
                <div className="auth-intro">
                    <span className="auth-mark">ERP</span>
                    <h2>Welcome to your business workspace</h2>
                    <p>
                        Customers, items and invoices.
                        <br />
                        One clear workspace.
                    </p>
                </div>
                <p className="sidebar-muted">Secure company access</p>
            </aside>
            <main className="auth-main">{children}</main>
        </div>
    );
}
