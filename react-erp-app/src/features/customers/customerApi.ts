import { api } from "../../lib/api";
export type CustomerRecord = {
    id: number; code: string; name: string; registration_type: "Registered" | "Unregistered";
    ntn_cnic: string | null; province: string; city: string | null; address: string;
    email: string | null; phone: string | null; is_active: boolean;
};
export type CustomerDraft = Omit<CustomerRecord, "id">;
export type CustomerFilters = { search: string; registration_type: string; page: number; sort: string; direction: string };
export type CustomerPage = { data: CustomerRecord[]; meta: { current_page: number; last_page: number; total: number; per_page: number } };
const base = (company: number) => `/api/v1/companies/${company}/customers`;
export const customerApi = {
    list(company: number, filters: CustomerFilters, signal?: AbortSignal) {
        const query = new URLSearchParams({ page: String(filters.page), per_page: "15", sort: filters.sort, direction: filters.direction });
        if (filters.search.trim()) query.set("search", filters.search.trim());
        if (filters.registration_type) query.set("registration_type", filters.registration_type);
        return api<CustomerPage>(base(company) + "?" + query, { signal });
    },
    get(company: number, id: number) { return api<{ data: CustomerRecord }>(base(company) + "/" + id); },
    save(company: number, data: CustomerDraft, id?: number) {
        return api<{ data: CustomerRecord }>(base(company) + (id ? "/" + id : ""), { method: id ? "PUT" : "POST", body: JSON.stringify(data) });
    },
    status(company: number, id: number, active: boolean) {
        return api<{ data: CustomerRecord }>(base(company) + "/" + id + "/status", { method: "PATCH", body: JSON.stringify({ is_active: active }) });
    },
    delete(company: number, id: number) {
        return api<{ message: string }>(base(company) + "/" + id, { method: "DELETE" });
    },
};
export function customerDraft(customer?: CustomerRecord): CustomerDraft {
    return customer ? { code: customer.code, name: customer.name, registration_type: customer.registration_type,
        ntn_cnic: customer.ntn_cnic || "", province: customer.province, city: customer.city || "",
        address: customer.address, email: customer.email || "", phone: customer.phone || "", is_active: customer.is_active }
        : { code: "", name: "", registration_type: "Registered", ntn_cnic: "", province: "", city: "", address: "", email: "", phone: "", is_active: true };
}
