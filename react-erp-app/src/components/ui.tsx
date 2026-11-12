import { useId, useState } from "react";
import type {
    ButtonHTMLAttributes,
    InputHTMLAttributes,
    ReactNode,
    SelectHTMLAttributes,
    TextareaHTMLAttributes,
} from "react";

export function Button({
    variant = "primary",
    className = "",
    type = "button",
    ...props
}: ButtonHTMLAttributes<HTMLButtonElement> & {
    variant?: "primary" | "secondary" | "quiet" | "danger";
}) {
    return (
        <button
            type={type}
            className={`button button--${variant} ${className}`}
            {...props}
        />
    );
}
export function Card({
    title,
    children,
    actions,
    className = "",
}: {
    title?: string;
    children: ReactNode;
    actions?: ReactNode;
    className?: string;
}) {
    return (
        <section className={`card ${className}`}>
            {(title || actions) && (
                <div className="card-heading">
                    {title && <h2>{title}</h2>}
                    {actions}
                </div>
            )}
            {children}
        </section>
    );
}
export function PageHeader({
    title,
    description,
    actions,
}: {
    title: string;
    description: string;
    actions?: ReactNode;
}) {
    return (
        <header className="page-header">
            <div>
                <h1>{title}</h1>
                <p>{description}</p>
            </div>
            {actions && <div className="actions">{actions}</div>}
        </header>
    );
}
export function Badge({
    children,
    tone = "neutral",
}: {
    children: ReactNode;
    tone?: "neutral" | "success" | "warning" | "danger";
}) {
    return <span className={`badge badge--${tone}`}>{children}</span>;
}
export function Notice({
    children,
    tone = "info",
}: {
    children: ReactNode;
    tone?: "info" | "success" | "warning";
}) {
    return (
        <div className={`notice notice--${tone}`} role="status">
            {children}
        </div>
    );
}
type FieldBase = {
    label: string;
    hint?: string;
    error?: string;
    wide?: boolean;
};
function FieldShell({
    id,
    label,
    hint,
    error,
    required,
    wide,
    children,
}: FieldBase & { id: string; required?: boolean; children: ReactNode }) {
    return (
        <div className={`field ${wide ? "field--wide" : ""}`}>
            <label htmlFor={id}>
                {label}
                {required && <span className="required"> *</span>}
            </label>
            {children}
            {(error || hint) && (
                <small
                    id={`${id}-help`}
                    className={error ? "field-error" : "field-hint"}
                >
                    {error || hint}
                </small>
            )}
        </div>
    );
}
export function Input({
    label,
    hint,
    error,
    wide,
    id: suppliedId,
    ...props
}: FieldBase & InputHTMLAttributes<HTMLInputElement>) {
    const generated = useId();
    const id = suppliedId || generated;
    return (
        <FieldShell
            {...{ id, label, hint, error, wide }}
            required={props.required}
        >
            <input
                {...props}
                step={props.type === "number" ? "any" : props.step}
                id={id}
                aria-invalid={!!error}
                aria-describedby={hint || error ? `${id}-help` : undefined}
            />
        </FieldShell>
    );
}
export function Select({
    label,
    hint,
    error,
    wide,
    options,
    id: suppliedId,
    ...props
}: FieldBase &
    SelectHTMLAttributes<HTMLSelectElement> & {
        options: readonly (string | { value: string; label: string })[];
    }) {
    const generated = useId();
    const id = suppliedId || generated;
    return (
        <FieldShell
            {...{ id, label, hint, error, wide }}
            required={props.required}
        >
            <select
                {...props}
                id={id}
                aria-invalid={!!error}
                aria-describedby={hint || error ? `${id}-help` : undefined}
            >
                {options.map((option) => {
                    const value =
                        typeof option === "string" ? option : option.value;
                    return (
                        <option key={value} value={value}>
                            {typeof option === "string" ? option : option.label}
                        </option>
                    );
                })}
            </select>
        </FieldShell>
    );
}
export function CheckboxGroup({
    label,
    options,
    value,
    onChange,
    hint,
    error,
    required = false,
}: {
    label: string;
    options: readonly string[];
    value: string[];
    onChange: (value: string[]) => void;
    hint?: string;
    error?: string;
    required?: boolean;
}) {
    const id = useId();
    return (
        <fieldset className="checkbox-group" aria-describedby={hint || error ? `${id}-help` : undefined}>
            <legend>{label}{required && <span className="required"> *</span>}</legend>
            <div className="checkbox-grid">
                {options.map(option => (
                    <label className="checkbox-option" key={option}>
                        <input
                            type="checkbox"
                            checked={value.includes(option)}
                            onChange={event => onChange(
                                event.target.checked
                                    ? [...value, option]
                                    : value.filter(item => item !== option)
                            )}
                        />
                        <span>{option}</span>
                    </label>
                ))}
            </div>
            {(error || hint) && <small id={`${id}-help`} className={error ? "field-error" : "field-hint"}>{error || hint}</small>}
        </fieldset>
    );
}
export function Textarea({
    label,
    hint,
    error,
    wide = true,
    id: suppliedId,
    ...props
}: FieldBase & TextareaHTMLAttributes<HTMLTextAreaElement>) {
    const generated = useId();
    const id = suppliedId || generated;
    return (
        <FieldShell
            {...{ id, label, hint, error, wide }}
            required={props.required}
        >
            <textarea
                {...props}
                id={id}
                aria-invalid={!!error}
                aria-describedby={hint || error ? `${id}-help` : undefined}
            />
        </FieldShell>
    );
}
export function PasswordField(
    props: FieldBase & InputHTMLAttributes<HTMLInputElement>,
) {
    const [visible, setVisible] = useState(false);
    return (
        <div className={`password-field ${props.wide ? "field--wide" : ""}`}>
            <Input {...props} type={visible ? "text" : "password"} />
            <button
                className="password-toggle"
                disabled={props.disabled}
                type="button"
                onClick={() => setVisible(!visible)}
                aria-label={`${visible ? "Hide" : "Show"} ${props.label.toLowerCase()}`}
                aria-pressed={visible}
            >
                {visible ? "Hide" : "Show"}
            </button>
        </div>
    );
}
export function FormActions({ children }: { children: ReactNode }) {
    return <div className="form-actions">{children}</div>;
}

export function RefreshButton({ label = "Refresh", className = "", ...props }: Omit<ButtonHTMLAttributes<HTMLButtonElement>, "children"> & { label?: string; variant?: "primary" | "secondary" | "quiet" | "danger" }) {
    return <Button {...props} variant="secondary" className={`refresh-button ${className}`} aria-label={label} title={label}>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M20 7v5h-5" /><path d="M4 17v-5h5" /><path d="M6.1 7a7 7 0 0 1 11.5-1L20 9M4 15l2.4 3A7 7 0 0 0 17.9 17" /></svg>
    </Button>;
}
export function SearchField({ label, ...props }: Omit<FieldBase & InputHTMLAttributes<HTMLInputElement>, "type" | "placeholder">) {
    return <div className="list-search"><Input {...props} label={label} type="search" placeholder="Search" /></div>;
}
