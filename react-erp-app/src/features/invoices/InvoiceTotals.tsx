import { money } from "./invoiceApi";
import type { InvoiceLine, InvoiceRecord } from "./invoiceApi";
export function InvoiceTotals({ lines, invoice }: { lines?: InvoiceLine[]; invoice?: Pick<InvoiceRecord, "value_sales_excluding_st" | "sales_tax_applicable" | "sales_tax_withheld_at_source" | "extra_tax" | "further_tax" | "fed_payable" | "discount" | "total_values"> }) {
    const estimate = (lines || []).reduce((sum, line) => {
        const net = Math.max(0, Number(line.quantity) * Number(line.unit_price) - Number(line.discount));
        const tax = net * Number(line.tax_rate_percent) / 100;
        return { net: sum.net + net, tax: sum.tax + tax, total: sum.total + net + tax + Number(line.extra_tax) + Number(line.further_tax) + Number(line.fed_payable) - Number(line.sales_tax_withheld_at_source) };
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
