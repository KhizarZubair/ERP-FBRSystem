import { Card } from "../../components/ui";
import type { CompanyDetails } from "./companyApi";

export function CompanyDetailsView({ company }: { company: CompanyDetails }) {
    const sellerNtn = company.seller_ntn || (company.ntn_cnic && company.ntn_cnic.length <= 8 ? company.ntn_cnic : null);
    const sellerCnic = company.seller_cnic || (company.ntn_cnic && company.ntn_cnic.length === 13 ? company.ntn_cnic : null);

    const details: [string, string | null | undefined, string?, boolean?][] = [
        ["Registered business name", company.name, undefined, true],
        ["Seller NTN", sellerNtn, sellerCnic ? "Optional (CNIC provided)" : "Required if CNIC not provided", !sellerCnic],
        ["Seller CNIC", sellerCnic, sellerNtn ? "Optional (NTN provided)" : "Required if NTN not provided", !sellerNtn],
        ["FBR Business Nature", company.business_activity, "Required for FBR digital invoicing", true],
        ["FBR Business Sector", company.sector, "Required for FBR digital invoicing", true],
        ["Province", company.province, undefined, true],
        ["Registered address", company.address, undefined, true],
        ["Contact person", company.contact_person],
        ["Company email", company.email, undefined, true],
        ["Phone", company.phone],
    ];

    return (
        <Card title="Company profile">
            <dl className="company-details">
                {details.map(([label, value, hint, isMandatory]) => (
                    <div key={label}>
                        <dt>{label}</dt>
                        <dd>
                            {value ? (
                                <strong>{value}</strong>
                            ) : isMandatory ? (
                                <span className="field-hint" style={{ color: "var(--color-warning, #d97706)", fontWeight: 500 }}>
                                    ⚠️ {hint || "Not provided"}
                                </span>
                            ) : (
                                <span className="field-hint" style={{ color: "var(--color-text-muted, #6b7280)" }}>
                                    {hint || "—"}
                                </span>
                            )}
                        </dd>
                    </div>
                ))}
            </dl>
        </Card>
    );
}
