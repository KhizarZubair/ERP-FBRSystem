import { Badge, Button, Card, Notice, PageHeader , RefreshButton } from "../../components/ui";
import { DataTable } from "../../components/DataTable";
import { useApiQuery } from "../../lib/useApiQuery";
import { useAuth } from "../auth/AuthContext";
import { money, statusLabel } from "../invoices/invoiceApi";
import type { InvoiceRecord } from "../invoices/invoiceApi";
import { navigate } from "../../lib/navigation";

type Summary = { saved_total: string; saved_tax: string; invoice_count: number; draft_count: number; accepted_count: number;
    attention_count: number; low_stock_count: number; customer_count: number; item_count: number; recent_invoices: InvoiceRecord[] };
export function OverviewPage() {
    const { activeCompany } = useAuth();
    const company = activeCompany;
    const isCompanyConfigured = Boolean(company?.id && company?.ntn_cnic);
    const query = useApiQuery<{ data: Summary }>(isCompanyConfigured && company ? `/api/v1/companies/${company.id}/overview` : null);
    const summary = query.data?.data;
    return <>
        <PageHeader title="Your business at a glance" description="Current saved invoices, master data and local stock."
            actions={<div className="actions">{isCompanyConfigured && <RefreshButton variant="secondary" onClick={query.reload} label="Refresh overview" />}
                {isCompanyConfigured && company?.role === "owner" && <Button onClick={() => navigate("/invoices/new")}>Create invoice</Button>}</div>} />
        {!isCompanyConfigured && <Card title="Company Setup Incomplete"><p className="muted">Your ERP features are locked until your company details and seller NTN are configured.</p><div className="actions section-gap"><Button onClick={() => navigate("/company")}>Company Settings</Button></div></Card>}
        {query.error && <Notice tone="warning">{query.error} <RefreshButton variant="quiet" onClick={query.reload} label="Try again" /></Notice>}
        {query.loading && <p role="status">Loading overview…</p>}
        {summary && <>
            <div className="stats-grid">{[
                ["Saved invoice total", money(summary.saved_total), "Includes drafts; excludes cancelled invoices"],
                ["Sales tax on saved invoices", money(summary.saved_tax), "Stored invoice totals; not tax paid"],
                ["Invoices / drafts", `${summary.invoice_count} / ${summary.draft_count}`, `${summary.accepted_count} accepted by FBR`],
                ["Low-stock items", String(summary.low_stock_count), `${summary.customer_count} customers • ${summary.item_count} catalog items`],
            ].map(([label, value, note]) => <Card key={label} className="stat-card"><div className="stat-label">{label}</div><div className="stat-value">{value}</div><div className="stat-note">{note}</div></Card>)}</div>
            <Card title="Recent sales invoices"><DataTable caption="Recent sales invoices" rows={summary.recent_invoices} rowKey={row => String(row.id)} pageSize={5} columns={[
                { key: "number", label: "Invoice", render: row => <Button variant="quiet" onClick={() => navigate(`/invoices/${row.id}/review`)}>{row.number}</Button> },
                { key: "buyer", label: "Customer", render: row => row.buyer_business_name },
                { key: "date", label: "Date", render: row => row.invoice_date },
                { key: "total", label: "Total", render: row => money(row.total_values) },
                { key: "status", label: "Status", render: row => <>{statusLabel(row.status)}<div className="cell-subtitle"><Badge tone={row.fbr_status === "accepted" ? "success" : "neutral"}>{statusLabel(row.fbr_status)}</Badge></div></> },
            ]} /></Card>
            <Card title="Needs your attention"><p>{summary.low_stock_count} items below reorder level • {summary.attention_count} FBR outcomes need review</p>
                <div className="actions section-gap"><Button variant="secondary" onClick={() => navigate("/invoices")}>Review invoices</Button>
                    <Button variant="secondary" onClick={() => navigate("/stock")}>Stock ledger</Button>
                    <Button variant="secondary" onClick={() => navigate("/company")}>Company setup</Button></div></Card>
        </>}
    </>;
}
