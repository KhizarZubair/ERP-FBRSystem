import { Modal } from "../../components/Modal";
import { useEffect, useRef, useState } from "react";
import { DataTable } from "../../components/DataTable";
import { Badge, Button, Card, Notice, PageHeader, Select , RefreshButton , SearchField } from "../../components/ui";
import { money } from "../../data/demo";
import { ApiError } from "../../lib/api";
import { navigate } from "../../lib/navigation";
import { useAuth } from "../auth/AuthContext";
import { ItemForm } from "./ItemForm";
import { ItemPrices } from "./ItemPrices";
import { itemApi } from "./itemApi";
import type { ItemRecord, Paginated } from "./itemApi";

function ItemEditor({ companyId, id, canEdit }: { companyId: number; id: string; canEdit: boolean }) {
    const [item, setItem] = useState<ItemRecord>();
    const [editing, setEditing] = useState(id === "new");
    const [error, setError] = useState("");
    const [revision, setRevision] = useState(0);
    const [saved, setSaved] = useState(false);
    useEffect(() => {
        if (id === "new") return;
        let active = true;
        itemApi.get(companyId, Number(id)).then(response => { if (active) setItem(response.data); })
            .catch(failure => { if (active) setError(failure instanceof Error ? failure.message : "Could not load item."); });
        return () => { active = false; };
    }, [companyId, id, revision]);
    return <>
        <PageHeader title={id === "new" ? "Create Product" : "Product details"} description="Product classification, pricing, and FBR tax defaults."
            actions={<><Button variant="secondary" onClick={() => navigate("/items")}>Back to products</Button>
                {item && canEdit && !editing && <Button onClick={() => { setSaved(false); setEditing(true); }}>Edit product</Button>}</>} />
        {saved && <Notice tone="success">Product saved successfully.</Notice>}
        {error ? <Notice tone="warning">{error} <RefreshButton variant="quiet" onClick={() => { setError(""); setRevision(value => value + 1); }} label="Try again" /></Notice>
            : id === "new" || item ? editing ? <ItemForm {...{ companyId, item }} onCancel={() => id === "new" ? navigate("/items") : setEditing(false)}
                onSaved={record => { if (id === "new") navigate("/items/" + record.id); else { setItem(record); setEditing(false); setSaved(true); } }} />
                : item && <Card title="Product details"><div className="form-grid">
                    {[["Product name", item.name], ["SKU", item.sku], ["Description", item.description], ["HS code", item.hs_code],
                        ["Unit", item.unit_of_measurement], ["Sale type", item.sale_type], ["Tax rate", item.tax_rate_label],
                        ["Track stock", item.track_stock ? "Yes" : "No"], ["Reorder level", item.reorder_level],
                        ["Notified / retail value", item.fixed_notified_value_or_retail_price], ["SRO schedule", item.sro_schedule_no || "Not provided"],
                        ["SRO serial", item.sro_item_serial_no || "Not provided"], ["Status", item.is_active ? "Active" : "Inactive"]].map(([label, value]) =>
                            <div key={label}><strong>{label}</strong><p>{value}</p></div>)}
                </div></Card>
            : <p role="status">Loading item…</p>}
        {item && <ItemPrices {...{ companyId, canEdit }} itemId={item.id} />}
    </>;
}

