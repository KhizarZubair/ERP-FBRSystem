import { SearchableSelect } from "../../components/SearchableSelect";
import { Button, Input } from "../../components/ui";
import type { ItemRecord } from "../items/itemApi";
import { blankLine, invoiceBase, money, inputDecimal } from "./invoiceApi";
import type { InvoiceLine } from "./invoiceApi";

export function InvoiceLineEditor({ line, index, companyId, invoiceDate, onChange, onRemove, canRemove }: {
    line: InvoiceLine; index: number; companyId: number; invoiceDate: string; onChange: (line: InvoiceLine) => void;
    onRemove: () => void; canRemove: boolean;
}) {
    const update = (key: keyof InvoiceLine, value: string) => onChange({ ...line, [key]: value, ...(key === "tax_rate_percent" ? { tax_rate_label: value + "%" } : {}) });
    const net = Math.max(0, Number(line.quantity) * Number(line.unit_price) - Number(line.discount));
    const total = net + net * Number(line.tax_rate_percent) / 100 + Number(line.extra_tax) + Number(line.further_tax) + Number(line.fed_payable) - Number(line.sales_tax_withheld_at_source);
    return <section className="invoice-line-row" aria-label={`Invoice item ${index + 1}`}>
        <div className="invoice-line-main">
            <div className="invoice-line-product"><SearchableSelect<ItemRecord> label={`Item ${index + 1}`} value={String(line.item_id)}
                selectedLabel={line.sku ? `${line.sku} — ${line.product_description || "Saved item"}` : ""}
                path={invoiceBase(companyId) + "/lookups/items?" + new URLSearchParams({ price_date: invoiceDate, price_list: "Standard" })}
                describe={item => `${item.sku} · HS ${item.hs_code} · ${item.unit_of_measurement}`}
                onSelect={item => onChange({ ...blankLine(), client_key: line.client_key, item_id: item.id,
                    unit_price: inputDecimal(item.current_unit_price ?? "0"), tax_rate_percent: inputDecimal(item.tax_rate_percent ?? "0"),
                    fixed_notified_value_or_retail_price: item.fixed_notified_value_or_retail_price,
                    tax_rate_label: item.tax_rate_label, extra_tax: item.extra_tax || "0", fed_payable: item.fed_payable || "0", sku: item.sku, product_description: item.description, hs_code: item.hs_code, unit_of_measurement: item.unit_of_measurement,
                    sale_type: item.sale_type, sro_schedule_no: item.sro_schedule_no || "", sro_item_serial_no: item.sro_item_serial_no || "" })} />
                {line.item_id && <p className="field-hint">HS {line.hs_code} · {line.unit_of_measurement} · {line.sale_type}</p>}
            </div>
            <Input label="Quantity" required type="number" min="0.000001" max="999999999" step="any" value={line.quantity} onChange={event => update("quantity", event.target.value)} />
            <Input label="Unit price" required type="number" min="0" max="999999999" step="any" value={line.unit_price} onChange={event => update("unit_price", event.target.value)} />
            <Input label="Tax (%)" required type="number" min="0" max="100" step="any" value={line.tax_rate_percent} onChange={event => update("tax_rate_percent", event.target.value)} />
            <div className="invoice-line-total"><span>Line total</span><strong>{money(total)}</strong></div>
            <Button variant="danger" disabled={!canRemove} onClick={onRemove} aria-label={`Remove item ${index + 1}`}>×</Button>
        </div>
        <div className="form-grid form-grid--three section-gap">
            {([["discount", "Discount"], ["sales_tax_withheld_at_source", "Sales tax withheld"], ["extra_tax", "Extra tax"], ["further_tax", "Further tax"], ["fed_payable", "FED payable"], ["fixed_notified_value_or_retail_price", "Notified / retail value"]] as const).map(([key, label]) =>
                <Input key={key} label={label} type="number" min="0" max="999999999" step="any" value={line[key]} onChange={event => update(key, event.target.value)} />)}
            <Input label="SRO schedule number" maxLength={100} value={line.sro_schedule_no || ""} onChange={event => update("sro_schedule_no", event.target.value)} />
            <Input label="SRO item serial number" maxLength={100} value={line.sro_item_serial_no || ""} onChange={event => update("sro_item_serial_no", event.target.value)} />
        </div>
    </section>;
}
