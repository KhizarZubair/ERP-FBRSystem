import { useState } from "react";
import type { ReactNode } from "react";
import { Context } from "./DemoContext";
import type { Company } from "./demo";
import { companySeed, customersSeed, itemsSeed, invoicesSeed } from "./demo";
export function DemoProvider({ children, companyProfile }: { children: ReactNode; companyProfile?: Partial<Company> }) {
    const [company, setCompany] = useState({ ...companySeed, ...companyProfile }),
        [customers, setCustomers] = useState(customersSeed),
        [items, setItems] = useState(itemsSeed),
        [invoices, setInvoices] = useState(invoicesSeed);
    return (
        <Context.Provider
            value={{
                company,
                setCompany,
                customers,
                setCustomers,
                items,
                setItems,
                invoices,
                setInvoices,
            }}
        >
            {children}
        </Context.Provider>
    );
}
