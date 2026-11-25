import { useRef, useState } from "react";
import type { ChangeEvent } from "react";
import { Button, Card, FormActions, Input, Notice, Select, Textarea, Badge } from "../../components/ui";
import { provinces } from "../../data/demo";
import { api, ApiError } from "../../lib/api";
import type { FieldErrors } from "../../lib/api";
import { navigate } from "../../lib/navigation";
import { customerApi, customerDraft } from "./customerApi";
import type { CustomerDraft, CustomerRecord } from "./customerApi";

type BuyerVerification = { data: { active: boolean; status: string; registration_type: string; source: string; is_offline?: boolean; message?: string } };

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
        if (key === "ntn" && value !== verifiedNumber) {
            setVerifiedNumber("");
            setVerification(undefined);
            setMessage("");
        }
    }

    const field = (key: Exclude<keyof CustomerDraft, "is_active">) => ({
        name: key, value: draft[key] || "", error: errors[key]?.[0],
        onChange: (event: ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => change(key, event.target.value),
    });

    const isRegistered = draft.registration_type === "Registered";
    const ntnValue = String(draft.ntn || "").trim();
    const cnicValue = String(draft.cnic || "").trim();
    const validNtn = /^[0-9]{7,8}$/.test(ntnValue);

    const isVerified = Boolean(
        (ntnValue && verifiedNumber === ntnValue && verification?.active) ||
        (customer && customer.registration_type === "Registered" && (customer.ntn === ntnValue || customer.ntn_cnic === ntnValue))
    );

    const canSave = !busy;

    async function verifyBuyer() {
        if (!validNtn || verifying) return;
        setVerifying(true); setMessage(""); setVerification(undefined);
        try {
            const response = await api<BuyerVerification>(
                `/api/v1/companies/${companyId}/fbr/buyer-verification?registration_number=${encodeURIComponent(ntnValue)}&date=${new Date().toISOString().slice(0, 10)}`
            );
            const result = response.data;
            setVerification(result);
            setVerifiedNumber(ntnValue);
            if (result.is_offline) {
                setMessage("FBR verification gateway is currently slow or unreachable. You can save this customer locally now.");
            } else if (result.active && result.registration_type?.toLowerCase() === "registered") {
                setMessage("FBR Taxpayer Verified: Active Registered Taxpayer.");
            } else {
                setMessage("FBR did not confirm this NTN as an active registered taxpayer.");
            }
        } catch (error) {
            setVerifiedNumber("");
            setVerification(undefined);
            setMessage("FBR verification gateway is currently unreachable. Customer can be saved locally.");
        } finally { setVerifying(false); }
    }

    return <Card title={customer ? "Edit Customer" : "Create Customer"}><form onSubmit={async event => {
        event.preventDefault();
        if (lock.current || !canSave) return;

        // Custom validation check
        const newErrors: FieldErrors = {};
        if (isRegistered) {
            if (!ntnValue) {
                newErrors.ntn = ["Buyer NTN is required for registered customers."];
            } else if (!/^[0-9]{7,8}$/.test(ntnValue)) {
                newErrors.ntn = ["Buyer NTN must be 7 or 8 digits."];
            }
            if (cnicValue && !/^[0-9]{13}$/.test(cnicValue)) {
                newErrors.cnic = ["CNIC must be 13 digits without dashes."];
            }
        } else {
            if (cnicValue && !/^[0-9]{13}$/.test(cnicValue)) {
                newErrors.cnic = ["Buyer CNIC must be 13 digits without dashes if provided."];
            }
        }

        if (Object.keys(newErrors).length > 0) {
            setErrors(newErrors);
            return;
        }

        lock.current = true; setBusy(true); setMessage(""); setErrors({});
        try {
            const payload: CustomerDraft = {
                ...draft,
                registration_type: draft.registration_type,
                ntn: isRegistered ? ntnValue : "",
                cnic: cnicValue,
                ntn_cnic: isRegistered ? ntnValue : cnicValue,
            };
            await customerApi.save(companyId, payload, customer?.id);
            navigate("/customers");
        } catch (error) {
            setMessage(error instanceof Error ? error.message : "Could not save customer.");
            if (error instanceof ApiError) setErrors(error.errors);
        } finally { lock.current = false; setBusy(false); }
    }}>
        {message && <Notice tone="warning">{message}</Notice>}
        <fieldset className="form-fields" disabled={busy}>
            <div className="form-grid">
                {/* Registration Type Selection (Check / Toggle) */}
                <div className="field" style={{ gridColumn: "1 / -1" }}>
                    <label style={{ fontWeight: 600, marginBottom: "0.5rem", display: "block" }}>
                        Registration Type (FBR Tax Status) *
                    </label>
                    <div style={{ display: "flex", gap: "2rem", alignItems: "center", padding: "0.5rem 0" }}>
                        <label style={{ display: "inline-flex", alignItems: "center", gap: "0.5rem", cursor: "pointer", fontWeight: 500 }}>
                            <input
                                type="radio"
                                name="registration_type"
                                value="Registered"
                                checked={isRegistered}
                                onChange={() => {
                                    change("registration_type", "Registered");
                                    setVerification(undefined);
                                    setVerifiedNumber("");
                                }}
                            />
                            <span>Registered Buyer (Active Sales Taxpayer)</span>
                        </label>
                        <label style={{ display: "inline-flex", alignItems: "center", gap: "0.5rem", cursor: "pointer", fontWeight: 500 }}>
                            <input
                                type="radio"
                                name="registration_type"
                                value="Unregistered"
                                checked={!isRegistered}
                                onChange={() => {
                                    change("registration_type", "Unregistered");
                                    setVerification(undefined);
                                    setVerifiedNumber("");
                                }}
                            />
                            <span>Unregistered Buyer (Consumer / Walk-in)</span>
                        </label>
                    </div>
                    <small className="field-hint">
                        {isRegistered
                            ? "Registered buyers require a valid 7 or 8-digit NTN (Used for Scenario SN001)."
                            : "Unregistered buyers do not require NTN or CNIC (13-digit CNIC optional; used for Scenario SN002)."}
                    </small>
                </div>

                <Input label="Business / customer name" required maxLength={255} {...field("name")} />
                <Input label="Customer code" required={!!customer} maxLength={50} pattern={"[A-Za-z0-9_\\-]+"} placeholder="Generated automatically if left blank" {...field("code")} />

                {/* If Registered: Show NTN (Required) */}
                {isRegistered && (
                    <div className="field">
                        <label htmlFor="customer-ntn">Buyer NTN *</label>
                        <input
                            id="customer-ntn"
                            name="ntn"
                            value={draft.ntn || ""}
                            inputMode="numeric"
                            pattern="[0-9]{7,8}"
                            maxLength={8}
                            required
                            placeholder="7 or 8-digit NTN (e.g. 0711153)"
                            aria-invalid={!!errors.ntn || !!errors.ntn_cnic}
                            onChange={event => change("ntn", event.target.value)}
                        />
                        <small className={errors.ntn || errors.ntn_cnic ? "field-error" : "field-hint"}>
                            {errors.ntn?.[0] || errors.ntn_cnic?.[0] || "7 or 8 digits without dashes. Required for FBR Registered Buyer invoices."}
                        </small>
                        {verification && verifiedNumber === ntnValue && (
                            <div className="customer-verification-status">
                                <Badge tone={verification.active ? "success" : "warning"}>
                                    {verification.is_offline ? "FBR Offline (Unverified)" : verification.active ? "Taxpayer Verified: Active" : "Unregistered Buyer"}
                                </Badge>
                                <span>{verification.is_offline ? "Gateway offline" : verification.active ? verification.registration_type : "Registration not active"} · FBR Check</span>
                            </div>
                        )}
                    </div>
                )}

                {/* CNIC Field: If Registered, optional. If Unregistered, required and NTN is hidden! */}
                <div className="field">
                    <label htmlFor="customer-cnic">Buyer CNIC (Optional)</label>
                    <input
                        id="customer-cnic"
                        name="cnic"
                        value={draft.cnic || ""}
                        inputMode="numeric"
                        pattern="[0-9]{13}"
                        maxLength={13}
                        required={false}
                        placeholder="13-digit CNIC without dashes (e.g. 4210112345671)"
                        aria-invalid={!!errors.cnic}
                        onChange={event => change("cnic", event.target.value)}
                    />
                    <small className={errors.cnic ? "field-error" : "field-hint"}>
                        {errors.cnic?.[0] || "Optional 13-digit CNIC without dashes."}
                    </small>
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
                {isRegistered && (
                    <Button type="button" variant="secondary" disabled={!validNtn || verifying || busy || isVerified} onClick={verifyBuyer}>
                        {verifying ? "Checking FBR…" : isVerified ? "Verified with FBR ✓" : "Verify NTN with FBR"}
                    </Button>
                )}
                <Button type="submit" disabled={!canSave}>{busy ? "Saving…" : customer ? "Update Customer" : "Save Customer"}</Button>
            </FormActions>
        </fieldset>
    </form></Card>;
}
