import { useEffect, useState } from "react";
import { Button, Card, CheckboxGroup, FormActions, Input, Notice, PageHeader, Select, Textarea } from "../../components/ui";
import { provinces } from "../../data/demo";
import { ApiError } from "../../lib/api";
import { navigate } from "../../lib/navigation";
import { useAuth } from "../auth/AuthContext";
import { companyApi } from "./companyApi";
import { CompanyForm } from "./CompanyForm";
import { CompanyDetailsView } from "./CompanyDetailsView";
import { FbrIntegrationPanel } from "./FbrIntegrationPanel";
import { FbrGuidanceModal } from "./FbrGuidanceModal";
import { ChangePasswordModal } from "../auth/ChangePasswordModal";
import type { CompanyDetails } from "./companyApi";

const businessActivities = ["Manufacturer", "Importer", "Distributor", "Wholesaler", "Retailer", "Exporter", "Service Provider", "Other"];
const businessSectors = ["Potassium Chlorate", "Mobile", "Pharmaceuticals", "Automobile", "Gas Distribution", "Petroleum", "Textile", "Steel", "Cement or Concrete Blocks", "Wholesale / Retails", "CNG Stations", "Services", "Electricity Distribution", "Telecom", "FMCG", "All Other Sectors"];

export function CompanyPage() {
    const { activeCompany, refresh } = useAuth();

    if (!activeCompany) {
        return <FirstCompanySetup onCreated={async () => {
            await refresh();
            navigate("/company");
        }} />;
    }

    return <ExistingCompany companyId={activeCompany.id} onRefresh={refresh} />;
}

function FirstCompanySetup({ onCreated }: { onCreated: () => Promise<void> }) {
    const [error, setError] = useState("");
    const [busy, setBusy] = useState(false);
    const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
    const [businessActivity, setBusinessActivity] = useState<string[]>([]);
    const [sector, setSector] = useState<string[]>([]);
    const [isSalesTaxProfileRetailer, setIsSalesTaxProfileRetailer] = useState(false);
    return <>
        <PageHeader title="Company & FBR Setup" description="Complete your single company profile to start using the ERP." />
        <form onSubmit={async event => {
            event.preventDefault();
            if (busy) return;
            setBusy(true); setError(""); setFieldErrors({});
            const form = new FormData(event.currentTarget);
            const value = (key: string) => String(form.get(key) ?? "").trim();
            const sellerNtn = value("seller_ntn");
            const sellerCnic = value("seller_cnic");
            if (!sellerNtn && !sellerCnic) {
                setError("Please provide either Seller NTN (7 or 8 digits) or Seller CNIC (13 digits).");
                setFieldErrors({
                    seller_ntn: ["Either NTN or CNIC is required."],
                    seller_cnic: ["Either CNIC or NTN is required."],
                });
                setBusy(false);
                return;
            }
            if (!businessActivity.length || !sector.length) {
                setError("Please select at least one Business Nature and one Sector.");
                setFieldErrors({
                    business_activity: businessActivity.length ? [] : ["Select at least one business nature."],
                    sector: sector.length ? [] : ["Select at least one sector."],
                });
                setBusy(false);
                return;
            }
            try {
                await companyApi.create({
                    name: value("name"),
                    seller_ntn: sellerNtn || undefined,
                    seller_cnic: sellerCnic || undefined,
                    province: value("province"),
                    address: value("address"),
                    email: value("email"),
                    phone: value("phone"),
                    business_activity: businessActivity,
                    sector,
                    is_sales_tax_profile_retailer: isSalesTaxProfileRetailer,
                });
                await onCreated();
            } catch (failure) {
                setError(failure instanceof Error ? failure.message : "Could not create company profile.");
                if (failure instanceof ApiError) setFieldErrors(failure.errors);
            } finally { setBusy(false); }
        }}>
            <Card title="Registered seller details">
                {error && <Notice tone="warning">{error}</Notice>}
                <div className="form-grid">
                    <Input label="Registered business name" name="name" required maxLength={255} error={fieldErrors.name?.[0]} />
                    <Input label="Seller NTN" name="seller_ntn" placeholder="e.g. 1234567" inputMode="numeric" pattern="[0-9]{7,8}" minLength={7} maxLength={8} error={fieldErrors.seller_ntn?.[0]} hint="7 or 8 digits (Required if CNIC is not provided)" />
                    <Input label="Seller CNIC" name="seller_cnic" placeholder="13 digits without dashes (e.g. 3520112345671)" inputMode="numeric" pattern="[0-9]{13}" minLength={13} maxLength={13} error={fieldErrors.seller_cnic?.[0]} hint="13 digits without dashes (Required if NTN is not provided)" />
                    <Select label="Province" name="province" required options={[{ value: "", label: "Select province" }, ...provinces]} error={fieldErrors.province?.[0]} />
                    <CheckboxGroup label="FBR Business Nature / Activity" required options={businessActivities} value={businessActivity} onChange={setBusinessActivity} error={fieldErrors.business_activity?.[0]} hint="Select all business activities registered for this company." />
                    <CheckboxGroup label="FBR Business Sector" required options={businessSectors} value={sector} onChange={setSector} error={fieldErrors.sector?.[0]} hint="Select every sector applicable to this company." />
                    <Input label="Registered as retailer in FBR Sales Tax Profile" type="checkbox" checked={isSalesTaxProfileRetailer} onChange={event => setIsSalesTaxProfileRetailer(event.target.checked)} hint="Enable only if FBR Sales Tax Profile officially shows Retailer. This controls scenarios SN026, SN027 and SN028." />
                    <Input label="Company email" name="email" type="email" required maxLength={255} error={fieldErrors.email?.[0]} />
                    <Input label="Phone" name="phone" maxLength={30} error={fieldErrors.phone?.[0]} />
                    <Textarea label="Registered address" name="address" required maxLength={2000} error={fieldErrors.address?.[0]} />
                </div>
                <FormActions><Button type="submit" disabled={busy}>{busy ? "Saving…" : "Save Company Profile"}</Button></FormActions>
            </Card>
        </form>
    </>;
}

