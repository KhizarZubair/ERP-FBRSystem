import { money } from "./invoiceApi";
import type { InvoiceLine, InvoiceRecord } from "./invoiceApi";

export function InvoiceTotals({ lines, invoice, scenarioId }: {
    lines?: InvoiceLine[];
    scenarioId?: string;
    invoice?: Pick<InvoiceRecord, "value_sales_excluding_st" | "sales_tax_applicable" | "sales_tax_withheld_at_source" | "extra_tax" | "further_tax" | "fed_payable" | "discount" | "total_values">;
}) {
    const scenario = (scenarioId || "").toUpperCase().trim();
    const estimate = (lines || []).reduce((sum, line) => {
        const qty = Number(line.quantity) || 0;
        const net = Math.max(0, qty * Number(line.unit_price) - Number(line.discount));
        const retailPrice = Number(line.fixed_notified_value_or_retail_price) || 0;
        const ratePercent = Number(line.tax_rate_percent) || 0;
        const saleTypeLower = (line.sale_type || "").toLowerCase().trim();
        const rateLabel = line.tax_rate_label || "";
        const parsedFixed = rateLabel.match(/(?:rs\.?|rupees)\s*([0-9]+(?:\.[0-9]+)?)/i);
        const isSN017 = scenario === "SN017" || saleTypeLower.includes("goods (fed in st mode)");
        const isSN021 = scenario === "SN021" || saleTypeLower.includes("cement") || saleTypeLower.includes("concrete block");
        const isSN022 = scenario === "SN022" || saleTypeLower.includes("potassium chlorate");
        const isThirdSchedule = scenario === "SN008" || scenario === "SN027" || saleTypeLower.includes("3rd schedule") || saleTypeLower.includes("third schedule") || (retailPrice > 0 && saleTypeLower.includes("retail"));

        let tax: number;
        if (isSN017) {
            const perUnitFed = parsedFixed ? Number(parsedFixed[1]) : 80;
            const fedComponent = qty * perUnitFed;
            tax = ((net + fedComponent) * ratePercent / 100) + fedComponent;
        } else if (isSN022) {
            const perKgDuty = parsedFixed ? Number(parsedFixed[1]) : 60;
            tax = (net * ratePercent / 100) + (qty * perKgDuty);
        } else if (isSN021) {
            const fixedRate = parsedFixed ? Number(parsedFixed[1]) : 2;
            tax = qty * fixedRate;
        } else {
            const taxBase = isThirdSchedule && retailPrice > 0 ? qty * retailPrice : net;
            tax = taxBase * ratePercent / 100;
        }

        const furtherTax = (isThirdSchedule || scenario === "SN001") ? 0 : Number(line.further_tax || 0);
        return {
            net: sum.net + net,
            tax: sum.tax + tax,
            total: sum.total + net + tax + Number(line.extra_tax || 0) + furtherTax + Number(line.fed_payable || 0) - Number(line.sales_tax_withheld_at_source || 0),
        };
    }, { net: 0, tax: 0, total: 0 });
    return <div className="totals">
        <div className="total-row"><span>Sales value excluding tax</span><span>{money(invoice?.value_sales_excluding_st ?? estimate.net)}</span></div>
        <div className="total-row"><span>Sales tax</span><span>{money(invoice?.sales_tax_applicable ?? estimate.tax)}</span></div>
        {invoice && <><div className="total-row"><span>Discount included above</span><span>{money(invoice.discount)}</span></div>
            <div className="total-row"><span>Extra / further tax / FED</span><span>{money(Number(invoice.extra_tax) + Number(invoice.further_tax) + Number(invoice.fed_payable))}</span></div>
            <div className="total-row"><span>Withholding</span><span>− {money(invoice.sales_tax_withheld_at_source)}</span></div></>}
        <div className="total-row total-row--grand"><span>Total</span><span>{money(invoice?.total_values ?? estimate.total)}</span></div>
    </div>;
}
