import { DataTable } from "../../components/DataTable";
import { Card } from "../../components/ui";
import { money } from "./invoiceApi";
import type { InvoiceRecord } from "./invoiceApi";
import { InvoiceTotals } from "./InvoiceTotals";

export type InvoiceSnapshot = Pick<InvoiceRecord, "number" | "invoice_date" | "due_date" | "scenario_id" | "reference_invoice_no" | "notes" | "currency" |
    "seller_business_name" | "seller_ntn_cnic" | "seller_province" | "seller_address" | "buyer_business_name" | "buyer_ntn_cnic" |
    "buyer_registration_type" | "buyer_province" | "buyer_address" | "lines" | "value_sales_excluding_st" | "sales_tax_applicable" |
    "sales_tax_withheld_at_source" | "extra_tax" | "further_tax" | "fed_payable" | "discount" | "total_values">;

export function InvoiceSummary({ invoice }: { invoice: InvoiceSnapshot }) {
    return <>
        <Card title="Invoice details">
            <div className="split-grid"><div><h3>Seller</h3><p>{invoice.seller_business_name}<br />{invoice.seller_ntn_cnic || "Identity not configured"}<br />{invoice.seller_province} • {invoice.seller_address}</p></div>
                <div><h3>Buyer</h3><p>{invoice.buyer_business_name}<br />{invoice.buyer_ntn_cnic || "Identity not provided"} • {invoice.buyer_registration_type}<br />{invoice.buyer_province} • {invoice.buyer_address}</p></div></div>
            <p className="reference-note">Invoice: {invoice.number} • Date: {invoice.invoice_date} • Due: {invoice.due_date || "Not set"} • Currency: {invoice.currency}</p>
            {invoice.scenario_id && <p className="reference-note">Scenario: {invoice.scenario_id}</p>}
            {invoice.reference_invoice_no && <p className="reference-note">Reference: {invoice.reference_invoice_no}</p>}
        </Card>
        <Card title="Invoice items"><DataTable caption="Invoice verification lines" rows={invoice.lines} rowKey={line => String(line.id ?? line.line_number)} pageSize={100} columns={[
            { key: "item", label: "Item", render: line => <>{line.product_description}<div className="cell-subtitle">{line.sku} • HS {line.hs_code || "Not provided"} • {line.sale_type}</div></> },
            { key: "unit", label: "Unit", render: line => line.unit_of_measurement },
            { key: "quantity", label: "Quantity", render: line => line.quantity },
            { key: "price", label: "Unit price", render: line => money(line.unit_price) },
            { key: "rate", label: "Tax rate", render: line => line.tax_rate_percent + "%" },
            { key: "tax", label: "Sales tax", render: line => money(line.sales_tax_applicable || "0") },
            { key: "total", label: "Total", render: line => money(line.total_values || "0") },
        ]} />
            <section className="line-tax-section"><h3>Discounts, additional taxes and SRO details</h3>
                {invoice.lines.map(line => <p key={line.id ?? line.line_number} className="reference-note">
                    {line.sku}: Discount {money(line.discount)} • Withholding {money(line.sales_tax_withheld_at_source)} • Extra tax {money(line.extra_tax)} • Further tax {money(line.further_tax)} • FED {money(line.fed_payable)} • Retail value {money(line.fixed_notified_value_or_retail_price)} • SRO {line.sro_schedule_no || "Not provided"} / {line.sro_item_serial_no || "Not provided"}
                </p>)}</section>
            <InvoiceTotals invoice={invoice} />{invoice.notes && <p className="reference-note">Notes: {invoice.notes}</p>}
        </Card>
    </>;
}