function ExistingCompany({ companyId, onRefresh }: { companyId: number; onRefresh: () => Promise<void> }) {
    const [showPassword, setShowPassword] = useState(false);
    const [passwordMessage, setPasswordMessage] = useState("");
    const [showFbrGuidance, setShowFbrGuidance] = useState(false);
    const [company, setCompany] = useState<CompanyDetails | null>(null);
    const [editing, setEditing] = useState(false);
    const [error, setError] = useState("");
    const [saved, setSaved] = useState(false);
    const load = async () => {
        setError("");
        try { setCompany((await companyApi.get(companyId)).data); }
        catch (failure) { setError(failure instanceof Error ? failure.message : "Could not load company details."); }
    };
    useEffect(() => { void load(); }, [companyId]);
    function updated(data: CompanyDetails) {
        void onRefresh();
        setCompany(data); setEditing(false); setSaved(true);
    }
    return <>
        <PageHeader title="Company & FBR Setup" description="Manage your registered business profile and FBR integration settings."
            actions={company ? <>
                <Button variant="secondary" onClick={() => setShowPassword(true)}>Change login password</Button>
                <Button variant="secondary" onClick={() => setShowFbrGuidance(true)}>FBR Credential Details</Button>
                {company.can_edit && !editing && <Button onClick={() => { setSaved(false); setEditing(true); }}>Edit company details</Button>}
            </> : undefined} />
        {passwordMessage && <Notice tone="success">{passwordMessage}</Notice>}
        {showPassword && <ChangePasswordModal onClose={() => setShowPassword(false)} onSaved={message => { setPasswordMessage(message); setShowPassword(false); }} />}
        {saved && <Notice tone="success">Company details saved successfully.</Notice>}
        {error ? <Notice tone="warning">{error} <Button variant="quiet" onClick={() => { void load(); }}>Try again</Button></Notice>
            : !company ? <p role="status">Loading company details…</p>
            : editing ? <CompanyForm company={company} onSaved={updated} onCancel={() => setEditing(false)} />
            : <><CompanyDetailsView company={company} /><FbrIntegrationPanel /></>}
        {showFbrGuidance && <FbrGuidanceModal onClose={() => setShowFbrGuidance(false)} />}
    </>;
}
