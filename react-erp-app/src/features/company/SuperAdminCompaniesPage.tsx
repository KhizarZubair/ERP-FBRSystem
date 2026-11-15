import { useEffect, useState } from "react";
import { useAuth } from "../auth/AuthContext";
import { Button, Card, Badge, Notice, Input, Select, Textarea } from "../../components/ui";
import { Modal } from "../../components/Modal";
import { api } from "../../lib/api";
import { navigate } from "../../lib/navigation";
import type { AuthCompany } from "../auth/authApi";

function getErrorMessage(err: unknown): string {
    return err instanceof Error ? err.message : "An unexpected error occurred.";
}

type EnrolledCompany = AuthCompany & {
    seller_ntn?: string | null;
    seller_cnic?: string | null;
    contact_person?: string | null;
    email?: string | null;
    phone?: string | null;
    is_active?: boolean;
    credentials?: {
        sandbox: { configured: boolean; status: string };
        production: { configured: boolean; status: string };
    };
};

export function SuperAdminCompaniesPage() {
    const { activeCompany, selectCompany, refresh } = useAuth();
    const [companies, setCompanies] = useState<EnrolledCompany[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const [enrollOpen, setEnrollOpen] = useState(false);
    const [enrolling, setEnrolling] = useState(false);
    const [enrollError, setEnrollError] = useState("");
    const [successMessage, setSuccessMessage] = useState("");

    // Form state (Enroll)
    const [name, setName] = useState("");
    const [sellerNtn, setSellerNtn] = useState("");
    const [sellerCnic, setSellerCnic] = useState("");
    const [province, setProvince] = useState("Sindh");
    const [address, setAddress] = useState("");
    const [email, setEmail] = useState("");
    const [phone, setPhone] = useState("");
    const [isRetailer, setIsRetailer] = useState(false);
    const [businessActivities, setBusinessActivities] = useState<string[]>(["Service Provider"]);
    const [selectedSector, setSelectedSector] = useState<string>("Services");
    const [sandboxToken, setSandboxToken] = useState("");

    // Client user fields (Enroll)
    const [clientName, setClientName] = useState("");
    const [clientEmail, setClientEmail] = useState("");
    const [clientPassword, setClientPassword] = useState("");

    // Edit Modal state
    const [editModalOpen, setEditModalOpen] = useState(false);
    const [editingCompany, setEditingCompany] = useState<EnrolledCompany | null>(null);
    const [editName, setEditName] = useState("");
    const [editSellerNtn, setEditSellerNtn] = useState("");
    const [editSellerCnic, setEditSellerCnic] = useState("");
    const [editProvince, setEditProvince] = useState("Sindh");
    const [editAddress, setEditAddress] = useState("");
    const [editEmail, setEditEmail] = useState("");
    const [editPhone, setEditPhone] = useState("");
    const [editIsRetailer, setEditIsRetailer] = useState(false);
    const [editBusinessActivities, setEditBusinessActivities] = useState<string[]>(["Service Provider"]);
    const [editSelectedSector, setEditSelectedSector] = useState<string>("Services");
    const [editSandboxToken, setEditSandboxToken] = useState("");
    const [savingEdit, setSavingEdit] = useState(false);
    const [editError, setEditError] = useState("");

    // Delete Modal state
    const [deleteModalOpen, setDeleteModalOpen] = useState(false);
    const [deletingCompany, setDeletingCompany] = useState<EnrolledCompany | null>(null);
    const [deleting, setDeleting] = useState(false);
    const [deleteError, setDeleteError] = useState("");

    const provinces = [
        "Sindh", "Punjab", "Khyber Pakhtunkhwa", "Balochistan",
        "Islamabad Capital Territory", "Gilgit-Baltistan", "Azad Jammu and Kashmir"
    ];

    const allActivities = [
        "Manufacturer", "Importer", "Distributor", "Wholesaler",
        "Retailer", "Exporter", "Service Provider", "Other"
    ];

    const allSectors = [
        "Potassium Chlorate", "Mobile", "Pharmaceuticals", "Automobile",
        "Gas Distribution", "Petroleum", "Textile", "Steel",
        "Cement or Concrete Blocks", "Wholesale / Retails", "CNG Stations",
        "Services", "Electricity Distribution", "Telecom", "FMCG", "All Other Sectors"
    ];

    async function loadCompanies() {
        setLoading(true);
        setError("");
        try {
            const res = await api<{ data: EnrolledCompany[] }>("/api/v1/companies");
            setCompanies(res.data);
        } catch (err) {
            setError(getErrorMessage(err));
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => {
        void loadCompanies();
    }, []);

    function handleDirectLogin(company: EnrolledCompany) {
        selectCompany(company.id);
        navigate("/overview");
    }

    function toggleActivity(item: string) {
        setBusinessActivities(prev =>
            prev.includes(item) ? prev.filter(x => x !== item) : [...prev, item]
        );
    }

    function toggleEditActivity(item: string) {
        setEditBusinessActivities(prev =>
            prev.includes(item) ? prev.filter(x => x !== item) : [...prev, item]
        );
    }

    async function handleEnroll(e: React.FormEvent) {
        e.preventDefault();
        setEnrolling(true);
        setEnrollError("");
        try {
            await api("/api/v1/companies/enroll", {
                method: "POST",
                body: JSON.stringify({
                    name,
                    seller_ntn: sellerNtn || null,
                    seller_cnic: sellerCnic || null,
                    province,
                    address,
                    email,
                    phone: phone || null,
                    business_activity: businessActivities,
                    sector: [selectedSector],
                    is_sales_tax_profile_retailer: isRetailer,
                    sandbox_token: sandboxToken || null,
                    client_name: clientName || null,
                    client_email: clientEmail || null,
                    client_password: clientPassword || null,
                })
            });
            setSuccessMessage(`Company "${name}" enrolled successfully!`);
            setEnrollOpen(false);
            // Reset fields
            setName(""); setSellerNtn(""); setSellerCnic(""); setAddress(""); setEmail(""); setPhone("");
            setSelectedSector("Services");
            setClientName(""); setClientEmail(""); setClientPassword(""); setSandboxToken("");
            await loadCompanies();
            await refresh();
        } catch (err) {
            setEnrollError(getErrorMessage(err));
        } finally {
            setEnrolling(false);
        }
    }

    function openEditModal(c: EnrolledCompany) {
        setEditingCompany(c);
        setEditName(c.name || "");
        setEditSellerNtn(c.seller_ntn || (c.ntn_cnic && c.ntn_cnic.length <= 8 ? c.ntn_cnic : ""));
        setEditSellerCnic(c.seller_cnic || (c.ntn_cnic && c.ntn_cnic.length === 13 ? c.ntn_cnic : ""));
        setEditProvince(c.province || "Sindh");
        setEditAddress(c.address || "");
        setEditEmail(c.email || "");
        setEditPhone(c.phone || "");
        setEditIsRetailer(Boolean(c.is_sales_tax_profile_retailer));
        setEditBusinessActivities(Array.isArray(c.business_activity) && c.business_activity.length > 0 ? c.business_activity : ["Service Provider"]);
        setEditSelectedSector(Array.isArray(c.sector) && c.sector.length > 0 ? c.sector[0] : "Services");
        setEditSandboxToken("");
        setEditError("");
        setEditModalOpen(true);
    }

    async function handleSaveEdit(e: React.FormEvent) {
        e.preventDefault();
        if (!editingCompany) return;
        setSavingEdit(true);
        setEditError("");
        try {
            await api(`/api/v1/companies/${editingCompany.id}`, {
                method: "PUT",
                body: JSON.stringify({
                    name: editName,
                    seller_ntn: editSellerNtn || null,
                    seller_cnic: editSellerCnic || null,
                    province: editProvince,
                    address: editAddress,
                    email: editEmail,
                    phone: editPhone || null,
                    business_activity: editBusinessActivities,
                    sector: [editSelectedSector],
                    is_sales_tax_profile_retailer: editIsRetailer,
                    sandbox_token: editSandboxToken || null,
                })
            });
            setSuccessMessage(`Company "${editName}" updated successfully!`);
            setEditModalOpen(false);
            setEditingCompany(null);
            await loadCompanies();
            await refresh();
        } catch (err) {
            setEditError(getErrorMessage(err));
        } finally {
            setSavingEdit(false);
        }
    }

    async function handleToggleStatus(c: EnrolledCompany) {
        setError("");
        try {
            await api(`/api/v1/companies/${c.id}/toggle-status`, {
                method: "PATCH"
            });
            setSuccessMessage(`Company "${c.name}" ${c.is_active ? "deactivated" : "activated"} successfully!`);
            await loadCompanies();
            await refresh();
        } catch (err) {
            setError(getErrorMessage(err));
        }
    }

    function openDeleteModal(c: EnrolledCompany) {
        setDeletingCompany(c);
        setDeleteError("");
        setDeleteModalOpen(true);
    }

    async function handleConfirmDelete() {
        if (!deletingCompany) return;
        setDeleting(true);
        setDeleteError("");
        try {
            await api(`/api/v1/companies/${deletingCompany.id}`, {
                method: "DELETE"
            });
            setSuccessMessage(`Company "${deletingCompany.name}" soft-deleted successfully.`);
            setDeleteModalOpen(false);
            setDeletingCompany(null);
            await loadCompanies();
            await refresh();
        } catch (err) {
            setDeleteError(getErrorMessage(err));
        } finally {
            setDeleting(false);
        }
    }

    return (
        <div className="super-admin-companies-page">
            <div className="page-header" style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", marginBottom: "1.5rem" }}>
                <div>
                    <h2>Client Companies Directory</h2>
                    <p className="sidebar-muted">Manage enrolled client companies, access individual workspaces with 1-click, and grant client self-service logins.</p>
                </div>
                <Button variant="primary" onClick={() => setEnrollOpen(true)}>
                    + Enroll New Client Company
                </Button>
            </div>

            {successMessage && <div style={{ marginBottom: "1rem" }}><Notice tone="success">{successMessage}</Notice></div>}
            {error && <div style={{ marginBottom: "1rem" }}><Notice tone="warning">{error}</Notice></div>}

            <Card title={`Enrolled Companies (${companies.length})`}>
                {loading ? (
                    <p role="status">Loading client companies…</p>
                ) : companies.length === 0 ? (
                    <p>No client companies enrolled yet. Click &quot;Enroll New Client Company&quot; above to add your first company.</p>
                ) : (
                    <div style={{ overflowX: "auto" }}>
                        <table className="data-table" style={{ width: "100%", borderCollapse: "collapse", textAlign: "left" }}>
                            <thead>
                                <tr style={{ borderBottom: "2px solid var(--border-color, #e2e8f0)", padding: "8px" }}>
                                    <th style={{ padding: "10px" }}>Company Name</th>
                                    <th style={{ padding: "10px" }}>NTN / CNIC</th>
                                    <th style={{ padding: "10px" }}>Province</th>
                                    <th style={{ padding: "10px" }}>Business Classification</th>
                                    <th style={{ padding: "10px" }}>Status</th>
                                    <th style={{ padding: "10px" }}>Active Workspace</th>
                                    <th style={{ padding: "10px", textAlign: "right" }}>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {companies.map(c => {
                                    const isCurrent = activeCompany?.id === c.id;
                                    const isActive = c.is_active !== false;
                                    return (
                                        <tr key={c.id} style={{ borderBottom: "1px solid var(--border-color, #e2e8f0)", opacity: isActive ? 1 : 0.75 }}>
                                            <td style={{ padding: "12px 10px", fontWeight: "600" }}>
                                                {c.name}
                                                {c.email && <div style={{ fontSize: "0.8rem", color: "var(--text-muted, #64748b)" }}>{c.email}</div>}
                                            </td>
                                            <td style={{ padding: "12px 10px" }}>
                                                <code>{c.ntn_cnic || "Not provided"}</code>
                                            </td>
                                            <td style={{ padding: "12px 10px" }}>{c.province || "—"}</td>
                                            <td style={{ padding: "12px 10px" }}>
                                                <div style={{ display: "flex", gap: "4px", flexWrap: "wrap" }}>
                                                    {Array.isArray(c.business_activity) && c.business_activity.map(act => (
                                                        <Badge key={act} tone="neutral">{act}</Badge>
                                                    ))}
                                                    {Array.isArray(c.sector) && c.sector.map(sec => (
                                                        <Badge key={sec} tone="neutral">{sec}</Badge>
                                                    ))}
                                                    {c.is_sales_tax_profile_retailer && <Badge tone="success">Retailer Profile</Badge>}
                                                </div>
                                            </td>
                                            <td style={{ padding: "12px 10px" }}>
                                                {isActive ? (
                                                    <Badge tone="success">Active</Badge>
                                                ) : (
                                                    <Badge tone="warning">Deactivated</Badge>
                                                )}
                                            </td>
                                            <td style={{ padding: "12px 10px" }}>
                                                {isCurrent ? (
                                                    <Badge tone="success">Current Active Workspace</Badge>
                                                ) : (
                                                    <Badge tone="neutral">Available</Badge>
                                                )}
                                            </td>
                                            <td style={{ padding: "12px 10px", textAlign: "right" }}>
                                                <div style={{ display: "inline-flex", gap: "6px", alignItems: "center", justifyContent: "flex-end" }}>
                                                    {/* Workspace Switch Icon Button */}
                                                    <button
                                                        type="button"
                                                        onClick={() => handleDirectLogin(c)}
                                                        title={isCurrent ? "Current Active Workspace" : "Enter Company Workspace"}
                                                        style={{
                                                            display: "inline-flex",
                                                            alignItems: "center",
                                                            gap: "4px",
                                                            padding: "6px 10px",
                                                            borderRadius: "6px",
                                                            border: "1px solid var(--border-color, #cbd5e1)",
                                                            background: isCurrent ? "var(--primary-color, #0f172a)" : "#fff",
                                                            color: isCurrent ? "#fff" : "var(--primary-color, #0f172a)",
                                                            cursor: "pointer",
                                                            fontSize: "0.8rem",
                                                            fontWeight: 500,
                                                        }}
                                                    >
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                                                            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
                                                            <polyline points="10 17 15 12 10 7" />
                                                            <line x1="15" y1="12" x2="3" y2="12" />
                                                        </svg>
                                                        <span>{isCurrent ? "Active" : "Workspace"}</span>
                                                    </button>

                                                    {/* Edit Company Icon Button */}
                                                    <button
                                                        type="button"
                                                        onClick={() => openEditModal(c)}
                                                        title="Edit Company Details"
                                                        style={{
                                                            display: "inline-flex",
                                                            alignItems: "center",
                                                            justifyContent: "center",
                                                            width: "32px",
                                                            height: "32px",
                                                            borderRadius: "6px",
                                                            border: "1px solid var(--border-color, #cbd5e1)",
                                                            background: "#fff",
                                                            color: "var(--text-color, #334155)",
                                                            cursor: "pointer",
                                                        }}
                                                    >
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                                        </svg>
                                                    </button>

                                                    {/* Deactivate / Activate Icon Button */}
                                                    <button
                                                        type="button"
                                                        onClick={() => handleToggleStatus(c)}
                                                        title={isActive ? "Deactivate Company (Blocks client login)" : "Activate Company"}
                                                        style={{
                                                            display: "inline-flex",
                                                            alignItems: "center",
                                                            justifyContent: "center",
                                                            width: "32px",
                                                            height: "32px",
                                                            borderRadius: "6px",
                                                            border: "1px solid var(--border-color, #cbd5e1)",
                                                            background: "#fff",
                                                            color: isActive ? "#10b981" : "#f59e0b",
                                                            cursor: "pointer",
                                                        }}
                                                    >
                                                        {isActive ? (
                                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                                                                <circle cx="12" cy="12" r="10" />
                                                                <line x1="10" y1="15" x2="10" y2="9" />
                                                                <line x1="14" y1="15" x2="14" y2="9" />
                                                            </svg>
                                                        ) : (
                                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                                                                <polygon points="5 3 19 12 5 21 5 3" />
                                                            </svg>
                                                        )}
                                                    </button>

                                                    {/* Delete Company Icon Button */}
                                                    <button
                                                        type="button"
                                                        onClick={() => openDeleteModal(c)}
                                                        title="Delete Company (Soft-Delete)"
                                                        style={{
                                                            display: "inline-flex",
                                                            alignItems: "center",
                                                            justifyContent: "center",
                                                            width: "32px",
                                                            height: "32px",
                                                            borderRadius: "6px",
                                                            border: "1px solid #fecaca",
                                                            background: "#fff",
                                                            color: "#ef4444",
                                                            cursor: "pointer",
                                                        }}
                                                    >
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                                                            <polyline points="3 6 5 6 21 6" />
                                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                                                            <line x1="10" y1="11" x2="10" y2="17" />
                                                            <line x1="14" y1="11" x2="14" y2="17" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>

            {/* Enroll Company Modal */}
            {enrollOpen && (
                <Modal title="Enroll New Client Company" onClose={() => setEnrollOpen(false)} wide>
                    <form onSubmit={handleEnroll} autoComplete="off" style={{ display: "flex", flexDirection: "column", gap: "1rem" }}>
                        {/* Hidden dummy credentials to trap aggressive browser password managers */}
                        <input type="text" style={{ display: "none" }} tabIndex={-1} autoComplete="username" />
                        <input type="password" style={{ display: "none" }} tabIndex={-1} autoComplete="current-password" />

                        {enrollError && <Notice tone="warning">{enrollError}</Notice>}

                        <div style={{ fontWeight: 600, borderBottom: "1px solid var(--border-color, #e2e8f0)", paddingBottom: "4px" }}>
                            1. Company Profile
                        </div>
                        <Input label="Company Name *" required value={name} onChange={e => setName(e.target.value)} placeholder="e.g. Al-Madina Traders" />

                        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "1rem" }}>
                            <Input label="Seller NTN (7 or 8 digits)" value={sellerNtn} onChange={e => setSellerNtn(e.target.value)} placeholder="e.g. 1234567" />
                            <Input label="Seller CNIC (13 digits)" value={sellerCnic} onChange={e => setSellerCnic(e.target.value)} placeholder="e.g. 3520112345671" />
                        </div>

                        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "1rem" }}>
                            <Select label="Province *" required value={province} onChange={e => setProvince(e.target.value)}
                                options={provinces.map(p => ({ value: p, label: p }))} />
                            <Input
                                label="Company Email"
                                type="email"
                                name="company_registration_email"
                                id="company_registration_email"
                                autoComplete="new-company-email"
                                required
                                value={email}
                                onChange={e => setEmail(e.target.value)}
                                placeholder="billing@company.com"
                            />
                        </div>

                        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "1rem" }}>
                            <Input label="Phone" value={phone} onChange={e => setPhone(e.target.value)} placeholder="e.g. +923001234567" />
                            <label style={{ display: "flex", alignItems: "center", gap: "8px", marginTop: "24px" }}>
                                <input type="checkbox" checked={isRetailer} onChange={e => setIsRetailer(e.target.checked)} />
                                <span>Registered as Retailer in FBR Profile</span>
                            </label>
                        </div>

                        <Textarea label="Registered Address *" required value={address} onChange={e => setAddress(e.target.value)} placeholder="Shop / Office address..." />

                        <div style={{ fontWeight: 600, borderBottom: "1px solid var(--border-color, #e2e8f0)", paddingBottom: "4px", marginTop: "8px" }}>
                            2. FBR Business Nature & Sector
                        </div>
                        <div>
                            <label style={{ display: "block", marginBottom: "4px", fontSize: "0.875rem", fontWeight: 500 }}>Business Activity (Select all applicable):</label>
                            <div style={{ display: "flex", gap: "8px", flexWrap: "wrap" }}>
                                {allActivities.map(act => (
                                    <button type="button" key={act} onClick={() => toggleActivity(act)}
                                        style={{
                                            padding: "4px 10px", borderRadius: "16px", border: "1px solid var(--border-color, #cbd5e1)",
                                            background: businessActivities.includes(act) ? "var(--primary-color, #0f172a)" : "transparent",
                                            color: businessActivities.includes(act) ? "#fff" : "inherit", cursor: "pointer"
                                        }}>
                                        {act}
                                    </button>
                                ))}
                            </div>
                        </div>

                        <div>
                            <label style={{ display: "block", marginBottom: "6px", fontSize: "0.875rem", fontWeight: 500 }}>Business Sector (Select one):</label>
                            <div style={{ display: "flex", gap: "8px", flexWrap: "wrap", maxHeight: "120px", overflowY: "auto", padding: "4px 0" }}>
                                {allSectors.map(sec => (
                                    <label
                                        key={sec}
                                        style={{
                                            display: "inline-flex",
                                            alignItems: "center",
                                            gap: "6px",
                                            padding: "4px 10px",
                                            borderRadius: "16px",
                                            border: "1px solid var(--border-color, #cbd5e1)",
                                            background: selectedSector === sec ? "var(--primary-color, #0f172a)" : "transparent",
                                            color: selectedSector === sec ? "#fff" : "inherit",
                                            cursor: "pointer",
                                            fontSize: "0.8rem",
                                            userSelect: "none"
                                        }}
                                    >
                                        <input
                                            type="radio"
                                            name="company_business_sector"
                                            value={sec}
                                            checked={selectedSector === sec}
                                            onChange={() => setSelectedSector(sec)}
                                            style={{ margin: 0, cursor: "pointer" }}
                                        />
                                        <span>{sec}</span>
                                    </label>
                                ))}
                            </div>
                        </div>

                        <Input label="Sandbox FBR Token (Optional)" value={sandboxToken} onChange={e => setSandboxToken(e.target.value)} placeholder="Bearer token from PRAL Sandbox..." />

                        <div style={{ fontWeight: 600, borderBottom: "1px solid var(--border-color, #e2e8f0)", paddingBottom: "4px", marginTop: "8px" }}>
                            3. Client Representative Login Credentials (Optional)
                        </div>
                        <p style={{ fontSize: "0.8rem", color: "var(--text-muted, #64748b)", margin: 0 }}>
                            If provided, the client company will be able to log into this portal independently and manage their own invoices.
                        </p>
                        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "1rem" }}>
                            <Input label="Client Contact Name" value={clientName} onChange={e => setClientName(e.target.value)} placeholder="e.g. Muhammad Ali" />
                            <Input
                                label="Client Login Email"
                                type="email"
                                name="client_user_email"
                                id="client_user_email"
                                autoComplete="off"
                                value={clientEmail}
                                onChange={e => setClientEmail(e.target.value)}
                                placeholder="ali@clientcompany.com"
                            />
                        </div>
                        <Input
                            label="Client Login Password"
                            type="password"
                            name="new_client_portal_password"
                            id="new_client_portal_password"
                            autoComplete="new-password"
                            value={clientPassword}
                            onChange={e => setClientPassword(e.target.value)}
                            placeholder="Minimum 6 characters"
                        />

                        <div style={{ display: "flex", justifyContent: "flex-end", gap: "8px", marginTop: "1rem" }}>
                            <Button type="button" variant="quiet" onClick={() => setEnrollOpen(false)}>Cancel</Button>
                            <Button type="submit" variant="primary" disabled={enrolling}>
                                {enrolling ? "Enrolling Company…" : "Enroll Company & Create Access"}
                            </Button>
                        </div>
                    </form>
                </Modal>
            )}

            {/* Edit Company Modal */}
            {editModalOpen && editingCompany && (
                <Modal title={`Edit Company: ${editingCompany.name}`} onClose={() => setEditModalOpen(false)} wide>
                    <form onSubmit={handleSaveEdit} autoComplete="off" style={{ display: "flex", flexDirection: "column", gap: "1rem" }}>
                        {editError && <Notice tone="warning">{editError}</Notice>}

                        <div style={{ fontWeight: 600, borderBottom: "1px solid var(--border-color, #e2e8f0)", paddingBottom: "4px" }}>
                            1. Company Profile
                        </div>
                        <Input label="Company Name *" required value={editName} onChange={e => setEditName(e.target.value)} />

                        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "1rem" }}>
                            <Input label="Seller NTN (7 or 8 digits)" value={editSellerNtn} onChange={e => setEditSellerNtn(e.target.value)} placeholder="e.g. 1234567" />
                            <Input label="Seller CNIC (13 digits)" value={editSellerCnic} onChange={e => setEditSellerCnic(e.target.value)} placeholder="e.g. 3520112345671" />
                        </div>

                        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "1rem" }}>
                            <Select label="Province *" required value={editProvince} onChange={e => setEditProvince(e.target.value)}
                                options={provinces.map(p => ({ value: p, label: p }))} />
                            <Input
                                label="Company Email"
                                type="email"
                                required
                                value={editEmail}
                                onChange={e => setEditEmail(e.target.value)}
                                placeholder="billing@company.com"
                            />
                        </div>

                        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "1rem" }}>
                            <Input label="Phone" value={editPhone} onChange={e => setEditPhone(e.target.value)} placeholder="e.g. +923001234567" />
                            <label style={{ display: "flex", alignItems: "center", gap: "8px", marginTop: "24px" }}>
                                <input type="checkbox" checked={editIsRetailer} onChange={e => setEditIsRetailer(e.target.checked)} />
                                <span>Registered as Retailer in FBR Profile</span>
                            </label>
                        </div>

                        <Textarea label="Registered Address *" required value={editAddress} onChange={e => setEditAddress(e.target.value)} />

                        <div style={{ fontWeight: 600, borderBottom: "1px solid var(--border-color, #e2e8f0)", paddingBottom: "4px", marginTop: "8px" }}>
                            2. FBR Business Nature & Sector
                        </div>
                        <div>
                            <label style={{ display: "block", marginBottom: "4px", fontSize: "0.875rem", fontWeight: 500 }}>Business Activity (Select all applicable):</label>
                            <div style={{ display: "flex", gap: "8px", flexWrap: "wrap" }}>
                                {allActivities.map(act => (
                                    <button type="button" key={act} onClick={() => toggleEditActivity(act)}
                                        style={{
                                            padding: "4px 10px", borderRadius: "16px", border: "1px solid var(--border-color, #cbd5e1)",
                                            background: editBusinessActivities.includes(act) ? "var(--primary-color, #0f172a)" : "transparent",
                                            color: editBusinessActivities.includes(act) ? "#fff" : "inherit", cursor: "pointer"
                                        }}>
                                        {act}
                                    </button>
                                ))}
                            </div>
                        </div>

                        <div>
                            <label style={{ display: "block", marginBottom: "6px", fontSize: "0.875rem", fontWeight: 500 }}>Business Sector (Select one):</label>
                            <div style={{ display: "flex", gap: "8px", flexWrap: "wrap", maxHeight: "120px", overflowY: "auto", padding: "4px 0" }}>
                                {allSectors.map(sec => (
                                    <label
                                        key={sec}
                                        style={{
                                            display: "inline-flex",
                                            alignItems: "center",
                                            gap: "6px",
                                            padding: "4px 10px",
                                            borderRadius: "16px",
                                            border: "1px solid var(--border-color, #cbd5e1)",
                                            background: editSelectedSector === sec ? "var(--primary-color, #0f172a)" : "transparent",
                                            color: editSelectedSector === sec ? "#fff" : "inherit",
                                            cursor: "pointer",
                                            fontSize: "0.8rem",
                                            userSelect: "none"
                                        }}
                                    >
                                        <input
                                            type="radio"
                                            name="edit_company_business_sector"
                                            value={sec}
                                            checked={editSelectedSector === sec}
                                            onChange={() => setEditSelectedSector(sec)}
                                            style={{ margin: 0, cursor: "pointer" }}
                                        />
                                        <span>{sec}</span>
                                    </label>
                                ))}
                            </div>
                        </div>

                        <Input label="Update Sandbox FBR Token (Leave blank to keep current)" value={editSandboxToken} onChange={e => setEditSandboxToken(e.target.value)} placeholder="New Bearer token..." />

                        <div style={{ display: "flex", justifyContent: "flex-end", gap: "8px", marginTop: "1rem" }}>
                            <Button type="button" variant="quiet" onClick={() => setEditModalOpen(false)}>Cancel</Button>
                            <Button type="submit" variant="primary" disabled={savingEdit}>
                                {savingEdit ? "Saving Changes…" : "Save Changes"}
                            </Button>
                        </div>
                    </form>
                </Modal>
            )}

            {/* Delete Company Confirmation Modal */}
            {deleteModalOpen && deletingCompany && (
                <Modal title="Delete Client Company" onClose={() => setDeleteModalOpen(false)}>
                    <div style={{ display: "flex", flexDirection: "column", gap: "1rem" }}>
                        {deleteError && <Notice tone="warning">{deleteError}</Notice>}

                        <p style={{ margin: 0, fontSize: "0.95rem", lineHeight: 1.5 }}>
                            Are you sure you want to delete client company <strong>&quot;{deletingCompany.name}&quot;</strong>?
                        </p>

                        <Notice tone="info">
                            <strong>FBR Compliance Notice:</strong> This company will be safely soft-deleted and archived from active operations. In accordance with Pakistan FBR Digital Invoicing regulations, all historical sales invoices, fiscal reports, and FBR submission records will remain permanently preserved in the database for legal audit requirements.
                        </Notice>

                        <div style={{ display: "flex", justifyContent: "flex-end", gap: "8px", marginTop: "0.5rem" }}>
                            <Button type="button" variant="quiet" onClick={() => setDeleteModalOpen(false)}>
                                Cancel
                            </Button>
                            <Button
                                type="button"
                                variant="danger"
                                disabled={deleting}
                                onClick={handleConfirmDelete}
                            >
                                {deleting ? "Deleting…" : "Yes, Soft-Delete Company"}
                            </Button>
                        </div>
                    </div>
                </Modal>
            )}
        </div>
    );
}
