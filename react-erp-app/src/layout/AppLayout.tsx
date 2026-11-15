import { useState } from "react";
import type { ReactNode } from "react";
import { useAuth } from "../features/auth/AuthContext";
import { Button, Badge, Notice } from "../components/ui";

const companyLinks = [
    ["/overview", "Overview"],
    ["/customers", "Customers"],
    ["/items", "Items & prices"],
    ["/stock", "Stock ledger"],
    ["/invoices", "Sales invoices"],
    ["/company", "Company Details"],
];

const adminLinks = [
    ["/admin/overview", "Overview"],
    ["/admin/companies", "Companies"],
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
    const isSuperAdminPortal = Boolean(user?.is_super_admin && (path.startsWith("/admin/") || !activeCompany));
    const [signingOut, setSigningOut] = useState(false);
    const [error, setError] = useState("");

    return (
        <>
            <a className="brand" href={isSuperAdminPortal ? "#/admin/overview" : "#/overview"}>
                NEXA / ERP
            </a>

            {isSuperAdminPortal ? (
                <>
                    <p className="eyebrow">TAX CONSULTANT PORTAL</p>
                    <div className="workspace-name">Super Admin Hub</div>
                    <p className="sidebar-muted">Multi-Company Management</p>
                    <nav aria-label="Super Admin navigation">
                        {adminLinks.map(([href, label]) => (
                            <a
                                key={href}
                                href={`#${href}`}
                                onClick={close}
                                aria-current={path.startsWith(href) ? "page" : undefined}
                                className={path.startsWith(href) ? "nav-link active" : "nav-link"}
                            >
                                {label}
                            </a>
                        ))}
                    </nav>
                </>
            ) : (
                <>
                    <p className="eyebrow">{user?.is_super_admin ? "CLIENT WORKSPACE" : "BUSINESS WORKSPACE"}</p>
                    <div className="workspace-name">{companyName}</div>
                    {activeCompany?.is_active === false && (
                        <div style={{ marginTop: "4px", marginBottom: "4px" }}>
                            <span style={{ display: "inline-block", fontSize: "11px", fontWeight: 700, background: "#fef3c7", color: "#92400e", padding: "2px 8px", borderRadius: "4px" }}>
                                Deactivated
                            </span>
                        </div>
                    )}
                    <p className="sidebar-muted">{user?.is_super_admin ? "Viewing as Super Admin" : "Company workspace • Pakistan"}</p>
                    <nav aria-label="Main navigation">
                        {user?.is_super_admin && (
                            <a
                                key="/admin/companies"
                                href="#/admin/companies"
                                onClick={close}
                                className="nav-link"
                                style={{ fontWeight: 600, color: "#38bdf8", marginBottom: "0.5rem" }}
                            >
                                ⬅ Exit to Admin Hub
                            </a>
                        )}
                        {companyLinks.map(([href, label]) => {
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
                </>
            )}

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
    const { user, activeCompany } = useAuth();
    const [open, setOpen] = useState(false);
    const isSuperAdminPortal = Boolean(user?.is_super_admin && (path.startsWith("/admin/") || !activeCompany));
    const allLinks = [...adminLinks, ...companyLinks];
    const title = allLinks.find(([href]) => path.startsWith(href))?.[1] || "Workspace";

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
                    {...{ path, companyName: isSuperAdminPortal ? "Super Admin Hub" : companyName }}
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
                            {isSuperAdminPortal ? "Super Admin Hub" : companyName}{" "}
                            <span className="breadcrumb-separator">/</span>{" "}
                            {title}
                        </span>
                    </div>
                    <div style={{ display: "flex", gap: "0.5rem", alignItems: "center" }}>
                        {user?.is_super_admin && (
                            <Badge tone="neutral">
                                {isSuperAdminPortal ? "Super Admin" : "Viewing as Super Admin"}
                            </Badge>
                        )}
                        {!isSuperAdminPortal && activeCompany?.is_active === false && (
                            <Badge tone="warning">Deactivated</Badge>
                        )}
                        <Badge tone="success">
                            {path === "/admin/overview" ? "Admin Overview"
                                : path === "/admin/companies" ? "Client Directory"
                                : path === "/company" ? "Company profile"
                                : path.startsWith("/customers") ? "Customer records"
                                : path.startsWith("/items") ? "Item records"
                                : path.startsWith("/invoices") ? "Invoice records"
                                : path === "/stock" ? "Stock ledger"
                                : "Live overview"}
                        </Badge>
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
