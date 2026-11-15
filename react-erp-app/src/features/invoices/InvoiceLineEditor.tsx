import { SearchableSelect } from "../../components/SearchableSelect";
import { Button, Input, Badge } from "../../components/ui";
import type { ItemRecord } from "../items/itemApi";
import { blankLine, invoiceBase, money, inputDecimal } from "./invoiceApi";
import type { InvoiceLine } from "./invoiceApi";

export function InvoiceLineEditor({ line, index, companyId, invoiceDate, scenarioId, onChange, onRemove, canRemove }: {
    line: InvoiceLine; index: number; companyId: number; invoiceDate: string; scenarioId?: string; onChange: (line: InvoiceLine) => void;
    onRemove: () => void; canRemove: boolean;
}) {
    const update = (key: keyof InvoiceLine, value: string) => onChange({ ...line, [key]: value, ...(key === "tax_rate_percent" ? { tax_rate_label: value + "%" } : {}) });
    const net = Math.max(0, Number(line.quantity) * Number(line.unit_price) - Number(line.discount));
    const retailPrice = Number(line.fixed_notified_value_or_retail_price) || 0;
    
    // Scenario classification
    const scenario = (scenarioId || "").toUpperCase().trim();
    const isSN001 = scenario === "SN001";
    const isSN002 = scenario === "SN002";
    const isSN008or027 = scenario === "SN008" || scenario === "SN027";
    const isSN017or018 = scenario === "SN017" || scenario === "SN018";
    const isSN024 = scenario === "SN024";
    const isSroRequired = isSN024 || scenario === "SN005" || scenario === "SN006" || scenario === "SN007" || scenario === "SN010";

    const isThirdSchedule = isSN008or027 || (
        retailPrice > 0 && (
            (line.sale_type && (line.sale_type.toLowerCase().includes("3rd schedule") || line.sale_type.toLowerCase().includes("third schedule"))) ||
            retailPrice > 0
        )
    );
    const taxBase = isThirdSchedule && retailPrice > 0 ? (Number(line.quantity) * retailPrice) : net;
    const tax = taxBase * Number(line.tax_rate_percent) / 100;
    
    // Proviso to Sec 3(1A): No further tax on 3rd schedule or standard SN001
    const furtherTax = (isThirdSchedule || isSN001) ? 0 : Number(line.further_tax || 0);
    const total = net + tax + Number(line.extra_tax || 0) + furtherTax + Number(line.fed_payable || 0) - Number(line.sales_tax_withheld_at_source || 0);

    return <section className="invoice-line-row" aria-label={`Invoice item ${index + 1}`} style={{ borderBottom: "1px solid var(--border-color, #e2e8f0)", paddingBottom: "1rem", marginBottom: "1rem" }}>
        <div className="invoice-line-main">
            <div className="invoice-line-product">
                <SearchableSelect<ItemRecord> 
                    label={`Item ${index + 1}`} 
                    value={String(line.item_id || "")}
                    selectedLabel={line.sku ? `${line.sku} — ${line.product_description || "Saved item"}` : ""}
                    path={invoiceBase(companyId) + "/lookups/items?" + new URLSearchParams({ price_date: invoiceDate, price_list: "Standard" })}
                    describe={item => `${item.sku} · HS ${item.hs_code} · ${item.unit_of_measurement}`}
                    onSelect={item => {
                        const qty = line.quantity && Number(line.quantity) > 0 ? line.quantity : "1";
                        const netVal = Math.max(0, Number(qty) * Number(item.current_unit_price ?? 0));
                        const autoFurtherTax = isSN002 ? (netVal * 0.03).toFixed(2) : "0";
                        onChange({
                            ...blankLine(),
                            client_key: line.client_key,
                            item_id: item.id,
                            quantity: qty,
                            unit_price: inputDecimal(item.current_unit_price ?? "0"),
                            tax_rate_percent: inputDecimal(item.tax_rate_percent ?? "18"),
                            tax_rate_label: item.tax_rate_label || `${item.tax_rate_percent ?? 18}%`,
                            fixed_notified_value_or_retail_price: item.fixed_notified_value_or_retail_price || "0",
                            extra_tax: isSN001 ? "0" : (item.extra_tax || "0"),
                            further_tax: isSN001 ? "0" : autoFurtherTax,
                            fed_payable: isSN001 ? "0" : (item.fed_payable || "0"),
                            sku: item.sku,
                            product_description: item.description,
                            hs_code: item.hs_code,
                            unit_of_measurement: item.unit_of_measurement,
                            sale_type: item.sale_type,
                            sro_schedule_no: item.sro_schedule_no || "",
                            sro_item_serial_no: item.sro_item_serial_no || "",
                        });
                    }} 
                />
                {line.item_id && (
                    <div style={{ display: "flex", gap: "6px", flexWrap: "wrap", marginTop: "4px" }}>
                        <Badge tone="neutral">HS: {line.hs_code || "N/A"}</Badge>
                        <Badge tone="neutral">UOM: {line.unit_of_measurement || "N/A"}</Badge>
                        <Badge tone="neutral">Sale Type: {line.sale_type || "Standard"}</Badge>
                    </div>
                )}
            </div>
            <Input label="Quantity" required type="number" min="0.000001" max="999999999" step="any" value={line.quantity} onChange={event => update("quantity", event.target.value)} />
            <Input label="Unit price (Excl. Tax)" required type="number" min="0" max="999999999" step="any" value={line.unit_price} onChange={event => update("unit_price", event.target.value)} />
            <Input label="Tax (%)" required type="number" min="0" max="100" step="any" value={line.tax_rate_percent} onChange={event => update("tax_rate_percent", event.target.value)} />
            <div className="invoice-line-total"><span>Line total</span><strong>{money(total)}</strong></div>
            <Button variant="danger" disabled={!canRemove} onClick={onRemove} aria-label={`Remove item ${index + 1}`}>×</Button>
        </div>

        {/* Dynamic Fields Grid based on Scenario Rules */}
        <div className="form-grid form-grid--three section-gap">
            <Input label="Discount" type="number" min="0" max="999999999" step="any" value={line.discount} onChange={event => update("discount", event.target.value)} />
            <Input label="Sales tax withheld" type="number" min="0" max="999999999" step="any" value={line.sales_tax_withheld_at_source} onChange={event => update("sales_tax_withheld_at_source", event.target.value)} />
            
            {/* Further Tax: Mandatory for SN002, Hidden for SN001 & SN008 */}
            {isSN002 && (
                <div>
                    <Input 
                        label="Further Tax (3% Unregistered) *" 
                        type="number" 
                        min="0" 
                        step="any" 
                        value={line.further_tax} 
                        onChange={event => update("further_tax", event.target.value)} 
                    />
                    <small style={{ color: "#0284c7", fontSize: "0.75rem" }}>Mandatory under Sec 3(1A) for unregistered buyers</small>
                </div>
            )}

            {/* 3rd Schedule Retail Price: Mandatory for SN008/SN027 */}
            {isSN008or027 && (
                <div>
                    <Input 
                        label="Printed Retail Price *" 
                        required 
                        type="number" 
                        min="0.01" 
                        step="any" 
                        value={line.fixed_notified_value_or_retail_price} 
                        onChange={event => update("fixed_notified_value_or_retail_price", event.target.value)} 
                    />
                    <small style={{ color: "#0284c7", fontSize: "0.75rem" }}>Tax is assessed on Retail Price × Quantity (Sec 3(2)(a))</small>
                </div>
            )}

            {/* FED Payable: Mandatory for SN017/SN018 */}
            {isSN017or018 && (
                <div>
                    <Input 
                        label="FED Payable *" 
                        required 
                        type="number" 
                        min="0" 
                        step="any" 
                        value={line.fed_payable} 
                        onChange={event => update("fed_payable", event.target.value)} 
                    />
                    <small style={{ color: "#0284c7", fontSize: "0.75rem" }}>Federal Excise Duty in Sales Tax Mode</small>
                </div>
            )}

            {/* SRO Fields: Mandatory for SN024 / Concessionary / Exempt scenarios */}
            {isSroRequired && (
                <>
                    <Input 
                        label="SRO Schedule Number *" 
                        required 
                        maxLength={100} 
                        placeholder={isSN024 ? "Goods as per SRO.297(|)/2023" : "e.g. Sixth Schedule"} 
                        value={line.sro_schedule_no || ""} 
                        onChange={event => update("sro_schedule_no", event.target.value)} 
                    />
                    <Input 
                        label="SRO Item Serial Number *" 
                        required 
                        maxLength={100} 
                        placeholder="e.g. 1(i)" 
                        value={line.sro_item_serial_no || ""} 
                        onChange={event => update("sro_item_serial_no", event.target.value)} 
                    />
                </>
            )}

            {/* Fallback for unclassified / production / other scenarios */}
            {!isSN001 && !isSN002 && !isSN008or027 && !isSN017or018 && !isSroRequired && (
                <>
                    <Input label="Extra tax" type="number" min="0" step="any" value={line.extra_tax} onChange={event => update("extra_tax", event.target.value)} />
                    <Input label="Further tax" type="number" min="0" step="any" value={line.further_tax} onChange={event => update("further_tax", event.target.value)} />
                    <Input label="FED payable" type="number" min="0" step="any" value={line.fed_payable} onChange={event => update("fed_payable", event.target.value)} />
                    <Input label="Printed retail price" type="number" min="0" step="any" value={line.fixed_notified_value_or_retail_price} onChange={event => update("fixed_notified_value_or_retail_price", event.target.value)} />
                    <Input label="SRO schedule number" maxLength={100} value={line.sro_schedule_no || ""} onChange={event => update("sro_schedule_no", event.target.value)} />
                    <Input label="SRO item serial number" maxLength={100} value={line.sro_item_serial_no || ""} onChange={event => update("sro_item_serial_no", event.target.value)} />
                </>
            )}
        </div>
    </section>;
}
