import { Modal } from "../../components/Modal";
import { useEffect, useRef, useState } from "react";
import { DataTable } from "../../components/DataTable";
import { Badge, Button, Card, Notice, PageHeader, Select , RefreshButton , SearchField } from "../../components/ui";
import { navigate } from "../../lib/navigation";
import { ApiError } from "../../lib/api";
import { useAuth } from "../auth/AuthContext";
import { CustomerForm } from "./CustomerForm";
import { customerApi } from "./customerApi";
import type { CustomerPage, CustomerRecord } from "./customerApi";

function CustomerEditor({ companyId, id, canEdit }: { companyId: number; id: string; canEdit: boolean }) {
    const [customer, setCustomer] = useState<CustomerRecord>();
    const [error, setError] = useState("");
    const [retry, setRetry] = useState(0);
    useEffect(() => {
        if (id === "new") return;
        let active = true;
        customerApi.get(companyId, Number(id)).then(response => {
            if (active) setCustomer(response.data);
        }).catch(failure => { if (active) setError(failure instanceof Error ? failure.message : "Could not load customer."); });
        return () => { active = false; };
    }, [companyId, id, retry]);
    return <>
        <PageHeader title={id === "new" ? "Add customer" : canEdit ? "Edit customer" : "Customer details"} description="Save buyer identity and billing information."
            actions={<Button variant="secondary" onClick={() => navigate("/customers")}>Back to customers</Button>} />
        {error ? <Notice tone="warning">{error} <RefreshButton variant="quiet" onClick={() => { setError(""); setRetry(value => value + 1); }} label="Try again" /></Notice>
            : id === "new" || customer ? canEdit ? <CustomerForm companyId={companyId} customer={customer} /> : <Card title="Customer details"><div className="form-grid">{Object.entries(customer || {}).filter(([key]) => key !== "id").map(([key, value]) => <div key={key}><strong>{key.replaceAll("_", " ")}</strong><p>{typeof value === "boolean" ? value ? "Active" : "Inactive" : value || "Not provided"}</p></div>)}</div></Card> : <p role="status">Loading customer…</p>}
    </>;
}

