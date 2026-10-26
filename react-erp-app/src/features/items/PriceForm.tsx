import { useRef, useState } from "react";
import type { ChangeEvent } from "react";
import { Button, FormActions, Input, Notice } from "../../components/ui";
import { ApiError } from "../../lib/api";
import type { FieldErrors } from "../../lib/api";
import { itemApi, priceDraft } from "./itemApi";
import type { PriceDraft, PriceRecord } from "./itemApi";

export function PriceFields({ draft, change, errors, prefix = "" }: {
    draft: PriceDraft; change: (key: keyof PriceDraft, value: string | boolean) => void; errors: FieldErrors; prefix?: string;
}) {
    const field = (key: Exclude<keyof PriceDraft, "is_active">) => ({
        name: prefix + key, value: draft[key] || "", error: errors[prefix + key]?.[0],
        onChange: (event: ChangeEvent<HTMLInputElement | HTMLSelectElement>) => change(key, event.target.value),
    });
    return <div className="form-grid">
        <Input label="Selling price per unit (PKR, before tax)" type="number" min="0" max="99999999999999" step="any" required {...field("unit_price")} />
    </div>;

}
export function PriceForm({ companyId, itemId, price, onSaved, onCancel }: {
    companyId: number; itemId: number; price?: PriceRecord; onSaved: () => void; onCancel: () => void;
}) {
    const [draft, setDraft] = useState(() => priceDraft(price));
    const [errors, setErrors] = useState<FieldErrors>({});
    const [message, setMessage] = useState("");
    const [busy, setBusy] = useState(false);
    const lock = useRef(false);
    function change(key: keyof PriceDraft, value: string | boolean) {
        setDraft(current => ({ ...current, [key]: value })); setErrors(current => ({ ...current, [key]: [] }));
    }
    return <form onSubmit={async event => {
        event.preventDefault(); if (lock.current) return;
        lock.current = true; setBusy(true); setMessage(""); setErrors({});
        try { await itemApi.savePrice(companyId, itemId, draft, price?.id); onSaved(); }
        catch (error) { setMessage(error instanceof Error ? error.message : "Could not save price."); if (error instanceof ApiError) setErrors(error.errors); }
        finally { lock.current = false; setBusy(false); }
    }}>
        {message && <Notice tone="warning">{message}</Notice>}
        <fieldset className="form-fields" disabled={busy}><PriceFields {...{ draft, change, errors }} />
            <FormActions><Button variant="secondary" onClick={onCancel}>Cancel price</Button><Button type="submit">{busy ? "Saving…" : "Save price"}</Button></FormActions>
        </fieldset>
    </form>;
}
