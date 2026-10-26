import { SearchableSelect } from "../../components/SearchableSelect";
import { Modal } from "../../components/Modal";
import { useRef, useState } from "react";
import { DataTable } from "../../components/DataTable";
import { Badge, Button, Card, FormActions, Input, Notice, PageHeader, Select, Textarea , RefreshButton , SearchField } from "../../components/ui";
import { failureMessage, useApiQuery } from "../../lib/useApiQuery";
import { useAuth } from "../auth/AuthContext";
import { invoiceBase, statusLabel, todayDate } from "../invoices/invoiceApi";
import type { ItemRecord } from "../items/itemApi";
import { stockApi, stockBase } from "./stockApi";
import type { MovementDraft, StockBalance, StockMovement, StockPageResult } from "./stockApi";

const trackedItem = (item: ItemRecord) => item.track_stock;
const emptyDraft = (): MovementDraft => ({ item_id: "", movement_date: todayDate(), document_number: "", movement_type: "receipt", quantity: "", notes: "", idempotency_key: crypto.randomUUID() });
export function StockPage() {
    const { activeCompany } = useAuth(); const company = activeCompany!; const owner = company.role === "owner";
    const [search, setSearch] = useState(""); const [itemId, setItemId] = useState(""); const [selectedName, setSelectedName] = useState("");
    const [from, setFrom] = useState(""); const [to, setTo] = useState(""); const [type, setType] = useState("");
    const [balancePage, setBalancePage] = useState(1); const [historyPage, setHistoryPage] = useState(1);
    const [showForm, setShowForm] = useState(false); const [draft, setDraft] = useState(emptyDraft);
    const [selectedItem, setSelectedItem] = useState<ItemRecord>(); const [reverse, setReverse] = useState<StockMovement>();
    const [reason, setReason] = useState(""); const [busy, setBusy] = useState(false); const lock = useRef(false);
    const [error, setError] = useState(""); const [message, setMessage] = useState("");
    const balances = useApiQuery<StockPageResult<StockBalance>>(stockBase(company.id) + "/balances?" + new URLSearchParams({ search, item_id: itemId, page: String(balancePage), per_page: "15" }), search ? 300 : 0);
    const history = useApiQuery<StockPageResult<StockMovement>>(stockBase(company.id) + "/movements?" + new URLSearchParams({ item_id: itemId, from, to, movement_type: type, page: String(historyPage), per_page: "15" }));
    function refresh() { balances.reload(); history.reload(); }
    async function mutate(reversing = false) {
        if (lock.current) return; lock.current = true; setBusy(true); setError(""); setMessage("");
        try {
            if (reversing && reverse) await stockApi.reverse(company.id, reverse.id, reason);
            else await stockApi.post(company.id, draft);
            setMessage(reversing ? "Reversal saved. Original history is preserved." : "Stock movement saved.");
            setReverse(undefined); setReason(""); setDraft(emptyDraft()); setSelectedItem(undefined); setShowForm(false); refresh();
        } catch (failure) { setError(failureMessage(failure)); } finally { lock.current = false; setBusy(false); }
    }
    const change = (key: keyof MovementDraft, value: string) => setDraft(current => ({ ...current, [key]: value }));
    return <>
        <PageHeader title="Stock & movements" description="Track stock balances and movement history."
            actions={owner ? <Button disabled={busy} onClick={() => { setShowForm(true); setError(""); }}>Add stock entry</Button> : undefined} />
        
        {message && <Notice tone="success">{message}</Notice>}
        {error && <Notice tone="warning">{error}</Notice>}
        {showForm && <form onSubmit={event => { event.preventDefault(); void mutate(); }}><fieldset className="form-fields" disabled={busy}><Card title="New stock movement">
            <div className="form-grid form-grid--three">
                <SearchableSelect<ItemRecord> label="Stock item" path={invoiceBase(company.id) + "/lookups/items"} value={draft.item_id}
                    selectedLabel={selectedItem ? selectedItem.name + " / " + selectedItem.sku : ""} filter={trackedItem}
                    describe={item => `${item.sku} · ${item.unit_of_measurement}`} onSelect={item => { change("item_id", String(item.id)); setSelectedItem(item); }} />
                <Select label="Entry type" value={draft.movement_type} options={["opening", "receipt", "issue", "adjustment"].map(value => ({ value, label: statusLabel(value) }))} onChange={event => change("movement_type", event.target.value)} />
                <Input label="Movement date" required type="date" max={todayDate()} value={draft.movement_date} onChange={event => change("movement_date", event.target.value)} />
                <Input label="Document number" required maxLength={100} value={draft.document_number} onChange={event => change("document_number", event.target.value)} />
                <Input label="Quantity" required type="number" min={draft.movement_type === "adjustment" ? "-999999999" : "0.000001"} max="999999999" step="any" value={draft.quantity} onChange={event => change("quantity", event.target.value)} />
            </div>
            <Textarea label="Entry notes" maxLength={5000} value={draft.notes} onChange={event => change("notes", event.target.value)} />
            <FormActions><Button variant="secondary" onClick={() => setShowForm(false)}>Cancel entry</Button><Button type="submit">{busy ? "Saving…" : "Save stock entry"}</Button></FormActions>
        </Card></fieldset></form>}
        {reverse && <form onSubmit={event => { event.preventDefault(); void mutate(true); }}><Modal title="Reverse stock movement?" busy={busy} onClose={() => setReverse(undefined)}>
            <p>{reverse.document_number}: {reverse.quantity} {reverse.unit_of_measurement}. A matching opposite entry will be saved.</p>
            <Textarea label="Reversal reason" required maxLength={5000} disabled={busy} value={reason} onChange={event => setReason(event.target.value)} />
            <FormActions><Button variant="secondary" disabled={busy} onClick={() => setReverse(undefined)}>Cancel reversal</Button><Button type="submit" disabled={busy}>{busy ? "Saving…" : "Confirm reversal"}</Button></FormActions>
        </Modal></form>}
        <Card title="Current stock" actions={<SearchField label="Search stock balances" maxLength={100} value={search} onChange={event => { setSearch(event.target.value); setBalancePage(1); }} />}><div className="toolbar">
            
            {itemId && <div><p>Selected item: {selectedName}</p><Button variant="quiet" onClick={() => { setItemId(""); setSelectedName(""); setBalancePage(1); setHistoryPage(1); }}>Show all items</Button></div>}
        </div>
            {balances.error && <Notice tone="warning">{balances.error} <RefreshButton variant="quiet" onClick={balances.reload} label="Retry balances" /></Notice>}
            {balances.loading ? <p role="status">Loading balances…</p> : <DataTable caption="Current stock balances" rows={balances.data?.data || []} rowKey={row => String(row.id)}
                pagination={{ page: balances.data?.current_page || balancePage, pages: balances.data?.last_page || 1, total: balances.data?.total || 0, pageSize: 15, onPage: setBalancePage }}
                columns={[
                    { key: "name", label: "Item", render: row => <>{row.name}<div className="cell-subtitle">{row.sku}</div></> },
                    { key: "opening", label: "Opening", render: row => row.opening },
                    { key: "received", label: "Received", render: row => row.received },
                    { key: "issued", label: "Issued", render: row => row.issued },
                    { key: "adjusted", label: "Adjustments / reversals", render: row => row.adjusted },
                    { key: "available", label: "Available", render: row => <Badge tone={Number(row.available) < Number(row.reorder_level) ? "warning" : "success"}>{row.available}{Number(row.available) < Number(row.reorder_level) ? " • Low stock" : ""}</Badge> },
                    { key: "unit", label: "Unit", render: row => row.unit_of_measurement },
                    { key: "actions", label: "Actions", render: row => <Button variant="quiet" onClick={() => { setItemId(String(row.id)); setSelectedName(row.name); setBalancePage(1); setHistoryPage(1); }}>View movements</Button> },
                ]} />}
            
        </Card>
        <Card title="Movement history"><div className="toolbar">
            <Input label="From date" type="date" value={from} max={to || undefined} onChange={event => { setFrom(event.target.value); setHistoryPage(1); }} />
            <Input label="To date" type="date" value={to} min={from || undefined} onChange={event => { setTo(event.target.value); setHistoryPage(1); }} />
            <Select label="Movement type" options={[{ value: "", label: "All" }, ...["opening", "receipt", "issue", "adjustment", "reversal"].map(value => ({ value, label: statusLabel(value) }))]} value={type} onChange={event => { setType(event.target.value); setHistoryPage(1); }} />
        </div>
            {history.error && <Notice tone="warning">{history.error} <RefreshButton variant="quiet" onClick={history.reload} label="Retry history" /></Notice>}
            {history.loading ? <p role="status">Loading movements…</p> : <DataTable caption="Stock movement history" rows={history.data?.data || []} rowKey={row => String(row.id)}
                pagination={{ page: history.data?.current_page || historyPage, pages: history.data?.last_page || 1, total: history.data?.total || 0, pageSize: 15, onPage: setHistoryPage }}
                columns={[
                    { key: "date", label: "Date", render: row => row.movement_date }, { key: "document", label: "Document", render: row => row.document_number },
                    { key: "item", label: "Item", render: row => row.item_name },
                    { key: "type", label: "Type", render: row => <>{statusLabel(row.movement_type)}{row.reversed_by_id && <div className="cell-subtitle">Reversed</div>}</> },
                    { key: "quantity", label: "Movement", render: row => <Badge tone={Number(row.quantity) > 0 ? "success" : "warning"}>{row.quantity}</Badge> },
                    { key: "balance", label: "Running balance", render: row => row.running_balance },
                    { key: "notes", label: "Notes", render: row => row.notes || "—" },
                    { key: "actions", label: "Actions", render: row => owner && !row.reversed_by_id && !row.reversal_of_id && !row.invoice_item_id
                        ? <Button variant="quiet" disabled={busy} onClick={() => { setReverse(row); setReason(""); setError(""); }}>Reverse</Button> : "—" },
                ]} />}
        </Card>
    </>;
}
