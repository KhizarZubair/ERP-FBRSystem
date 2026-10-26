import { Modal } from "../../components/Modal";
import { useEffect, useRef, useState } from "react";
import { DataTable } from "../../components/DataTable";
import { Button, Card, Notice , RefreshButton } from "../../components/ui";
import { money } from "../../data/demo";
import { ApiError } from "../../lib/api";
import { itemApi } from "./itemApi";
import type { Paginated, PriceRecord } from "./itemApi";
import { PriceForm } from "./PriceForm";

export function ItemPrices({ companyId, itemId, canEdit }: { companyId: number; itemId: number; canEdit: boolean }) {
    const [result, setResult] = useState<Paginated<PriceRecord>>();
    const [page, setPage] = useState(1);
    const [revision, setRevision] = useState(0);
    const [loading, setLoading] = useState(true);
    const [editing, setEditing] = useState<PriceRecord | "new" | null>(null);
    const [pendingDelete, setPendingDelete] = useState<PriceRecord | null>(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState("");
    const [message, setMessage] = useState("");
    const lock = useRef(false);
    useEffect(() => {
        const controller = new AbortController();
        itemApi.prices(companyId, itemId, page, controller.signal).then(response => {
            if (controller.signal.aborted) return;
            if (response.meta.current_page > response.meta.last_page) { setPage(response.meta.last_page); return; }
            setResult(response);
        }).catch(failure => { if (!controller.signal.aborted) setError(failure instanceof Error ? failure.message : "Could not load prices."); })
            .finally(() => { if (!controller.signal.aborted) setLoading(false); });
        return () => controller.abort();
    }, [companyId, itemId, page, revision]);
    function reload() { setError(""); setLoading(true); setRevision(value => value + 1); }
    return <Card title="Selling prices" actions={canEdit && !editing && !result?.data.length ? <Button onClick={() => { setMessage(""); setEditing("new"); }}>Add price</Button> : undefined}>
        {message && <Notice tone="success">{message}</Notice>}
        {error && <Notice tone="warning">{error} <RefreshButton variant="quiet" onClick={reload} label="Try again" /></Notice>}
        {editing && <PriceForm key={editing === "new" ? "new" : editing.id} {...{ companyId, itemId }} price={editing === "new" ? undefined : editing}
            onCancel={() => setEditing(null)} onSaved={() => { setEditing(null); setMessage("Price saved successfully."); reload(); }} />}
        {pendingDelete && <Modal title="Delete price?" busy={busy} onClose={() => setPendingDelete(null)}><p>Delete this saved selling price?</p>
            <div className="actions"><Button variant="secondary" disabled={busy} onClick={() => setPendingDelete(null)}>Cancel deletion</Button>
                <Button variant="danger" disabled={busy} onClick={async () => {
                    if (lock.current) return; lock.current = true; setBusy(true); setError("");
                    try { await itemApi.deletePrice(companyId, itemId, pendingDelete.id); setPendingDelete(null); setMessage("Price deleted successfully."); reload(); }
                    catch (failure) { setError(failure instanceof ApiError ? Object.values(failure.errors)[0]?.[0] || failure.message : failure instanceof Error ? failure.message : "Could not delete price."); }
                    finally { lock.current = false; setBusy(false); }
                }}>{busy ? "Deleting…" : "Confirm price deletion"}</Button></div></Modal>}
        {loading ? <p role="status">Loading prices…</p> : <DataTable caption="Item price history" rows={result?.data || []} rowKey={price => String(price.id)}
            pagination={{ page: result?.meta.current_page || 1, pages: result?.meta.last_page || 1, total: result?.meta.total || 0, pageSize: 15, onPage: value => { setPage(value); setLoading(true); setError(""); } }}
            columns={[
                { key: "price", label: "Selling price (PKR, before tax)", render: price => money(Number(price.unit_price)) },
                { key: "actions", label: "Actions", render: price => canEdit ? <div className="actions">
                    <Button variant="quiet" disabled={busy} onClick={() => { setError(""); setEditing(price); }}>Edit price</Button>
                    <Button variant="danger" disabled={busy} onClick={() => setPendingDelete(price)}>Delete price</Button>
                </div> : "Read only" },
            ]} />}
    </Card>;
}
