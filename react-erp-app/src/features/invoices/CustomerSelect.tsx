import { SearchableSelect } from "../../components/SearchableSelect";
import type { CustomerRecord } from "../customers/customerApi";
import { invoiceBase } from "./invoiceApi";
export function CustomerSelect({ companyId, value, selectedLabel, onSelect }: {
    companyId: number; value: string; selectedLabel: string; onSelect: (customer: CustomerRecord) => void;
}) {
    return <SearchableSelect path={invoiceBase(companyId) + "/lookups/customers"} label="Customer" {...{value, selectedLabel, onSelect}}
        describe={customer => [customer.code, customer.ntn_cnic, customer.province].filter(Boolean).join(" · ")} />;
}
