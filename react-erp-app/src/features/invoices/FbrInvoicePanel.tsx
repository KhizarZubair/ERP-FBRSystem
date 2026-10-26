import { useEffect, useRef, useState } from "react";
import { Button, Card, Input, Notice, Select, Textarea } from "../../components/ui";
import { api, ApiError } from "../../lib/api";
import { Modal } from "../../components/Modal";
import { InvoiceConfirmDialog } from "./InvoiceConfirmDialog";
import { InvoiceSummary } from "./InvoiceSummary";
import { invoiceBase, statusLabel } from "./invoiceApi";
import type { InvoiceRecord } from "./invoiceApi";
type Attempt = { id: number; environment: string; operation: string; status: string; http_status: number | null; reconciliation_notes?: string | null; error_code:string|null; error: string | null; guidance:string|null; fbr_invoice_number: string | null; lines: { line_number: number; status: string; error_code: string | null; error: string | null; guidance:string|null }[] };
type Details = { environments: string[]; readiness:{ready:boolean;blockers:string[];scenarios:Record<string,{description:string}>}; history: Attempt[] };
export function FbrInvoicePanel({ companyId, invoice, verified, onUpdated }: { companyId: number; invoice: InvoiceRecord; verified: boolean; onUpdated: () => void }) {
    const [details, setDetails] = useState<Details>();
    const [environment, setEnvironment] = useState("");
    const [scenario, setScenario] = useState(invoice.scenario_id || "");
    const [error, setError] = useState("");
    const [result, setResult] = useState<Attempt>();
    const [busy, setBusy] = useState(false);
    const [reconcile, setReconcile] = useState<Attempt>();
    const [outcome, setOutcome] = useState("not_accepted");
    const [reference, setReference] = useState("");
    const [notes, setNotes] = useState("");
    const [checked, setChecked] = useState(false);
    const [confirm, setConfirm] = useState(false);
    const [validated, setValidated] = useState("");
    const lock = useRef(false);
    const base = invoiceBase(companyId) + "/" + invoice.id + "/fbr";
    const key = invoice.version + ":" + environment + ":" + scenario;
    useEffect(() => {
        let active = true;
        api<Details>(base).then(data => { if (active) { setDetails(data); if (data.environments.length === 1) setEnvironment(data.environments[0]); } }).catch(failure => { if (active) setError(failure instanceof Error ? failure.message : "Could not load FBR settings."); });
        return () => { active = false; };
    }, [base, invoice.version]);
    async function run(operation: "validate" | "submit") {
        if (lock.current) return;
        lock.current = true; setBusy(true); setError("");
        if (operation === "validate") setValidated("");
        try {
            const response = await api<{ data: Attempt }>(base + "/" + operation, { method: "POST", body: JSON.stringify({ environment, scenario_id: scenario || null, version: invoice.version, request_id: crypto.randomUUID(), confirmed: true }) });
            setResult(response.data);
            if (operation === "validate" && response.data.status === "accepted") setValidated(key);
            if (operation === "submit") { setValidated(""); setConfirm(false); onUpdated(); }
            setDetails(await api<Details>(base));
        } catch (failure) {
            setError(failure instanceof ApiError ? Object.values(failure.errors).flat().join(" ") || failure.message : failure instanceof Error ? failure.message : "FBR request failed.");
            if (operation === "submit") { setValidated(""); setConfirm(false); onUpdated(); }
        } finally { lock.current = false; setBusy(false); }
    }
    async function resolveUnknown() {
        if (!reconcile || !checked || lock.current) return;
        lock.current = true; setBusy(true); setError("");
        try {
            await api(base + "/reconcile", { method: "POST", body: JSON.stringify({ submission_id: reconcile.id, outcome, fbr_invoice_number: reference || null, notes, confirmed: true }) });
            setReconcile(undefined); setValidated(""); onUpdated();
        } catch (failure) { setError(failure instanceof Error ? failure.message : "Could not save verified outcome."); }
        finally { lock.current = false; setBusy(false); }
    }
    const blocked = ["pending", "unknown"].includes(invoice.fbr_status) || invoice.status === "issued";
    return <Card title="FBR validation & submission">
        {error && <Notice tone="warning">{error}</Notice>}
        {!details ? <p role="status">Loading FBR credentials…</p> : <>
            {!details.environments.length ? <Notice>Add a Sandbox or Production token in Company Details to connect to FBR.</Notice> : <>
                <p>Destination: {statusLabel(environment)} — selected in Company Details</p>
                {!details.readiness.ready && <Notice tone="warning">{details.readiness.blockers.join(" ")}</Notice>}
                {environment === "sandbox" && <Select label="Saved sandbox scenario" required options={[{value:"",label:"No scenario saved"},...Object.entries(details.readiness.scenarios).map(([value,item])=>({value,label:`${value} - ${item.description}`}))]} disabled value={scenario} onChange={event => { setScenario(event.target.value); setValidated(""); }} />}
                {environment === "production" && <Notice tone="warning">Production submits a real fiscal invoice using the saved Production token.</Notice>}
                {!blocked && <div className="actions section-gap"><Button variant="secondary" disabled={busy || !details.readiness.ready || !environment || (environment === "sandbox" && !scenario)} onClick={() => { void run("validate"); }}>{busy ? "Contacting FBR…" : "Validate with FBR"}</Button>
                    <Button disabled={busy || !verified || validated !== key} onClick={() => setConfirm(true)}>Submit to FBR</Button></div>}
                {blocked && <Notice tone="warning">{invoice.fbr_status === "unknown" ? "Submission result unknown. Do not resend. Confirm the result with PRAL using the saved attempt history." : "This invoice is already submitted or a submission is in progress."}</Notice>}
            </>}
            {result && <Notice tone={result.status === "accepted" ? "success" : "warning"}>{result.operation === "validate" && result.status === "accepted" ? "FBR validation passed. Complete final verification to submit." : statusLabel(result.status)}{result.http_status ? " — HTTP " + result.http_status : ""}{result.error_code?` — ${result.error_code}`:""}{result.error ? ": " + result.error : ""}{result.guidance?" "+result.guidance:""}{result.fbr_invoice_number ? " — FBR reference: " + result.fbr_invoice_number : ""}</Notice>}
            {result?.lines.filter(line => line.error).map(line => <p className="field-error" key={line.line_number}>Item {line.line_number}: {line.error_code} {line.error} {line.guidance}</p>)}
            {!!details.history.length && <div className="section-gap"><h3>Submission history</h3>{details.history.map(attempt => <div key={attempt.id}>{attempt.operation === "submit" && ["unknown", "processing"].includes(attempt.status) && <Button variant="secondary" onClick={() => { setChecked(false); setNotes(""); setReference(""); setReconcile(attempt); }}>Record PRAL-confirmed outcome</Button>}{attempt.reconciliation_notes && <p>{attempt.reconciliation_notes}</p>}<p>#{attempt.id} · {statusLabel(attempt.environment)} · {statusLabel(attempt.operation)} · {statusLabel(attempt.status)}{attempt.http_status ? " · HTTP " + attempt.http_status : ""}{attempt.fbr_invoice_number ? " · " + attempt.fbr_invoice_number : ""}</p>{attempt.error && <p className="field-error">{attempt.error_code} {attempt.error} {attempt.guidance}</p>}{attempt.lines.filter(line => line.error).map(line => <p className="field-error" key={line.line_number}>Item {line.line_number}: {line.error_code} {line.error} {line.guidance}</p>)}</div>)}</div>}
        </>}
        {reconcile && <Modal title="Reconcile uncertain submission" busy={busy} onClose={() => setReconcile(undefined)} footer={<><Button variant="secondary" disabled={busy} onClick={() => setReconcile(undefined)}>Cancel</Button><Button disabled={busy || !checked || notes.trim().length < 20 || (outcome === "accepted" && !reference.trim())} onClick={() => { void resolveUnknown(); }}>Save verified outcome</Button></>}>
            <Notice tone="warning">Use only after checking the FBR portal or receiving PRAL confirmation. Do not guess the result.</Notice>
            <Select label="Confirmed outcome" value={outcome} disabled={busy} onChange={event => setOutcome(event.target.value)} options={[{ value: "not_accepted", label: "PRAL confirmed not accepted — allow a fresh validation/retry" }, { value: "accepted", label: "Accepted — store confirmed FBR invoice number" }]} />
            {outcome === "accepted" && <Input label="Confirmed FBR invoice number" required maxLength={100} value={reference} disabled={busy} onChange={event => setReference(event.target.value)} />}
            <Textarea label="PRAL confirmation / portal verification details" required minLength={20} maxLength={4000} value={notes} disabled={busy} onChange={event => setNotes(event.target.value)} />
            <label className="invoice-confirmation"><input type="checkbox" checked={checked} disabled={busy} onChange={event => setChecked(event.target.checked)} />I verified this outcome with PRAL or the FBR portal.</label>
        </Modal>}
        {confirm && <InvoiceConfirmDialog title={"Confirm Submit to FBR — " + (environment === "production" ? "Production" : "Sandbox")} busy={busy} cancelLabel="No, Cancel" confirmLabel="Yes, Submit to FBR" onClose={() => setConfirm(false)} onConfirm={() => { void run("submit"); }}><Notice tone={environment === "production" ? "warning" : "info"}>Invoice {invoice.number} will be sent to {environment === "production" ? "FBR Production as a real fiscal invoice" : "FBR Sandbox for testing"}. If the response is uncertain, do not resend; reconcile the attempt with PRAL.</Notice>
            <p>Buyer: {invoice.buyer_business_name} • Total: {new Intl.NumberFormat("en-PK", { style: "currency", currency: invoice.currency || "PKR" }).format(Number(invoice.total_values))}</p>
            <InvoiceSummary invoice={invoice} /></InvoiceConfirmDialog>}
    </Card>;
}
