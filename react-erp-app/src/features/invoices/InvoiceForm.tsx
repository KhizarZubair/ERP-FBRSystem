import { CustomerSelect } from "./CustomerSelect";
import { useEffect, useRef, useState } from "react";
import { Modal } from "../../components/Modal";
import { Button, Card, FormActions, Input, Notice, PageHeader, Select, Textarea, RefreshButton } from "../../components/ui";
import { api } from "../../lib/api";
import { navigate } from "../../lib/navigation";
import { failureMessage } from "../../lib/useApiQuery";
import { useAuth } from "../auth/AuthContext";
import type { CustomerRecord } from "../customers/customerApi";
import { companyApi } from "../company/companyApi";
import type { CompanyDetails } from "../company/companyApi";
import { blankLine, draftFrom, invoiceApi, invoiceBase } from "./invoiceApi";
import type { InvoiceDraft, InvoiceRecord, InvoicePreview } from "./invoiceApi";
import { InvoiceLineEditor } from "./InvoiceLineEditor";
import { InvoiceTotals } from "./InvoiceTotals";
import { InvoiceSummary } from "./InvoiceSummary";
import { InvoiceConfirmDialog } from "./InvoiceConfirmDialog";

export function InvoiceForm({ id }: { id?: string }) {
    const { activeCompany } = useAuth(); const company = activeCompany!; const owner = company.role === "owner";
    const [draft, setDraft] = useState<InvoiceDraft>(() => draftFrom());
    const invoiceLines = draft.lines;
    function updateLine(index: number, updated: typeof draft.lines[number]) {
        setDraft(value => ({ ...value, lines: value.lines.map((line, position) => position === index ? updated : line) }));
    }
    const [preview, setPreview] = useState<InvoicePreview>(); const [savedInvoice, setSavedInvoice] = useState<InvoiceRecord>();
    const [original, setOriginal] = useState<InvoiceRecord>();
    const [seller, setSeller] = useState<CompanyDetails>();
    const [loadError, setLoadError] = useState(""); const [retry, setRetry] = useState(0);
    const [selectedBuyer, setSelectedBuyer] = useState<CustomerRecord>();
    const [busy, setBusy] = useState(false); const lock = useRef(false); const [error, setError] = useState("");
    useEffect(() => {
        let active = true;
        Promise.all([companyApi.get(company.id), id ? api<{ data: InvoiceRecord }>(invoiceBase(company.id) + "/" + id) : Promise.resolve(null)])
            .then(([profile, record]) => { if (!active) return; setSeller(profile.data); if (record) { setOriginal(record.data); setDraft(draftFrom(record.data)); } })
            .catch(failure => { if (active) setLoadError(failureMessage(failure)); });
        return () => { active = false; };
    }, [company.id, id, retry]);
    const buyer = selectedBuyer;
    const savedBuyer = original && String(draft.customer_id) === String(original.customer_id) ? original : undefined;
    const sellerName = original ? original.seller_business_name : seller?.name;
    const sellerIdentity = original ? original.seller_ntn_cnic : seller?.ntn_cnic;
    const sellerAddress = original ? original.seller_address : seller?.address;
    const update = (key: keyof Omit<InvoiceDraft, "lines">, value: string) => setDraft(valueBefore => ({ ...valueBefore, [key]: value }));
    async function showPreview() {
        if (lock.current) return; lock.current = true; setBusy(true); setError("");
        try { const result = await invoiceApi.preview(company.id, { ...draft, lines: invoiceLines }, id); setPreview(result.data); }
        catch (failure) { setError(failureMessage(failure)); }
        finally { lock.current = false; setBusy(false); }
    }
    async function save() {
        if (lock.current || !preview) return; lock.current = true; setBusy(true); setError("");
        try {
            const response = await invoiceApi.save(company.id, { ...draft, lines: invoiceLines }, id);
            setPreview(undefined);
            setSavedInvoice(response.data);
        } catch (failure) { setError(failureMessage(failure)); } finally { lock.current = false; setBusy(false); }
    }
    if (!owner) return <Notice tone="warning">Only the company owner can save invoices.</Notice>;
    if (loadError) return <Notice tone="warning">{loadError} <RefreshButton variant="quiet" onClick={() => { setLoadError(""); setRetry(value => value + 1); }} label="Try again" /></Notice>;
    if (!seller || (id && !original)) return <p role="status">Loading invoice…</p>;
    if (original && !original.editable) return <Notice tone="warning">This invoice is protected from editing. <Button onClick={() => navigate(`/invoices/${id}/review`)}>Open invoice</Button></Notice>;
    return <>
        <PageHeader title={id ? "Edit sales invoice" : "Create sales invoice"} description="Save a local draft with customer and item snapshots."
            actions={<Button variant="secondary" onClick={() => navigate("/invoices")}>Back to invoices</Button>} />
        
        {error && <Notice tone="warning">{error}</Notice>}
        {savedInvoice && <Modal title="Invoice Saved Successfully" onClose={() => { setSavedInvoice(undefined); navigate(`/invoices/${savedInvoice.id}/review`); }}
            footer={<div className="actions"><Button variant="secondary" onClick={() => { setSavedInvoice(undefined); navigate("/invoices"); }}>Back to Invoices</Button>
                <Button onClick={() => { const savedId = savedInvoice.id; setSavedInvoice(undefined); navigate(`/invoices/${savedId}/review`); }}>Review Saved Invoice</Button></div>}>
            <Notice tone="success">Invoice {savedInvoice.number} has been saved locally in the database. FBR has NOT been called yet.</Notice>
            <div className="section-gap">
                <p><strong>Local Reference / Invoice #:</strong> {savedInvoice.number}</p>
                <p><strong>Database ID:</strong> #{savedInvoice.id}</p>
            </div>
            <p>The invoice is safely stored locally. Review the saved details before proceeding to local approval and FBR validation.</p>
        </Modal>}
        {preview && <InvoiceConfirmDialog title="Review Before Submit" busy={busy}
            cancelLabel="Cancel" confirmLabel="Save to Local Database"
            onClose={() => { if (!busy) { setPreview(undefined); setError(""); } }} onConfirm={() => { void save(); }}>
            <Notice tone="info">No invoice has been saved yet. These totals are calculated locally and have not been sent to FBR. Confirming saves this invoice to the local database only; it does NOT contact FBR.</Notice>
            {error && <Notice tone="warning">{error}</Notice>}<InvoiceSummary invoice={preview} />
        </InvoiceConfirmDialog>}
        <form onSubmit={event => { event.preventDefault(); void showPreview(); }}>
            <fieldset className="form-fields" disabled={busy}>
                <Card title="Invoice details"><div className="form-grid form-grid--three">
                    <Input label="Local invoice number" readOnly value={original?.number || "Assigned on save"} />
                    <Input label="Invoice date" required type="date" value={draft.invoice_date} onChange={event => update("invoice_date", event.target.value)} />
                    <Input label="Invoice type" readOnly value="Sale Invoice" />
                    <Select label="Sandbox scenario" required={seller.fbr_environment === "sandbox"} disabled={seller.fbr_environment !== "sandbox"}
                        options={[{ value: "", label: seller.fbr_environment === "sandbox" ? "Select scenario" : "Not used in production" }, ...Object.entries(seller.supported_scenarios).filter(([code]) => seller.allowed_sandbox_scenarios.includes(code)).map(([value, item]) => ({ value, label: `${value} - ${item.description}` }))]}
                        value={draft.scenario_id} onChange={event => update("scenario_id", event.target.value)} />
                    <CustomerSelect companyId={company.id} value={String(draft.customer_id)}
                        selectedLabel={selectedBuyer?.name || original?.buyer_business_name || ""}
                        onSelect={customer => { update("customer_id", String(customer.id)); setSelectedBuyer(customer); }} />
                    <Input label="Due date (optional)" type="date" min={draft.invoice_date} value={draft.due_date} onChange={event => update("due_date", event.target.value)} />
                    <Input label="Reference invoice number (optional)" maxLength={100} value={draft.reference_invoice_no} onChange={event => update("reference_invoice_no", event.target.value)} />
                </div>
                    <div className="split-grid section-gap"><div><h3>Seller</h3><p className="reference-note">{sellerName} • {sellerIdentity || "Identity not configured"}<br />{sellerAddress}</p></div>
                        <div><h3>Buyer</h3><p className="reference-note">{savedBuyer ? savedBuyer.buyer_business_name : buyer?.name || "Select a customer"}<br />{savedBuyer ? savedBuyer.buyer_address : buyer?.address || ""}</p></div></div>
                </Card>
                <Card title="Invoice items" actions={<Button variant="secondary" disabled={draft.lines.length >= 100} onClick={() => setDraft(value => ({ ...value, lines: [...value.lines, blankLine()] }))}>Add item</Button>}>
                    <div className="invoice-lines">
                    {invoiceLines.map((line, index) => <InvoiceLineEditor key={line.client_key || line.id || index} line={line} index={index} companyId={company.id} invoiceDate={draft.invoice_date} canRemove={draft.lines.length > 1}
                        onRemove={() => setDraft(value => ({ ...value, lines: value.lines.filter((_, position) => position !== index) }))}
                        onChange={updated => updateLine(index, updated)} />)}
                    </div>
                    <InvoiceTotals lines={invoiceLines} />
                </Card>
                <Card title="Additional details"><Textarea label="Notes (optional)" maxLength={5000} value={draft.notes} onChange={event => update("notes", event.target.value)} />
                    <FormActions><Button variant="secondary" onClick={() => navigate("/invoices")}>Cancel</Button>
                        <Button type="submit">{busy ? "Checking…" : "Review Before Submit"}</Button></FormActions>
                </Card>
            </fieldset>
        </form>
    </>;
}
