import { useRef, useState } from "react";
import type { ChangeEvent } from "react";
import { Button, Card, FormActions, Input, Notice, Select, Textarea, Badge } from "../../components/ui";
import { provinces } from "../../data/demo";
import { api, ApiError } from "../../lib/api";
import type { FieldErrors } from "../../lib/api";
import { navigate } from "../../lib/navigation";
import { customerApi, customerDraft } from "./customerApi";
import type { CustomerDraft, CustomerRecord } from "./customerApi";

type BuyerVerification = { data: { active: boolean; status: string; registration_type: string; source: string } };

export function CustomerForm({ companyId, customer }: { companyId: number; customer?: CustomerRecord }) {
    const [draft, setDraft] = useState(() => customerDraft(customer));
    const [busy, setBusy] = useState(false);
    const [verifying, setVerifying] = useState(false);
    const [message, setMessage] = useState("");
    const [errors, setErrors] = useState<FieldErrors>({});
    const [verifiedNumber, setVerifiedNumber] = useState("");
    const [verification, setVerification] = useState<BuyerVerification["data"]>();
    const lock = useRef(false);
    function change(key: keyof CustomerDraft, value: string | boolean) {
        setDraft(current => ({ ...current, [key]: value }));
        setErrors(current => ({ ...current, [key]: [] }));
        if (key === "ntn_cnic" && value !== verifiedNumber) {
            setVerifiedNumber("");
            setVerification(undefined);
            setMessage("");
        }
    }
    const field = (key: Exclude<keyof CustomerDraft, "is_active">) => ({
        name: key, value: draft[key] || "", error: errors[key]?.[0],
        onChange: (event: ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => change(key, event.target.value),
    });
    const registrationNumber = String(draft.ntn_cnic || "").trim();
    const validRegistrationNumber = /^(?:[0-9]{7,8}|[0-9]{13})$/.test(registrationNumber);
    const isVerified = Boolean(
        (registrationNumber && verifiedNumber === registrationNumber && verification) ||
        (customer && draft.ntn_cnic === customer.ntn_cnic && customer.ntn_cnic)
    );
    const canSave = !busy && isVerified;
    async function verifyBuyer() {
        if (!validRegistrationNumber || verifying) return;
        setVerifying(true); setMessage(""); setVerification(undefined);
        try {
            const response = await api<BuyerVerification>(`/api/v1/companies/${companyId}/fbr/buyer-verification?registration_number=${encodeURIComponent(registrationNumber)}&date=${new Date().toISOString().slice(0, 10)}`);
            const result = response.data;
            setVerification(result);
            setVerifiedNumber(registrationNumber);
            change("registration_type", result.active && result.registration_type.toLowerCase() === "registered" ? "Registered" : "Unregistered");
            if (!result.active) setMessage("FBR did not confirm an active taxpayer. Customer will be marked Unregistered.");
        } catch (error) {
            setVerifiedNumber("");
            setVerification(undefined);
            setMessage(error instanceof Error ? error.message : "FBR verification failed. Please retry.");
        } finally { setVerifying(false); }
    }
    return <Card title={customer ? "Edit Customer" : "Create Customer"}><form onSubmit={async event => {
        event.preventDefault();
        if (lock.current || !canSave) return;
        if (!isVerified) {
            setMessage("Please verify this NTN/CNIC with FBR before saving the customer.");
            return;
        }
        lock.current = true; setBusy(true); setMessage(""); setErrors({});
        try {
            await customerApi.save(companyId, draft, customer?.id);
            navigate("/customers");
        } catch (error) {
            setMessage(error instanceof Error ? error.message : "Could not save customer.");
            if (error instanceof ApiError) setErrors(error.errors);
        } finally { lock.current = false; setBusy(false); }
    }}>
        {message && <Notice tone="warning">{message}</Notice>}
        <fieldset className="form-fields" disabled={busy}>
            <div className="form-grid">
                <Input label="Business / customer name" required maxLength={255} {...field("name")} />
                <Input label="Customer code" required={!!customer} maxLength={50} pattern={"[A-Za-z0-9_\\-]+"} placeholder="Generated automatically if left blank" {...field("code")} />
                <div className="field">
                    <label htmlFor="customer-ntn-cnic">NTN / CNIC</label>
                    <input id="customer-ntn-cnic" name="ntn_cnic" value={draft.ntn_cnic || ""} inputMode="numeric" pattern="([0-9]{7,8}|[0-9]{13})" maxLength={13}
                        placeholder="7 or 8-digit NTN or 13-digit CNIC" aria-invalid={!!errors.ntn_cnic}
                        onChange={event => change("ntn_cnic", event.target.value)} />
                    <small className={errors.ntn_cnic ? "field-error" : "field-hint"}>{errors.ntn_cnic?.[0] || "Enter digits only, without dashes or spaces. Click 'Verify with FBR' below to enable saving."}</small>
                    {verification && verifiedNumber === registrationNumber && <div className="customer-verification-status">
                        <Badge tone={verification.active ? "success" : "warning"}>{verification.active ? "Taxpayer Verified: Active" : "Unregistered Buyer"}</Badge>
                        <span>{verification.active ? verification.registration_type : "Registration not active"} · Checked via FBR</span>
                    </div>}
                </div>
                <Select label="Province" required options={[{ value: "", label: "Select province" }, ...provinces]} {...field("province")} />
                <Select label="Status" options={["Active", "Inactive"]} value={draft.is_active ? "Active" : "Inactive"} onChange={event => change("is_active", event.target.value === "Active")} error={errors.is_active?.[0]} />
                <Input label="City (optional)" maxLength={100} {...field("city")} />
                <Textarea label="Billing / business address" required maxLength={2000} {...field("address")} />
                <Input label="Email" type="email" maxLength={255} {...field("email")} />
                <Input label="Phone" type="tel" maxLength={30} {...field("phone")} />
            </div>
            <FormActions>
                <Button variant="secondary" onClick={() => navigate("/customers")}>Cancel</Button>
                <Button type="button" variant="secondary" disabled={!validRegistrationNumber || verifying || busy || isVerified} onClick={verifyBuyer}>
                    {verifying ? "Checking FBR…" : isVerified ? "Verified with FBR ✓" : "Verify with FBR"}
                </Button>
                <Button type="submit" disabled={!canSave}>{busy ? "Saving…" : customer ? "Update Customer" : "Save Customer"}</Button>
            </FormActions>
        </fieldset>
    </form></Card>;
}
