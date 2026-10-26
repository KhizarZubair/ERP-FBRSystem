import { useEffect, useState } from "react";
import { Button, Card, Input, Notice, Select } from "../../components/ui";
import { failureMessage } from "../../lib/useApiQuery";
import { useAuth } from "../auth/AuthContext";
import { companyApi } from "./companyApi";
import type { CompanyMember } from "./companyApi";

export function CompanyManagement({ companyId, canEdit }: { companyId: number; canEdit: boolean }) {
    const { refresh, selectCompany } = useAuth();
    const [members, setMembers] = useState<CompanyMember[]>([]);
    const [email, setEmail] = useState(""); const [role, setRole] = useState<"owner" | "member">("member");
    const [error, setError] = useState(""); const [message, setMessage] = useState(""); const [busy, setBusy] = useState(false);
    const [company, setCompany] = useState({ name: "", seller_ntn: "", seller_cnic: "", province: "", address: "", email: "", phone: "" });
    useEffect(() => {
        if (!canEdit) return;
        companyApi.members(companyId).then(result => setMembers(result.data)).catch(failure => setError(failureMessage(failure)));
    }, [companyId, canEdit]);
    async function addMember() {
        setBusy(true); setError(""); setMessage("");
        try { const result = await companyApi.addMember(companyId, email, role); setMembers(current => [...current.filter(row => row.id !== result.data.id), result.data]); setEmail(""); setMessage("Member added."); }
        catch (failure) { setError(failureMessage(failure)); } finally { setBusy(false); }
    }
    async function updateMember(member: CompanyMember, data: Partial<Pick<CompanyMember, "role" | "is_active">>) {
        setBusy(true); setError("");
        try { const result = await companyApi.updateMember(companyId, member.id, { role: data.role ?? member.role, is_active: data.is_active ?? member.is_active }); setMembers(current => current.map(row => row.id === member.id ? result.data : row)); await refresh(); }
        catch (failure) { setError(failureMessage(failure)); } finally { setBusy(false); }
    }
    async function removeMember(member: CompanyMember) {
        setBusy(true); setError("");
        try { await companyApi.removeMember(companyId, member.id); setMembers(current => current.filter(row => row.id !== member.id)); setMessage("Member removed."); }
        catch (failure) { setError(failureMessage(failure)); } finally { setBusy(false); }
    }
    async function createCompany() {
        setBusy(true); setError(""); setMessage("");
        try { const result = await companyApi.create(company); await refresh(); selectCompany(result.data.id); setMessage("Company created. Select it from the workspace menu."); setCompany({ name: "", seller_ntn: "", seller_cnic: "", province: "", address: "", email: "", phone: "" }); }
        catch (failure) { setError(failureMessage(failure)); } finally { setBusy(false); }
    }
    if (!canEdit) return null;
    return <>
        {error && <Notice tone="warning">{error}</Notice>}{message && <Notice tone="success">{message}</Notice>}
        <Card title="Company members"><div className="form-grid form-grid--three">
            <Input label="Registered user email" type="email" value={email} onChange={event => setEmail(event.target.value)} />
            <Select label="Role" value={role} options={[{ value: "member", label: "Member" }, { value: "owner", label: "Owner" }]} onChange={event => setRole(event.target.value as "owner" | "member")} />
            <Button disabled={busy || !email} onClick={() => { void addMember(); }}>Add member</Button>
        </div><div className="section-gap">{members.map(member => <div className="actions" key={member.id}><strong>{member.name}</strong><span>{member.email}</span>
            <Select label="Role" value={member.role} options={[{ value: "member", label: "Member" }, { value: "owner", label: "Owner" }]} disabled={busy} onChange={event => { void updateMember(member, { role: event.target.value as "owner" | "member" }); }} />
            <Button variant="secondary" disabled={busy} onClick={() => { void updateMember(member, { is_active: !member.is_active }); }}>{member.is_active ? "Deactivate" : "Activate"}</Button>
            <Button variant="danger" disabled={busy} onClick={() => { void removeMember(member); }}>Remove</Button>
        </div>)}</div></Card>
        <Card title="Add another company"><div className="form-grid form-grid--three">
            <Input label="Registered company name" required value={company.name} onChange={event => setCompany(current => ({ ...current, name: event.target.value }))} />
            <Input label="Seller NTN *" required placeholder="e.g. 1234567" inputMode="numeric" pattern="[0-9]{7,8}" minLength={7} maxLength={8} value={company.seller_ntn} onChange={event => setCompany(current => ({ ...current, seller_ntn: event.target.value }))} />
            <Input label="Seller CNIC *" required placeholder="13 digits without dashes (e.g. 3520112345671)" inputMode="numeric" pattern="[0-9]{13}" minLength={13} maxLength={13} value={company.seller_cnic} onChange={event => setCompany(current => ({ ...current, seller_cnic: event.target.value }))} />
            <Select label="Province" required value={company.province} options={[{ value: "", label: "Select province" }, ...["Punjab", "Sindh", "Khyber Pakhtunkhwa", "Balochistan", "Islamabad Capital Territory", "Gilgit-Baltistan", "Azad Jammu and Kashmir"].map(value => ({ value, label: value }))]} onChange={event => setCompany(current => ({ ...current, province: event.target.value }))} />
            <Input label="Registered address" required value={company.address} onChange={event => setCompany(current => ({ ...current, address: event.target.value }))} />
            <Input label="Company email" required type="email" value={company.email} onChange={event => setCompany(current => ({ ...current, email: event.target.value }))} />
            <Input label="Phone" value={company.phone} onChange={event => setCompany(current => ({ ...current, phone: event.target.value }))} />
        </div><Button disabled={busy || !company.name || !company.seller_ntn || !company.seller_cnic || !company.province || !company.address || !company.email} onClick={() => { void createCompany(); }}>Create company</Button></Card>
    </>;
}
