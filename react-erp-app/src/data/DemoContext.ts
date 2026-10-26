import { createContext, useContext } from "react";
import type { Dispatch, SetStateAction } from "react";
import type { Company, Customer, Item, Invoice } from "./demo";
type DemoStore = {
    company: Company;
    setCompany: Dispatch<SetStateAction<Company>>;
    customers: Customer[];
    setCustomers: Dispatch<SetStateAction<Customer[]>>;
    items: Item[];
    setItems: Dispatch<SetStateAction<Item[]>>;
    invoices: Invoice[];
    setInvoices: Dispatch<SetStateAction<Invoice[]>>;
};
export const Context = createContext<DemoStore | null>(null);
export function useDemo() {
    const store = useContext(Context);
    if (!store) throw new Error("DemoProvider is required");
    return store;
}
