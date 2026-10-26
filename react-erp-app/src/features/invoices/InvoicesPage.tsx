import { useState } from "react";
import { DataTable } from "../../components/DataTable";
import { Badge, Button, Card, Input, Notice, PageHeader, Select , RefreshButton , SearchField } from "../../components/ui";
import { navigate } from "../../lib/navigation";
import { useApiQuery } from "../../lib/useApiQuery";
import { useAuth } from "../auth/AuthContext";
import { invoiceBase, money, statusLabel } from "./invoiceApi";
import type { InvoiceRecord, PageResult } from "./invoiceApi";

export function InvoicesPage() {
    const { activeCompany } = useAuth(); const company = activeCompany!;
    const [search, setSearch] = useState(""); const [status, setStatus] = useState(""); const [fbr, setFbr] = useState("");
    const [from, setFrom] = useState(""); const [to, setTo] = useState(""); const [page, setPage] = useState(1);
    const [sort, setSort] = useState({ key: "invoice_date", descending: true });
    const query = useApiQuery<PageResult<InvoiceRecord>>(invoiceBase(company.id) + "?" + new URLSearchParams({
        search, status, fbr_status: fbr, from, to, page: String(page), per_page: "15", sort: sort.key, direction: sort.descending ? "desc" : "asc",
    }), search ? 300 : 0);
    const filter = (setter: (value: string) => void, value: string) => { setter(value); setPage(1); };
    return <>
        <PageHeader title="Sales invoices" description="Saved company invoices and their actual FBR status."
            actions={company.role === "owner" ? <Button onClick={() => navigate("/invoices/new")}>New invoice</Button> : undefined} />
        {query.error && <Notice tone="warning">{query.error} <RefreshButton variant="quiet" onClick={query.reload} label="Try again" /></Notice>}
        <Card title="Invoice register" actions={<SearchField label="Search invoices" maxLength={100} value={search} onChange={event => filter(setSearch, event.target.value)} />}>
            <div className="toolbar">
                
                <Select label="Invoice status" options={[{ value: "", label: "All" }, ...["draft", "approved", "issued", "cancelled"].map(value => ({ value, label: statusLabel(value) }))]} value={status} onChange={event => filter(setStatus, event.target.value)} />
                <Select label="FBR status" options={[{ value: "", label: "All" }, ...["not_submitted", "pending", "validation_failed", "accepted", "rejected", "unknown"].map(value => ({ value, label: statusLabel(value) }))]} value={fbr} onChange={event => filter(setFbr, event.target.value)} />
                <Input label="From date" type="date" value={from} max={to || undefined} onChange={event => filter(setFrom, event.target.value)} />
                <Input label="To date" type="date" value={to} min={from || undefined} onChange={event => filter(setTo, event.target.value)} />
            </div>
            {query.loading ? <p role="status">Loading invoices…</p> : <DataTable caption="Sales invoice register" rows={query.data?.data || []} rowKey={row => String(row.id)}
                pagination={{ page: query.data?.meta.current_page || page, pages: query.data?.meta.last_page || 1, total: query.data?.meta.total || 0, pageSize: 15,
                    onPage: setPage, sort, onSort: (key, descending) => { setSort({ key, descending }); setPage(1); } }}
                columns={[
                    { key: "number", label: "Invoice", sortValue: row => row.number, render: row => <strong>{row.number}</strong> },
                    { key: "buyer_business_name", label: "Buyer", sortValue: row => row.buyer_business_name, render: row => row.buyer_business_name },
                    { key: "invoice_date", label: "Date", sortValue: row => row.invoice_date, render: row => row.invoice_date },
                    { key: "total_values", label: "Total", sortValue: row => Number(row.total_values), render: row => money(row.total_values) },
                    { key: "status", label: "Invoice status", render: row => <Badge>{statusLabel(row.status)}</Badge> },
                    { key: "fbr", label: "FBR status", render: row => <Badge tone={row.fbr_status === "accepted" ? "success" : "neutral"}>{statusLabel(row.fbr_status)}</Badge> },
                    { key: "actions", label: "Actions", render: row => <div className="actions"><Button variant="quiet" onClick={() => navigate(`/invoices/${row.id}/review`)}>Open invoice</Button>
                        {company.role === "owner" && row.editable && <Button variant="quiet" onClick={() => navigate(`/invoices/${row.id}/edit`)}>Edit</Button>}</div> },
                ]} />}
        </Card>
    </>;
}
