import { Modal } from "../../components/Modal";
import { useRef, useState } from "react";
import { Badge, Button, Card, Notice, PageHeader , RefreshButton } from "../../components/ui";
import { useAuth } from "../auth/AuthContext";
import { navigate } from "../../lib/navigation";
import { failureMessage, useApiQuery } from "../../lib/useApiQuery";
import { invoiceApi, invoiceBase, statusLabel } from "./invoiceApi";
import type { InvoiceRecord } from "./invoiceApi";
import { InvoiceSummary } from "./InvoiceSummary";
import { FbrInvoicePanel } from "./FbrInvoicePanel";
import { InvoiceConfirmDialog } from "./InvoiceConfirmDialog";
import { FbrInvoicePrint } from "./FbrInvoicePrint";

export function InvoiceReview({ id }: { id: string }) {
    const { activeCompany } = useAuth(); const company = activeCompany!; const owner = company.role === "owner";
    const query = useApiQuery<{ data: InvoiceRecord }>(invoiceBase(company.id) + "/" + id);
    const [confirmDelete, setConfirmDelete] = useState(false); const [busy, setBusy] = useState(false);
    const [error, setError] = useState(""); const lock = useRef(false);
    const [approvalVersion, setApprovalVersion] = useState(""); const [verifiedVersion, setVerifiedVersion] = useState("");
    const [modal, setModal] = useState<3 | null>(null);
    const invoice = query.data?.data;
    const approved = invoice?.status === "approved";
    const verified = Boolean(invoice && verifiedVersion === invoice.version);
    async function approve() {
        if (!invoice || lock.current || approvalVersion !== invoice.version) return;
        lock.current = true; setBusy(true); setError(""); setVerifiedVersion("");
        try {
            await invoiceApi.approve(company.id, invoice.id, invoice.version);
            setApprovalVersion(""); query.reload(); setModal(3);
        } catch (failure) { setError(failureMessage(failure)); }
        finally { lock.current = false; setBusy(false); }
    }
    async function remove() {
        if (!invoice || lock.current) return; lock.current = true; setBusy(true); setError("");
        try { await invoiceApi.delete(company.id, invoice.id, invoice.version); navigate("/invoices"); }
        catch (failure) { setError(failureMessage(failure)); } finally { lock.current = false; setBusy(false); }
    }
    async function reopen() {
        if (!invoice || lock.current) return; lock.current = true; setBusy(true); setError("");
        try { await invoiceApi.reopen(company.id, invoice.id, invoice.version); query.reload(); }
        catch (failure) { setError(failureMessage(failure)); } finally { lock.current = false; setBusy(false); }
    }
    if (query.error) return <Notice tone="warning">{query.error} <RefreshButton variant="quiet" onClick={query.reload} label="Try again" /></Notice>;
    if (!invoice) return <p role="status">Loading invoice…</p>;
    return <>
        <PageHeader title={invoice.number} description="Review, approve, validate and confirm before FBR submission."
            actions={<Button variant="secondary" onClick={() => navigate("/invoices")}>Back to invoices</Button>} />
        
        {error && <Notice tone="warning">{error} <RefreshButton variant="quiet" onClick={() => { setApprovalVersion(""); setVerifiedVersion(""); setModal(null); query.reload(); }} label="Reload invoice" /></Notice>}
        <div className="actions section-gap"><Badge>{statusLabel(invoice.status)}</Badge><Badge>{statusLabel(invoice.fbr_status)}</Badge></div>
        {invoice.approved_at && <p className="reference-note">Locally approved: {new Date(invoice.approved_at).toLocaleString()}</p>}
        {invoice.fbr_invoice_number && <p>FBR reference: {invoice.fbr_invoice_number}</p>}
        <InvoiceSummary invoice={invoice} />
        <FbrInvoicePrint invoice={invoice} />
        {owner && invoice.editable && <Card title="Verification 2 of 4 — Review saved invoice">
            <p>Check the saved seller, buyer, items and totals. Approval locks this local invoice against editing/deletion.</p>
            <label className="invoice-confirmation"><input type="checkbox" checked={approvalVersion === invoice.version} disabled={busy}
                onChange={event => setApprovalVersion(event.target.checked ? invoice.version : "")} />I confirm the saved invoice is correct and approve it.</label>
            <div className="actions"><Button disabled={busy || approvalVersion !== invoice.version} onClick={() => { void approve(); }}>Approve invoice</Button>
                <Button variant="secondary" disabled={busy} onClick={() => navigate(`/invoices/${invoice.id}/edit`)}>Edit draft</Button>
                <Button variant="danger" disabled={busy} onClick={() => setConfirmDelete(true)}>Delete draft</Button></div>
            {confirmDelete && <Modal title="Delete draft invoice?" busy={busy} onClose={() => setConfirmDelete(false)}><p>Delete {invoice.number}? This permanently removes this unsubmitted draft.</p><div className="actions">
                <Button variant="secondary" disabled={busy} onClick={() => setConfirmDelete(false)}>Cancel</Button>
                <Button variant="danger" disabled={busy} onClick={() => { void remove(); }}>Confirm delete</Button></div></Modal>}
        </Card>}
        {owner && approved && <Card title="Approved invoice verification">
            {invoice.fbr_status === "validation_failed" && <><Notice tone="warning">FBR validation rejected this invoice. Reopen it to correct the saved fields; previous attempts remain in history.</Notice><Button variant="secondary" disabled={busy} onClick={() => { void reopen(); }}>Reopen for correction</Button></>}
            <Button variant="secondary" disabled={busy} onClick={() => setModal(3)}>Verification 3 — Are you sure?</Button>
            {verified && <p>Verification 3 confirmed for this saved invoice version.</p>}
        </Card>}
        {owner && !invoice.editable && <FbrInvoicePanel companyId={company.id} invoice={invoice} verified={verified} onUpdated={() => { setVerifiedVersion(""); query.reload(); }} />}
        {modal === 3 && approved && <InvoiceConfirmDialog title="Verification 3 of 4 — Are you sure?" confirmationLabel="I am sure the approved invoice details are correct."
            confirmLabel="Confirm verification 3" onClose={() => setModal(null)} onConfirm={() => { setVerifiedVersion(invoice.version); setModal(null); }}>
            <p>Verify the approved invoice again before enabling the final upload confirmation.</p><InvoiceSummary invoice={invoice} />
        </InvoiceConfirmDialog>}
    </>;
}
