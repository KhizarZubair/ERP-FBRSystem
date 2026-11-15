import { useEffect, useState } from "react";
import { Button, Card, Badge, PageHeader } from "../../components/ui";
import { api } from "../../lib/api";
import { navigate } from "../../lib/navigation";
import type { AuthCompany } from "../auth/authApi";

type EnrolledCompany = AuthCompany & {
    contact_person?: string | null;
    email?: string | null;
    phone?: string | null;
    is_active?: boolean;
    credentials?: {
        sandbox: { configured: boolean; status: string };
        production: { configured: boolean; status: string };
    };
};

export function SuperAdminOverviewPage() {
    const [companies, setCompanies] = useState<EnrolledCompany[]>([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        let active = true;
        api<{ data: EnrolledCompany[] }>("/api/v1/companies")
            .then(res => {
                if (active) setCompanies(res.data);
            })
            .catch(() => {})
            .finally(() => {
                if (active) setLoading(false);
            });
        return () => { active = false; };
    }, []);

    const totalCompanies = companies.length;
    const activeCompanies = companies.filter(c => c.is_active !== false).length;
    const sandboxConfigured = companies.filter(c => c.credentials?.sandbox?.configured).length;
    const productionReady = companies.filter(c => c.fbr_environment === "production" && c.credentials?.production?.configured).length;

    return (
        <div className="super-admin-overview" style={{ display: "flex", flexDirection: "column", gap: "1.5rem" }}>
            <PageHeader
                title="Super Admin Overview"
                description="Master command center for Tax Practitioners & Lawyers managing multi-company FBR compliance."
                actions={
                    <Button variant="primary" onClick={() => navigate("/admin/companies")}>
                        Go to Companies Directory ➜
                    </Button>
                }
            />

            {/* Top Stat Cards */}
            <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))", gap: "1rem" }}>
                <Card title="Enrolled Client Companies">
                    <div style={{ fontSize: "2.2rem", fontWeight: 700, color: "#0284c7" }}>
                        {loading ? "…" : totalCompanies}
                    </div>
                    <p style={{ margin: "4px 0 0 0", fontSize: "0.85rem", color: "var(--text-muted, #64748b)" }}>
                        Total businesses registered in portal
                    </p>
                </Card>

                <Card title="Active Workspaces">
                    <div style={{ fontSize: "2.2rem", fontWeight: 700, color: "#16a34a" }}>
                        {loading ? "…" : activeCompanies}
                    </div>
                    <p style={{ margin: "4px 0 0 0", fontSize: "0.85rem", color: "var(--text-muted, #64748b)" }}>
                        Companies with active status
                    </p>
                </Card>

                <Card title="FBR Sandbox Configured">
                    <div style={{ fontSize: "2.2rem", fontWeight: 700, color: "#d97706" }}>
                        {loading ? "…" : sandboxConfigured}
                    </div>
                    <p style={{ margin: "4px 0 0 0", fontSize: "0.85rem", color: "var(--text-muted, #64748b)" }}>
                        Ready for PRAL sandbox scenario testing
                    </p>
                </Card>

                <Card title="Production Live">
                    <div style={{ fontSize: "2.2rem", fontWeight: 700, color: "#059669" }}>
                        {loading ? "…" : productionReady}
                    </div>
                    <p style={{ margin: "4px 0 0 0", fontSize: "0.85rem", color: "var(--text-muted, #64748b)" }}>
                        Active in live FBR production environment
                    </p>
                </Card>
            </div>

            {/* Recent Enrolled Companies Preview */}
            <Card title="Recent Client Companies">
                {loading ? (
                    <p role="status">Loading companies…</p>
                ) : companies.length === 0 ? (
                    <div style={{ textAlign: "center", padding: "2rem" }}>
                        <p style={{ color: "var(--text-muted, #64748b)", marginBottom: "1rem" }}>
                            No client companies enrolled yet.
                        </p>
                        <Button variant="primary" onClick={() => navigate("/admin/companies")}>
                            + Enroll First Client Company
                        </Button>
                    </div>
                ) : (
                    <div style={{ overflowX: "auto" }}>
                        <table style={{ width: "100%", borderCollapse: "collapse", textAlign: "left" }}>
                            <thead>
                                <tr style={{ borderBottom: "1px solid var(--border-color, #e2e8f0)" }}>
                                    <th style={{ padding: "8px" }}>Company Name</th>
                                    <th style={{ padding: "8px" }}>NTN / CNIC</th>
                                    <th style={{ padding: "8px" }}>Province</th>
                                    <th style={{ padding: "8px" }}>Environment</th>
                                    <th style={{ padding: "8px" }}>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                {companies.slice(0, 5).map(c => (
                                    <tr key={c.id} style={{ borderBottom: "1px solid var(--border-color, #f1f5f9)" }}>
                                        <td style={{ padding: "8px", fontWeight: 600 }}>{c.name}</td>
                                        <td style={{ padding: "8px" }}>{c.ntn_cnic || "Not configured"}</td>
                                        <td style={{ padding: "8px" }}>{c.province || "N/A"}</td>
                                        <td style={{ padding: "8px" }}>
                                            <Badge tone={c.fbr_environment === "production" ? "success" : "neutral"}>
                                                {c.fbr_environment === "production" ? "Production" : "Sandbox"}
                                            </Badge>
                                        </td>
                                        <td style={{ padding: "8px" }}>
                                            <Button variant="quiet" onClick={() => navigate("/admin/companies")}>
                                                View in Directory ➜
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>
        </div>
    );
}