function ItemDirectory({ companyId, canEdit }: { companyId: number; canEdit: boolean }) {
    const [search, setSearch] = useState("");
    const [status, setStatus] = useState("");
    const [sort, setSort] = useState({ key: "name", descending: false });
    const [page, setPage] = useState(1);
    const [result, setResult] = useState<Paginated<ItemRecord>>();
    const [loading, setLoading] = useState(true);
    const [revision, setRevision] = useState(0);
    const [error, setError] = useState("");
    const [message, setMessage] = useState("");
    const [busy, setBusy] = useState(false);
    const [pendingStatus, setPendingStatus] = useState<ItemRecord | null>(null);
    const [pendingDelete, setPendingDelete] = useState<ItemRecord | null>(null);
    const lock = useRef(false);
    useEffect(() => {
        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            setLoading(true); setError(""); setResult(undefined);
            itemApi.list(companyId, { search, is_active: status, price_list: "Standard", page, sort: sort.key, direction: sort.descending ? "desc" : "asc" }, controller.signal)
                .then(response => { if (!controller.signal.aborted) { if (response.meta.current_page > response.meta.last_page) setPage(response.meta.last_page); else setResult(response); } })
                .catch(failure => { if (!controller.signal.aborted) setError(failure instanceof Error ? failure.message : "Could not load items."); })
                .finally(() => { if (!controller.signal.aborted) setLoading(false); });
        }, search ? 300 : 0);
        return () => { window.clearTimeout(timer); controller.abort(); };
    }, [companyId, search, status, page, sort, revision]);
    async function mutate(item: ItemRecord, deleting = false) {
        if (lock.current) return; lock.current = true; setBusy(true); setError(""); setMessage("");
        try {
            if (deleting) await itemApi.delete(companyId, item.id); else await itemApi.status(companyId, item.id, !item.is_active);
            setPendingDelete(null); setPendingStatus(null); setMessage(deleting ? "Product deleted successfully." : "Product status updated."); setRevision(value => value + 1);
        } catch (failure) { setError(failure instanceof ApiError ? failure.errors.item?.[0] || failure.message : failure instanceof Error ? failure.message : "Could not update item."); }
        finally { lock.current = false; setBusy(false); }
    }
    return <>
        <PageHeader title="Products & prices" description="Your company product catalog and effective selling prices."
            actions={canEdit ? <Button onClick={() => navigate("/items/new")}>Create Product</Button> : undefined} />
        {message && <Notice tone="success">{message}</Notice>}
        {error && <Notice tone="warning">{error} <RefreshButton variant="quiet" onClick={() => setRevision(value => value + 1)} label="Try again" /></Notice>}
        {pendingStatus && <Modal title={(pendingStatus.is_active ? "Deactivate" : "Activate") + " product?"} busy={busy} onClose={() => setPendingStatus(null)} footer={<><Button variant="secondary" disabled={busy} onClick={() => setPendingStatus(null)}>Cancel</Button><Button disabled={busy} onClick={() => { void mutate(pendingStatus); }}>{busy ? "Updating…" : pendingStatus.is_active ? "Confirm deactivate" : "Confirm activate"}</Button></>}>{error && <Notice tone="warning">{error}</Notice>}<p>{pendingStatus.is_active ? "Deactivate" : "Activate"} {pendingStatus.name}?</p></Modal>}
        {pendingDelete && <Modal title="Delete product?" busy={busy} onClose={() => setPendingDelete(null)}><p>Delete product {pendingDelete.name} and its prices? Products with invoice or stock history must be deactivated instead.</p>
            <div className="actions"><Button variant="secondary" disabled={busy} onClick={() => setPendingDelete(null)}>Cancel deletion</Button>
                <Button variant="danger" disabled={busy} onClick={() => { void mutate(pendingDelete, true); }}>{busy ? "Deleting…" : "Confirm product deletion"}</Button></div></Modal>}
        <Card title="Product catalog" actions={<SearchField label="Search products" maxLength={100} value={search} onChange={event => { setSearch(event.target.value); setPage(1); setLoading(true); }} />}><div className="toolbar">
            
            <Select label="Status" options={[{ value: "", label: "All" }, { value: "1", label: "Active" }, { value: "0", label: "Inactive" }]} value={status} onChange={event => { setStatus(event.target.value); setPage(1); setLoading(true); }} />
        </div>
            {loading ? <p role="status">Loading items…</p> : <DataTable caption="Product catalog" rows={result?.data || []} rowKey={item => String(item.id)}
                pagination={{ page: result?.meta.current_page || 1, pages: result?.meta.last_page || 1, total: result?.meta.total || 0, pageSize: 15,
                    onPage: value => { setPage(value); setLoading(true); }, sort, onSort: (key, descending) => { setSort({ key, descending }); setPage(1); setLoading(true); } }}
                columns={[
                    { key: "name", label: "Product / SKU", sortValue: item => item.name, render: item => <><strong>{item.name}</strong><div className="cell-subtitle">{item.sku}</div></> },
                    { key: "hs_code", label: "HS code", sortValue: item => item.hs_code, render: item => item.hs_code },
                    { key: "unit", label: "Unit", render: item => item.unit_of_measurement },
                    { key: "price", label: "Current price excl. tax", sortValue: item => Number(item.current_unit_price || 0), render: item => item.current_unit_price === null || item.current_unit_price === undefined ? "No effective price" : money(Number(item.current_unit_price)) },
                    { key: "stock", label: "On hand", render: item => item.track_stock ? item.on_hand || "0" : "Not tracked" },
                    { key: "status", label: "Status", render: item => <Badge tone={item.is_active ? "success" : "neutral"}>{item.is_active ? "Active" : "Inactive"}</Badge> },
                    { key: "actions", label: "Actions", render: item => <div className="actions"><Button variant="quiet" onClick={() => navigate("/items/" + item.id)}>View / prices</Button>
                        {canEdit && <><Button variant="quiet" disabled={busy} onClick={() => { setError(""); setPendingStatus(item); }}>{item.is_active ? "Deactivate" : "Activate"}</Button>
                            <Button variant="danger" disabled={busy} onClick={() => { setError(""); setPendingDelete(item); }}>Delete item</Button></>}
                    </div> },
                ]} />}
        </Card>
    </>;
}
export function ItemsPage({ path }: { path: string }) {
    const { activeCompany } = useAuth(); const company = activeCompany!; const id = path.split("/")[2];
    if (id === "new" && company.role !== "owner") return <Notice tone="warning">Only the company owner can create items.</Notice>;
    return id ? <ItemEditor companyId={company.id} id={id} canEdit={company.role === "owner"} /> : <ItemDirectory companyId={company.id} canEdit={company.role === "owner"} />;
}
