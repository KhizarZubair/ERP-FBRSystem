import { useEffect, useRef, useState } from "react";
import { AuthLayout } from "../../layout/AppLayout";
import { Button, Card, Input, Notice, PageHeader, PasswordField, FormActions } from "../../components/ui";
import { navigate } from "../../lib/navigation";
import { ApiError } from "../../lib/api";
import type { FieldErrors } from "../../lib/api";
import { useAuth } from "./AuthContext";
import { authApi } from "./authApi";

export function AuthPage({ register = false }: { register?: boolean }) {
    const auth = useAuth();
    const [message, setMessage] = useState("");
    const [errors, setErrors] = useState<FieldErrors>({});
    const [busy, setBusy] = useState(false);
    const [registrationAllowed, setRegistrationAllowed] = useState<boolean | null>(null);
    const submitting = useRef(false);
    const fieldError = (name: string) => errors[name]?.[0];

    useEffect(() => {
        let active = true;
        void authApi.registrationStatus().then(result => {
            if (active) setRegistrationAllowed(result.data.can_register);
        }).catch(() => {
            if (active) setRegistrationAllowed(false);
        });
        return () => { active = false; };
    }, []);

    useEffect(() => {
        if (register && registrationAllowed === false) navigate("/login");
    }, [register, registrationAllowed]);

    const registrationDisabled = register && registrationAllowed === false;
    return (
        <AuthLayout>
            <PageHeader title={register ? "Create master account" : "Welcome back"}
                description={register ? "Create the first system user. Company details are configured after login." : "Sign in to manage your company, customers, items and invoices."} />
            <Card title={register ? "Master account details" : "Account login"}>
                {(message || auth.error || registrationDisabled) && <Notice tone="warning">{registrationDisabled ? "Single-user installation active. Please login with your master credentials." : message || auth.error}</Notice>}
                {!registrationDisabled && <form onSubmit={async event => {
                    event.preventDefault();
                    if (submitting.current) return;
                    submitting.current = true; setBusy(true); setMessage(""); setErrors({});
                    const data = new FormData(event.currentTarget);
                    const text = (key: string) => String(data.get(key) || "");
                    try {
                        if (register) {
                            await auth.register({ name: text("name"), email: text("email"), phone: text("phone"), password: text("password"), password_confirmation: text("password_confirmation") });
                        } else {
                            await auth.login({ email: text("email"), password: text("password") });
                        }
                        navigate("/overview");
                    } catch (failure) {
                        if (failure instanceof ApiError) {
                            setErrors(failure.errors);
                            setMessage(failure.status === 429 ? "Too many attempts. Please wait before trying again." : failure.message);
                            if (failure.status === 403 && register) setRegistrationAllowed(false);
                        } else setMessage(failure instanceof Error ? failure.message : "Could not complete the request.");
                    } finally { submitting.current = false; setBusy(false); }
                }}>
                    <fieldset className="auth-fields" disabled={busy || (register && registrationAllowed === null)}>
                        <div className="form-grid">
                            {register && <Input label="Master administrator name" name="name" required maxLength={255} autoComplete="name" placeholder="Full name" error={fieldError("name")} />}
                            {register && <Input label="Phone" name="phone" type="tel" required maxLength={30} autoComplete="tel" placeholder="03xx xxxxxxx" error={fieldError("phone")} />}
                            <Input label="Email" name="email" type="email" required maxLength={255} autoComplete="email" placeholder="you@company.com" wide={!register} error={fieldError("email")} />
                            <PasswordField label="Password" wide={!register} name="password" required minLength={register ? 8 : undefined} maxLength={128}
                                autoComplete={register ? "new-password" : "current-password"} placeholder={register ? "At least 8 characters" : "Enter password"} error={fieldError("password")} />
                            {register && <PasswordField label="Confirm password" name="password_confirmation" required maxLength={128} autoComplete="new-password" placeholder="Repeat password" error={fieldError("password_confirmation")} />}
                        </div>
                        <FormActions><Button type="submit" disabled={busy || (register && registrationAllowed === null)}>{busy ? "Please wait…" : register ? "Create master account" : "Sign in"}</Button></FormActions>
                    </fieldset>
                </form>}
                <p className="auth-link">{register ? "Already have an account?" : "First installation?"} <a href={register ? "#/login" : "#/register"}>{register ? "Sign in" : "Set up master account"}</a></p>
            </Card>
        </AuthLayout>
    );
}
