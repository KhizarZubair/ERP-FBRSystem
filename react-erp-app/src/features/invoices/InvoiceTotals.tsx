import { money } from "./invoiceApi";
import type { InvoiceLine, InvoiceRecord } from "./invoiceApi";
export function InvoiceTotals({ lines, invoice }: { lines?: InvoiceLine[]; invoice?: Pick<InvoiceRecord, "value_sales_excluding_st" | "sales_tax_applicable" | "sales_tax_withheld_at_source" | "extra_tax" | "further_tax" | "fed_payable" | "discount" | "total_values"> }) {
    const estimate = (lines || []).reduce((sum, line) => {
        const net = Math.max(0, Number(line.quantity) * Number(line.unit_price) - Number(line.discount));
        const retailPrice = Number(line.fixed_notified_value_or_retail_price) || 0;
        const isThirdSchedule = retailPrice > 0 && (
            (line.sale_type && (line.sale_type.toLowerCase().includes("3rd schedule") || line.sale_type.toLowerCase().includes("third schedule"))) ||
            retailPrice > 0
        );
        const taxBase = isThirdSchedule ? (Number(line.quantity) * retailPrice) : net;
        const tax = taxBase * Number(line.tax_rate_percent) / 100;
        const furtherTax = isThirdSchedule ? 0 : Number(line.further_tax || 0);
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
