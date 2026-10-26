import { api } from "../../lib/api";
export type ItemRecord = {
    id: number; sku: string; name: string; description: string; hs_code: string;
    unit_of_measurement: string; sale_type: string; tax_rate_label: string; tax_rate_percent: string;
    extra_tax: string; fed_payable: string; fixed_notified_value_or_retail_price: string; sro_schedule_no: string | null; sro_item_serial_no: string | null;
    track_stock: boolean; reorder_level: string; is_active: boolean; current_unit_price?: string | null; on_hand?: string;
};
export type ItemDraft = Omit<ItemRecord, "id" | "current_unit_price" | "on_hand">;
export type PriceRecord = { id: number; price_list: string; unit_price: string; currency: string; effective_from: string; effective_to: string | null; is_active: boolean };
export type PriceDraft = Omit<PriceRecord, "id">;
export type Paginated<T> = { data: T[]; meta: { current_page: number; last_page: number; total: number; per_page: number } };
export type ItemFilters = { search: string; is_active: string; price_list: string; page: number; sort: string; direction: string };
const base = (company: number) => `/api/v1/companies/${company}/items`;
const prices = (company: number, item: number) => base(company) + "/" + item + "/prices";
export const itemApi = {
    list(company: number, filters: ItemFilters, signal?: AbortSignal) {
        const query = new URLSearchParams({ page: String(filters.page), per_page: "15", price_list: filters.price_list, sort: filters.sort, direction: filters.direction });
        if (filters.search.trim()) query.set("search", filters.search.trim());
        if (filters.is_active !== "") query.set("is_active", filters.is_active);
        return api<Paginated<ItemRecord>>(base(company) + "?" + query, { signal });
    },
    get(company: number, id: number) { return api<{ data: ItemRecord }>(base(company) + "/" + id); },
    save(company: number, draft: ItemDraft, id?: number, price?: PriceDraft) {
        return api<{ data: ItemRecord }>(base(company) + (id ? "/" + id : ""), { method: id ? "PUT" : "POST", body: JSON.stringify(price ? { ...draft, price } : draft) });
    },
    status(company: number, id: number, active: boolean) {
        return api<{ data: ItemRecord }>(base(company) + "/" + id + "/status", { method: "PATCH", body: JSON.stringify({ is_active: active }) });
    },
    delete(company: number, id: number) { return api<{ message: string }>(base(company) + "/" + id, { method: "DELETE" }); },
    prices(company: number, item: number, page: number, signal?: AbortSignal) {
        return api<Paginated<PriceRecord>>(prices(company, item) + "?page=" + page + "&per_page=15", { signal });
    },
    savePrice(company: number, item: number, draft: PriceDraft, id?: number) {
        return api<{ data: PriceRecord }>(prices(company, item) + (id ? "/" + id : ""), { method: id ? "PUT" : "POST", body: JSON.stringify(draft) });
    },
    deletePrice(company: number, item: number, id: number) {
        return api<{ message: string }>(prices(company, item) + "/" + id, { method: "DELETE" });
    },
};
export function itemDraft(item?: ItemRecord): ItemDraft {
    return item ? { sku: item.sku, name: item.name, description: item.description, hs_code: item.hs_code,
        unit_of_measurement: item.unit_of_measurement, sale_type: item.sale_type, tax_rate_percent: item.tax_rate_percent, tax_rate_label: item.tax_rate_label, extra_tax: item.extra_tax || "0", fed_payable: item.fed_payable || "0",
        fixed_notified_value_or_retail_price: item.fixed_notified_value_or_retail_price,
        sro_schedule_no: item.sro_schedule_no || "", sro_item_serial_no: item.sro_item_serial_no || "",
        track_stock: item.track_stock, reorder_level: item.reorder_level, is_active: item.is_active }
        : { sku: "", name: "", description: "", hs_code: "", unit_of_measurement: "", sale_type: "",
            tax_rate_percent: "", tax_rate_label: "", extra_tax: "0", fed_payable: "0", fixed_notified_value_or_retail_price: "0", sro_schedule_no: "", sro_item_serial_no: "",
            track_stock: true, reorder_level: "0", is_active: true };
}
export function priceDraft(price?: PriceRecord): PriceDraft {
    const now = new Date();
    const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}-${String(now.getDate()).padStart(2, "0")}`;
    return price ? { price_list: price.price_list, unit_price: price.unit_price, currency: price.currency,
        effective_from: price.effective_from, effective_to: price.effective_to || "", is_active: price.is_active }
        : { price_list: "Standard", unit_price: "", currency: "PKR", effective_from: today, effective_to: "", is_active: true };
}
