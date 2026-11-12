import { useRef, useState } from "react";
import { Button, Card, FormActions, Input, Notice, Select, Textarea } from "../../components/ui";
import { provinces } from "../../data/demo";
import { ApiError } from "../../lib/api";
import { companyApi, companyDraft } from "./companyApi";
import type { CompanyDetails, CompanyDraft } from "./companyApi";

export function CompanyForm({ company, onSaved, onCancel }: { company: CompanyDetails; onSaved: (company: CompanyDetails) => void; onCancel: () => void }) {
    const [draft, setDraft] = useState(() => companyDraft(company));
    const [busy, setBusy] = useState(false);
    const [message, setMessage] = useState("");
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const lock = useRef(false);

    function change(key: Exclude<keyof CompanyDraft, "allowed_sandbox_scenarios">, value: string) {
        setDraft(current => ({ ...current, [key]: value, ...(key === "fbr_environment" ? { sandbox_token: "", production_token: "" } : {}) }));
        setErrors(current => ({ ...current, [key]: [] }));
    }

    const field = (key: Exclude<keyof CompanyDraft, "allowed_sandbox_scenarios">) => ({
        name: key,
        value: draft[key],
        onChange: (event: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => change(key, event.target.value),
        error: errors[key]?.[0]
    });

    const businessActivityOptions = [
        { value: "", label: "Select Business Nature / Activity" },
        ...(company.business_activities?.length ? company.business_activities : [
            "Manufacturer", "Importer", "Distributor", "Wholesaler",
            "Retailer", "Exporter", "Service Provider", "Other"
        ]).map(activity => ({ value: activity, label: activity }))
    ];

    const sectorOptions = [
        { value: "", label: "Select FBR Sector" },
        ...(company.sectors?.length ? company.sectors : [
            "All Other Sectors", "Steel", "FMCG", "Textile", "Telecom", "Petroleum",
            "Electricity Distribution", "Gas Distribution", "Services", "Automobile",
            "CNG Stations", "Pharmaceuticals", "Wholesale / Retails"
        ]).map(sector => ({ value: sector, label: sector }))
    ];

    return <form onSubmit={async event => {
        event.preventDefault();
        if (lock.current) return;
        lock.current = true;
        setBusy(true);
        setMessage("");
        setErrors({});
        if (!draft.ntn_cnic.trim() && !draft.seller_cnic.trim()) {
            setMessage("Please provide either Seller NTN (7 or 8 digits) or Seller CNIC (13 digits).");
            setErrors({
                seller_ntn: ["Either NTN or CNIC is required."],
                seller_cnic: ["Either CNIC or NTN is required."],
            });
            lock.current = false;
            setBusy(false);
            return;
        }
        try {
            const response = await companyApi.update(company.id, {
                name: draft.name,
                seller_ntn: draft.ntn_cnic.trim() || undefined,
                seller_cnic: draft.seller_cnic.trim() || undefined,
                province: draft.province,
                address: draft.address,
                contact_person: draft.contact_person,
                email: draft.email,
                phone: draft.phone,
                business_activity: draft.business_activity || undefined,
                sector: draft.sector || undefined,
            });
            onSaved(response.data);
        } catch (error) {
            setMessage(error instanceof Error ? error.message : "Could not save company details.");
            if (error instanceof ApiError) setErrors(error.errors);
        } finally {
            lock.current = false;
            setBusy(false);
        }
    }}>
        {message && <Notice tone="warning">{message}</Notice>}
        <fieldset className="form-fields" disabled={busy}>
            <Card title="Seller identity & FBR classification">
                <div className="form-grid">
                    <Input label="Registered business name" required maxLength={255} {...field("name")} />
                    <Input label="Seller NTN" name="seller_ntn" placeholder="e.g. 1234567" inputMode="numeric" pattern="[0-9]{7,8}" minLength={7} maxLength={8} value={draft.ntn_cnic} onChange={event => change("ntn_cnic", event.target.value)} error={errors.seller_ntn?.[0]} hint="7 or 8 digits (Required if CNIC is not provided)." />
                    <Input label="Seller CNIC" name="seller_cnic" placeholder="13 digits without dashes (e.g. 3520112345671)" inputMode="numeric" pattern="[0-9]{13}" minLength={13} maxLength={13} value={draft.seller_cnic} onChange={event => change("seller_cnic", event.target.value)} error={errors.seller_cnic?.[0]} hint="13 digits without dashes (Required if NTN is not provided)." />
                    <Select label="Province" required options={[{ value: "", label: "Select province" }, ...provinces]} {...field("province")} />
                    <Select label="FBR Business Activity / Nature *" required options={businessActivityOptions} {...field("business_activity")} hint="Primary business activity as registered with FBR (Manufacturer, Retailer, Wholesaler, etc.)." />
                    <Select label="FBR Business Sector *" required options={sectorOptions} {...field("sector")} hint="Official sector determining applicable FBR tax rules and sandbox testing scenarios." />
                    <Input label="Contact person" maxLength={255} {...field("contact_person")} />
                    <Input label="Company email" required hint="Business contact email; this does not change your login email." type="email" maxLength={255} {...field("email")} />
                    <Input label="Phone" maxLength={30} {...field("phone")} />
                    <Textarea label="Registered address" required maxLength={2000} {...field("address")} />
                </div>
            </Card>
            <FormActions>
                <Button variant="secondary" onClick={onCancel}>Cancel</Button>
                <Button type="submit">{busy ? "Saving…" : "Save company details"}</Button>
            </FormActions>
        </fieldset>
    </form>;
}
