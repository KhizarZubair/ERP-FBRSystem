import { FbrReferenceSelect } from "./FbrReferenceSelect";
import { useRef, useState } from "react";
import type { ChangeEvent } from "react";
import { Button, Card, FormActions, Input, Notice, Select, Textarea } from "../../components/ui";
import { ApiError } from "../../lib/api";
import type { FieldErrors } from "../../lib/api";
import { itemApi, itemDraft } from "./itemApi";
import type { ItemDraft, ItemRecord } from "./itemApi";
import { HsCodeSelect } from "./HsCodeSelect";

export function ItemForm({ companyId, item, onSaved, onCancel }: {
    companyId: number; item?: ItemRecord; onSaved: (item: ItemRecord) => void; onCancel: () => void;
}) {
    const [draft, setDraft] = useState(() => ({ ...itemDraft(item), ...(!item ? { tax_rate_percent: "18", tax_rate_label: "18%", sku: "", unit_of_measurement: "", sale_type: "" } : {}) }));
    const [unitPrice, setUnitPrice] = useState(item?.current_unit_price || "");
    const [errors, setErrors] = useState<FieldErrors>({});
    const [message, setMessage] = useState("");
    const [busy, setBusy] = useState(false);
    const lock = useRef(false);
    function change(key: keyof ItemDraft, value: string | boolean) {
        setDraft(current => ({ ...current, [key]: value })); setErrors(current => ({ ...current, [key]: [] }));
    }
    const field = (key: Exclude<keyof ItemDraft, "track_stock" | "is_active">) => ({
        name: key, value: draft[key] || "", error: errors[key]?.[0],
        onChange: (event: ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => change(key, event.target.value),
    });
    const isExemptOrZero = draft.tax_rate_label === "Exempt" || draft.tax_rate_label === "0%" || draft.tax_rate_percent === "0";

    return <Card title={item ? "Edit Product" : "Create Product"}><form onSubmit={async event => {
        event.preventDefault(); if (lock.current) return;
        lock.current = true; setBusy(true); setMessage(""); setErrors({});
        try {
            if (!unitPrice.trim() || !Number.isFinite(Number(unitPrice)) || Number(unitPrice) < 0.01) {
                setErrors({ unit_price: ["Unit price must be a number greater than or equal to 0.01."] });
                return;
            }
            const today = new Date();
            const effectiveFrom = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, "0")}-${String(today.getDate()).padStart(2, "0")}`;
            const price = { price_list: "Standard" as const, unit_price: unitPrice, currency: "PKR" as const, effective_from: effectiveFrom, effective_to: "", is_active: true };
            const payload: ItemDraft = {
                ...draft,
                fixed_notified_value_or_retail_price: draft.fixed_notified_value_or_retail_price?.trim() || "0",
                extra_tax: draft.extra_tax?.trim() || "0",
                fed_payable: draft.fed_payable?.trim() || "0",
                sro_schedule_no: draft.sro_schedule_no?.trim() || null,
                sro_item_serial_no: draft.sro_item_serial_no?.trim() || null,
            };
            const response = await itemApi.save(companyId, payload, item?.id, price);
            onSaved(response.data);
        }
        catch (error) { setMessage(error instanceof Error ? error.message : "Could not save item."); if (error instanceof ApiError) setErrors(error.errors); }
        finally { lock.current = false; setBusy(false); }
    }}>
        {message && <Notice tone="warning">{message}</Notice>}
        <fieldset className="form-fields" disabled={busy}>
            <div className="form-grid">
                <Input label="Product Name" required maxLength={255} placeholder="e.g., Laptop" {...field("name")} />
                <Input label="Product Code" required maxLength={80} pattern={"[A-Za-z0-9_\\-]+"} placeholder="e.g., LAPTOP-001" {...field("sku")} />
                <Textarea label="Product Description" required maxLength={4000} placeholder="Product specifications or details" {...field("description")} />
                <HsCodeSelect companyId={companyId} value={draft.hs_code} onChange={code => { change("hs_code", code); if (code && code !== draft.hs_code) change("unit_of_measurement", ""); }} error={errors.hs_code?.[0]} />
                <FbrReferenceSelect label="Unit of measurement" path={`/api/v1/companies/${companyId}/fbr/units?limit=100${draft.hs_code ? "&hs_code=" + encodeURIComponent(draft.hs_code) : ""}`} value={draft.unit_of_measurement} onChange={value => change("unit_of_measurement", value)} error={errors.unit_of_measurement?.[0]} autoSelectSingle />
                <FbrReferenceSelect label="Sale type" path={`/api/v1/companies/${companyId}/fbr/sale-types?limit=100`} value={draft.sale_type} onChange={value => change("sale_type", value)} error={errors.sale_type?.[0]} autoSelectStandardRate />
                <Select label="Sales Tax Rate" required options={["18%", "0%", "Exempt", "1%", "5%", "10%", "12%", "15%"]} value={draft.tax_rate_label} onChange={event => { change("tax_rate_label", event.target.value); change("tax_rate_percent", event.target.value === "Exempt" ? "0" : event.target.value.replace("%", "")); }} />
                <Input label="Unit Price (Excl. Tax)" type="number" min="0.01" step="0.01" required placeholder="e.g. 500.00" value={unitPrice} error={errors.unit_price?.[0]} onChange={event => { setUnitPrice(event.target.value); setErrors(current => ({ ...current, unit_price: [] })); }} />

                <div style={{ gridColumn: "1 / -1", marginTop: "1rem", paddingTop: "1rem", borderTop: "1px solid var(--border-color, #e2e8f0)" }}>
                    <h3 style={{ margin: "0 0 0.25rem 0", fontSize: "1.05rem", fontWeight: 600 }}>FBR Sector & Fiscal Classification</h3>
                    <p style={{ margin: 0, fontSize: "0.85rem", color: "var(--text-muted, #64748b)" }}>
                        Configure Printed Retail Price for FMCG / 3rd Schedule goods, or SRO details for Exempt or Concessionary items.
                    </p>
                </div>
                {isExemptOrZero && (
                    <div style={{ gridColumn: "1 / -1" }}>
                        <Notice tone="info">
                            <strong>SRO Details Required:</strong> FBR mandates SRO Schedule Number & Item Serial Number for Exempt / 0% goods under Section 10. Leaving them blank causes FBR Error 0077 / 0079.
                        </Notice>
                    </div>
                )}
                <Input
                    label="Printed Retail Price / Fixed Notified Value"
                    type="number"
                    min="0"
                    step="any"
                    placeholder="0.00"
                    hint="Mandatory for 3rd Schedule (FMCG). FBR calculates tax on Retail Price × Quantity (Sec 3(2)(a))."
                    {...field("fixed_notified_value_or_retail_price")}
                />
                <Input
                    label="SRO Schedule Number"
                    maxLength={100}
                    placeholder="e.g. Sixth Schedule, Table-1"
                    hint="Official FBR SRO Schedule Number (for Exempt/Reduced rate items)"
                    {...field("sro_schedule_no")}
                />
                <Input
                    label="SRO Item Serial Number"
                    maxLength={100}
                    placeholder="e.g. Item 12(i)"
                    hint="Item serial number within the SRO Schedule"
                    {...field("sro_item_serial_no")}
                />
                <Input
                    label="Federal Excise Duty (FED Payable)"
                    type="number"
                    min="0"
                    step="any"
                    placeholder="0.00"
                    hint="Applicable for FED in Sales Tax mode (Beverages, Tobacco, etc.)"
                    {...field("fed_payable")}
                />
                <Input
                    label="Extra Tax"
                    type="number"
                    min="0"
                    step="any"
                    placeholder="0.00"
                    hint="Special extra tax percentage or fixed value where applicable"
                    {...field("extra_tax")}
                />
            </div>
            <FormActions><Button variant="secondary" onClick={onCancel}>Cancel</Button><Button type="submit">{busy ? "Saving…" : item ? "Update Product" : "Save Product"}</Button></FormActions>
        </fieldset>
    </form></Card>;
}
