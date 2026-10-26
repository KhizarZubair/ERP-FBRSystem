import { api } from "../../lib/api";
export type StockBalance = { id: number; name: string; sku: string; unit_of_measurement: string; reorder_level: string;
    is_active: boolean; opening: string; received: string; issued: string; adjusted: string; available: string };
export type StockMovement = { id: number; item_id: number; item_name: string; sku: string; unit_of_measurement: string;
    movement_date: string; document_number: string; movement_type: string; quantity: string; running_balance: string;
    reversed_by_id: number | null; reversal_of_id: number | null; invoice_item_id: number | null; notes: string | null };
export type StockPageResult<T> = { data: T[]; current_page: number; last_page: number; total: number; per_page: number };
export type MovementDraft = { item_id: string; movement_date: string; document_number: string; movement_type: string;
    quantity: string; notes: string; idempotency_key: string };
export const stockBase = (company: number) => `/api/v1/companies/${company}/stock`;
export const stockApi = {
    post(company: number, draft: MovementDraft) { return api(stockBase(company) + "/movements", { method: "POST", body: JSON.stringify(draft) }); },
    reverse(company: number, id: number, notes: string) { return api(stockBase(company) + "/movements/" + id + "/reverse", { method: "POST", body: JSON.stringify({ notes }) }); },
};
