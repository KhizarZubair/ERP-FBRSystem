import { useId, useState } from "react";
import type { ReactNode } from "react";
import { Button, FormActions } from "../../components/ui";
import { Modal } from "../../components/Modal";
export function InvoiceConfirmDialog({ title, children, confirmationLabel, confirmLabel, cancelLabel = "Cancel", busy = false, onConfirm, onClose }: {
    title: string; children: ReactNode; confirmationLabel?: string; confirmLabel: string; cancelLabel?: string; busy?: boolean;
    onConfirm: () => void; onClose: () => void;
}) {
    const [agreed, setAgreed] = useState(false);
    const formId = useId();
    const canConfirm = (!confirmationLabel || agreed) && !busy;
    return <Modal {...{title, busy, onClose}} wide footer={<form id={formId} onSubmit={event => { event.preventDefault(); if (canConfirm) onConfirm(); }}>
        {confirmationLabel && <label className="invoice-confirmation"><input type="checkbox" required checked={agreed} disabled={busy} onChange={event => setAgreed(event.target.checked)} />{confirmationLabel}</label>}
        <FormActions><Button variant="secondary" disabled={busy} onClick={onClose}>{cancelLabel}</Button>
            <Button type="submit" disabled={!canConfirm}>{busy ? "Processing…" : confirmLabel}</Button></FormActions>
    </form>}>{children}</Modal>;
}
