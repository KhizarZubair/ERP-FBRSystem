import { useRef, useState } from "react";
import type { FormEvent } from "react";
import { Modal } from "../../components/Modal";
import { Button, Notice, PasswordField } from "../../components/ui";
import { ApiError } from "../../lib/api";
import type { FieldErrors } from "../../lib/api";
import { companyApi } from "./companyApi";
import type { CompanyDetails } from "./companyApi";

export function ChangeClientPasswordModal({
    company,
    onClose,
    onSaved,
}: {
    company: CompanyDetails;
    onClose: () => void;
    onSaved: (message: string) => void;
}) {
    const [values, setValues] = useState({ password: "", password_confirmation: "" });
    const [errors, setErrors] = useState<FieldErrors>({});
    const [error, setError] = useState("");
    const [busy, setBusy] = useState(false);
    const lock = useRef(false);

    const clientEmail = company.client_user?.email || company.email || "client account";

    function field(key: keyof typeof values) {
        return {
            value: values[key],
            onChange: (event: React.ChangeEvent<HTMLInputElement>) =>
                setValues(current => ({ ...current, [key]: event.target.value })),
            error: errors[key]?.[0],
            disabled: busy,
            required: true,
            maxLength: 128,
        };
    }

    async function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (lock.current) return;
        setErrors({});
        setError("");

        if (values.password !== values.password_confirmation) {
            setErrors({ password_confirmation: ["Passwords do not match."] });
            return;
        }

        lock.current = true;
        setBusy(true);

        try {
            const response = await companyApi.updateClientPassword(company.id, values);
            setValues({ password: "", password_confirmation: "" });
            onSaved(response.message);
        } catch (failure) {
            if (failure instanceof ApiError) {
                setErrors(failure.errors);
                setError(Object.keys(failure.errors).length ? "Please check the highlighted fields." : failure.message);
            } else {
                setError("Could not change client password. Please try again.");
            }
        } finally {
            lock.current = false;
            setBusy(false);
        }
    }

    return (
        <Modal
            title="Change Client Login Password"
            busy={busy}
            onClose={onClose}
            footer={
                <>
                    <Button variant="secondary" disabled={busy} onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" form="change-client-password" disabled={busy}>
                        {busy ? "Saving…" : "Save Client Password"}
                    </Button>
                </>
            }
        >
            <div style={{ marginBottom: "1rem" }}>
                <p style={{ margin: "0 0 0.5rem 0", fontSize: "0.9rem" }}>
                    Update the login password for <strong>{company.name}</strong> representative user:
                </p>
                <div style={{ padding: "8px 12px", background: "#f1f5f9", borderRadius: "6px", fontSize: "0.85rem", color: "#334155" }}>
                    <strong>Client Account:</strong> {clientEmail}
                    <div style={{ fontSize: "0.8rem", color: "#64748b", marginTop: "2px" }}>
                        Note: This changes the login credentials for this client company. Your Super Admin password will not be changed.
                    </div>
                </div>
            </div>

            {error && <Notice tone="warning">{error}</Notice>}

            <form id="change-client-password" onSubmit={event => { void submit(event); }} className="form-grid">
                <PasswordField
                    label="New client password"
                    autoComplete="new-password"
                    autoFocus
                    minLength={8}
                    hint="Use at least 8 characters."
                    wide
                    {...field("password")}
                />
                <PasswordField
                    label="Confirm new client password"
                    autoComplete="new-password"
                    minLength={8}
                    wide
                    {...field("password_confirmation")}
                />
            </form>
        </Modal>
    );
}
