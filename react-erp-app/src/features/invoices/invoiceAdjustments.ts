import type { InvoiceLine } from "./invoiceApi";
const scale = 1000000n;
export const amountFields = ["discount", "sales_tax_withheld_at_source", "extra_tax", "further_tax", "fed_payable"] as const;
export type InvoiceAdjustments = Pick<InvoiceLine, typeof amountFields[number] | "fixed_notified_value_or_retail_price" | "sro_schedule_no" | "sro_item_serial_no">;
export function decimalUnits(value: string): bigint {
    if (!/^\d*(?:\.\d*)?$/.test(value) || !value) return 0n;
    const [whole = "0", fraction = ""] = value.split(".");
    return BigInt(whole || "0") * scale + BigInt((fraction + "000000").slice(0, 6));
}
export function decimalString(value: bigint): string {
    const fraction = (value % scale).toString().padStart(6, "0").replace(/0+$/, "");
    return (value / scale).toString() + (fraction ? "." + fraction : "");
}
export function overallAdjustments(lines: InvoiceLine[]): InvoiceAdjustments {
    return { ...Object.fromEntries(amountFields.map(key => [key, decimalString(lines.reduce((sum, line) => sum + decimalUnits(line[key]), 0n))])),
        fixed_notified_value_or_retail_price: lines[0]?.fixed_notified_value_or_retail_price || "0",
        sro_schedule_no: lines[0]?.sro_schedule_no || "", sro_item_serial_no: lines[0]?.sro_item_serial_no || "" } as InvoiceAdjustments;
}
function distribute(amount: bigint, weights: bigint[]): bigint[] {
    if (!weights.length) return [];
    const total = weights.reduce((sum, weight) => sum + weight, 0n);
    if (!total) return weights.map((_, index) => index === 0 ? amount : 0n);
    const parts = weights.map(weight => amount * weight / total);
    let remaining = amount - parts.reduce((sum, part) => sum + part, 0n);
    const ranked = weights.map((weight, index) => ({ index, remainder: amount * weight % total })).sort((a, b) => a.remainder === b.remainder ? a.index - b.index : a.remainder > b.remainder ? -1 : 1);
    for (const { index } of ranked) { if (remaining === 0n) break; parts[index] += 1n; remaining -= 1n; }
    return parts;
}
export function applyOverallAdjustments(lines: InvoiceLine[], adjustments: InvoiceAdjustments): InvoiceLine[] {
    const gross = lines.map(line => (decimalUnits(line.quantity) * decimalUnits(line.unit_price) + scale / 2n) / scale);
    const discounts = distribute(decimalUnits(adjustments.discount), gross);
    const net = gross.map((value, index) => value > discounts[index] ? value - discounts[index] : 0n);
    const tax = net.map((value, index) => (value * decimalUnits(lines[index].tax_rate_percent) + 50n * scale) / (100n * scale));
    const allocations = Object.fromEntries(amountFields.map(key => [key, key === "discount" ? discounts : distribute(decimalUnits(adjustments[key]), key === "sales_tax_withheld_at_source" ? tax : net)])) as Record<typeof amountFields[number], bigint[]>;
    return lines.map((line, index) => ({ ...line,
        ...Object.fromEntries(amountFields.map(key => [key, decimalString(allocations[key][index])])),
        fixed_notified_value_or_retail_price: adjustments.fixed_notified_value_or_retail_price || "0",
        sro_schedule_no: adjustments.sro_schedule_no || "", sro_item_serial_no: adjustments.sro_item_serial_no || "" }));
}
