import { api } from "../../lib/api";
export type InvoiceLine = {
    client_key?: string; id?: number; line_number?: number; item_id: string | number; quantity: string; unit_price: string; tax_rate_percent: string;
    discount: string; sales_tax_withheld_at_source: string; extra_tax: string; further_tax: string; fed_payable: string;
    fixed_notified_value_or_retail_price: string; sro_schedule_no: string | null; sro_item_serial_no: string | null;
    tax_rate_label?: string; sku?: string; product_description?: string; hs_code?: string; unit_of_measurement?: string; sale_type?: string;
    value_sales_excluding_st?: string; sales_tax_applicable?: string; total_values?: string;
};
export type InvoiceDraft = {
    customer_id: string | number; invoice_date: string; due_date: string; scenario_id: string;
    reference_invoice_no: string; notes: string; version?: string; lines: InvoiceLine[];
};
export type InvoiceRecord = Omit<InvoiceDraft, "due_date" | "scenario_id" | "reference_invoice_no" | "notes"> & {
    id: number; number: string; due_date: string | null; scenario_id: string | null; reference_invoice_no: string | null;
    notes: string | null; status: string; fbr_status: string; fbr_environment: string; fbr_invoice_number: string | null;
    approved_at: string | null; currency: string; editable: boolean; version: string; value_sales_excluding_st: string;
    sales_tax_applicable: string; sales_tax_withheld_at_source: string; extra_tax: string; further_tax: string;
    fed_payable: string; discount: string; total_values: string;
    buyer_business_name: string; buyer_ntn_cnic: string | null; buyer_registration_type: string;
    buyer_province: string; buyer_address: string; seller_business_name: string; seller_ntn_cnic: string | null;
    seller_province: string; seller_address: string;
};
export type PageResult<T> = { data: T[]; meta: { current_page: number; last_page: number; total: number; per_page: number } };
export const invoiceBase = (company: number) => `/api/v1/companies/${company}/invoices`;
export function todayDate() {
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}-${String(now.getDate()).padStart(2, "0")}`;
}
export const inputDecimal = (value: string) => value.includes(".") ? value.replace(/0+$/, "").replace(/\.$/, "") : value;
export function blankLine(): InvoiceLine {
    return { client_key: crypto.randomUUID(), item_id: "", quantity: "1", unit_price: "0", tax_rate_percent: "0", discount: "0",
        sales_tax_withheld_at_source: "0", extra_tax: "0", further_tax: "0", fed_payable: "0",
        fixed_notified_value_or_retail_price: "0", sro_schedule_no: "", sro_item_serial_no: "" };
}
export function draftFrom(record?: InvoiceRecord): InvoiceDraft {
    return record ? { customer_id: record.customer_id, invoice_date: record.invoice_date, due_date: record.due_date || "",
        scenario_id: record.scenario_id || "", reference_invoice_no: record.reference_invoice_no || "", notes: record.notes || "",
        version: record.version, lines: record.lines.map(line => ({ ...line, client_key: crypto.randomUUID(), quantity: inputDecimal(line.quantity), unit_price: inputDecimal(line.unit_price), tax_rate_percent: inputDecimal(line.tax_rate_percent) })) }
        : { customer_id: "", invoice_date: todayDate(), due_date: "", scenario_id: "", reference_invoice_no: "", notes: "", lines: [blankLine()] };
}
export type InvoicePreview = Omit<InvoiceRecord, "id"> & { id: null };
export const invoiceApi = {
    preview(company: number, draft: InvoiceDraft, id?: string) {
        return api<{ data: InvoicePreview }>(invoiceBase(company) + "/preview", { method: "POST", body: JSON.stringify({ ...draft, ...(id ? { invoice_id: Number(id) } : {}) }) });
    },
    approve(company: number, id: number, version: string) {
        return api<{ data: InvoiceRecord }>(invoiceBase(company) + "/" + id + "/approve", { method: "POST", body: JSON.stringify({ version, confirmed: true }) });
    },
    reopen(company: number, id: number, version: string) {
        return api<{ data: InvoiceRecord }>(invoiceBase(company) + "/" + id + "/reopen", { method: "POST", body: JSON.stringify({ version, confirmed: true }) });
    },
    save(company: number, draft: InvoiceDraft, id?: string) {
        return api<{ data: InvoiceRecord }>(invoiceBase(company) + (id ? "/" + id : ""), { method: id ? "PUT" : "POST", body: JSON.stringify(draft) });
    },
    delete(company: number, id: number, version: string) {
        return api<{ message: string }>(invoiceBase(company) + "/" + id, { method: "DELETE", body: JSON.stringify({ version }) });
    },
};
export const money = (value: string | number) => new Intl.NumberFormat("en-PK", { style: "currency", currency: "PKR", minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value));
export const statusLabel = (value: string) => value.replaceAll("_", " ").replace(/^./, first => first.toUpperCase());