function CustomerDirectory({ companyId, canEdit }: { companyId: number; canEdit: boolean }) {
    const [search, setSearch] = useState("");
    const [registration, setRegistration] = useState("");
    const [page, setPage] = useState(1);
    const [sort, setSort] = useState({ key: "name", descending: false });
    const [result, setResult] = useState<CustomerPage>();
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const [message, setMessage] = useState("");
    const [revision, setRevision] = useState(0);
    const [busy, setBusy] = useState<number | null>(null);
    const [pendingStatus, setPendingStatus] = useState<CustomerRecord | null>(null);
    const [pendingDelete, setPendingDelete] = useState<CustomerRecord | null>(null);
    const lock = useRef(false);
    useEffect(() => {
        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            setLoading(true); setError(""); setResult(undefined);
            customerApi.list(companyId, { search, registration_type: registration, page, sort: sort.key, direction: sort.descending ? "desc" : "asc" }, controller.signal)
                .then(response => {
                    if (controller.signal.aborted) return;
                    if (response.meta.current_page > response.meta.last_page) { setPage(response.meta.last_page); return; }
                    setResult(response);
                }).catch(failure => {
                    if (!controller.signal.aborted) setError(failure instanceof Error ? failure.message : "Could not load customers.");
                }).finally(() => { if (!controller.signal.aborted) setLoading(false); });
        }, search ? 300 : 0);
        return () => { window.clearTimeout(timer); controller.abort(); };
    }, [companyId, search, registration, page, sort, revision]);

    async function mutate(customer: CustomerRecord, deleting = false) {
        if (lock.current) return;
        lock.current = true; setBusy(customer.id); setError(""); setMessage("");
        try {
            if (deleting) await customerApi.delete(companyId, customer.id);
            else await customerApi.status(companyId, customer.id, !customer.is_active);
            setPendingDelete(null); setPendingStatus(null);
            setMessage(deleting ? "Customer deleted successfully." : "Customer status updated.");
            setRevision(value => value + 1);
        } catch (failure) {
            setError(failure instanceof ApiError ? failure.errors.customer?.[0] || failure.message : failure instanceof Error ? failure.message : "Could not update customer.");
        } finally { lock.current = false; setBusy(null); }
    }
    return <>
        <PageHeader title="Customers" description="Buyer records saved in your company workspace."
            actions={canEdit ? <Button onClick={() => navigate("/customers/new")}>Add customer</Button> : undefined} />
        {message && <Notice tone="success">{message}</Notice>}
        {error && <Notice tone="warning">{error} <RefreshButton variant="quiet" onClick={() => setRevision(value => value + 1)} label="Try again" /></Notice>}
        {pendingStatus && <Modal title={(pendingStatus.is_active ? "Deactivate" : "Activate") + " customer?"} busy={busy !== null} onClose={() => setPendingStatus(null)} footer={<><Button variant="secondary" disabled={busy !== null} onClick={() => setPendingStatus(null)}>Cancel</Button><Button disabled={busy !== null} onClick={() => { void mutate(pendingStatus); }}>{busy !== null ? "Updating…" : pendingStatus.is_active ? "Confirm deactivate" : "Confirm activate"}</Button></>}>{error && <Notice tone="warning">{error}</Notice>}<p>{pendingStatus.is_active ? "Deactivate" : "Activate"} {pendingStatus.name}?</p></Modal>}
        {pendingDelete && <Modal title="Delete customer?" busy={busy !== null} onClose={() => setPendingDelete(null)}><p>Delete {pendingDelete.name}? Customers with invoices must be deactivated instead.</p>
            <div className="actions"><Button variant="secondary" disabled={busy !== null} onClick={() => setPendingDelete(null)}>Cancel</Button>
                <Button variant="danger" disabled={busy !== null} onClick={() => { void mutate(pendingDelete, true); }}>{busy !== null ? "Deleting…" : "Confirm delete"}</Button></div></Modal>}
        <Card title="Customer directory" actions={<SearchField label="Search customers" maxLength={100} value={search}
                    onChange={event => { setSearch(event.target.value); setPage(1); setLoading(true); }} />}>
            <div className="toolbar">
                
                <Select label="Registration" options={[{ value: "", label: "All" }, "Registered", "Unregistered"]} value={registration}
                    onChange={event => { setRegistration(event.target.value); setPage(1); setLoading(true); }} />
            </div>
            {loading ? <p role="status">Loading customers…</p> : <DataTable caption="Customer directory" rows={result?.data || []} rowKey={customer => String(customer.id)}
                pagination={{ page: result?.meta.current_page || 1, pages: result?.meta.last_page || 1, total: result?.meta.total || 0, pageSize: 15,
                    onPage: value => { setPage(value); setLoading(true); }, sort, onSort: (key, descending) => { setSort({ key, descending }); setPage(1); setLoading(true); } }}
                columns={[
                    { key: "name", label: "Customer", sortValue: customer => customer.name, render: customer => <><strong>{customer.name}</strong><div className="cell-subtitle">{customer.code}</div></> },
                    { key: "ntn", label: "NTN / CNIC", render: customer => customer.ntn_cnic || "Not provided" },
                    { key: "registration_type", label: "Registration", sortValue: customer => customer.registration_type, render: customer => customer.registration_type },
                    { key: "province", label: "Province", sortValue: customer => customer.province, render: customer => customer.province },
                    { key: "status", label: "Status", render: customer => <Badge tone={customer.is_active ? "success" : "neutral"}>{customer.is_active ? "Active" : "Inactive"}</Badge> },
                    { key: "actions", label: "Actions", render: customer => <div className="actions">
                        <Button variant="quiet" onClick={() => navigate(`/customers/${customer.id}`)}>{canEdit ? "Edit" : "View"}</Button>
                        {canEdit && <><Button variant="quiet" disabled={busy !== null} onClick={() => { setError(""); setPendingStatus(customer); }}>{customer.is_active ? "Deactivate" : "Activate"}</Button>
                            <Button variant="danger" disabled={busy !== null} onClick={() => { setError(""); setPendingDelete(customer); }}>Delete</Button></>}
                    </div> },
                ]} />}
        </Card>
    </>;
}
export function CustomersPage({ path }: { path: string }) {
    const { activeCompany } = useAuth();
    const company = activeCompany!;
    const id = path.split("/")[2];
    if (id) {
        if (id === "new" && company.role !== "owner") return <Notice tone="warning">Only the company owner can edit customer details.</Notice>;
        return <CustomerEditor companyId={company.id} id={id} canEdit={company.role === "owner"} />;
    }
    return <CustomerDirectory companyId={company.id} canEdit={company.role === "owner"} />;
}
