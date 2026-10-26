import { useRef, useState } from "react";
import type { FormEvent } from "react";
import { Modal } from "../../components/Modal";
import { Button, Notice, PasswordField } from "../../components/ui";
import { ApiError } from "../../lib/api";
import type { FieldErrors } from "../../lib/api";
import { authApi } from "./authApi";

export function ChangePasswordModal({ onClose, onSaved }: { onClose: () => void; onSaved: (message: string) => void }) {
    const [values, setValues] = useState({ current_password: "", password: "", password_confirmation: "" });
    const [errors, setErrors] = useState<FieldErrors>({});
    const [error, setError] = useState("");
    const [busy, setBusy] = useState(false);
    const lock = useRef(false);
    function field(key: keyof typeof values) {
        return { value: values[key], onChange: (event: React.ChangeEvent<HTMLInputElement>) => setValues(current => ({ ...current, [key]: event.target.value })), error: errors[key]?.[0], disabled: busy, required: true, maxLength: 128 };
    }
    async function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (lock.current) return;
        setErrors({}); setError("");
        if (values.password !== values.password_confirmation) { setErrors({ password_confirmation: ["Passwords do not match."] }); return; }
        lock.current = true; setBusy(true);
        try { const response = await authApi.changePassword(values); setValues({ current_password: "", password: "", password_confirmation: "" }); onSaved(response.message); }
        catch (failure) { if (failure instanceof ApiError) { setErrors(failure.errors); setError(Object.keys(failure.errors).length ? "Please check the highlighted fields." : failure.message); } else { setError("Could not change your password. Please try again."); } }
        finally { lock.current = false; setBusy(false); }
    }
    return <Modal title="Change login password" busy={busy} onClose={onClose} footer={<><Button variant="secondary" disabled={busy} onClick={onClose}>Cancel</Button><Button type="submit" form="change-login-password" disabled={busy}>{busy ? "Changing…" : "Change password"}</Button></>}>
        <p>Update the password for your ERP login account.</p>
        {error && <Notice tone="warning">{error}</Notice>}
        <form id="change-login-password" onSubmit={event => { void submit(event); }} className="form-grid">
            <PasswordField label="Current password" autoComplete="current-password" autoFocus wide {...field("current_password")} />
            <PasswordField label="New password" autoComplete="new-password" minLength={8} hint="Use at least 8 characters." wide {...field("password")} />
            <PasswordField label="Confirm new password" autoComplete="new-password" minLength={8} wide {...field("password_confirmation")} />
        </form>
    </Modal>;
}
