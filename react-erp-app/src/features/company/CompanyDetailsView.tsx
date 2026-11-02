import { Card } from "../../components/ui";
import type { CompanyDetails } from "./companyApi";

export function CompanyDetailsView({ company }: { company: CompanyDetails }) {
    const details: [string, string | null | undefined, string?][] = [
        ["Registered business name", company.name],
        ["Seller NTN", company.ntn_cnic],
        ["Seller CNIC", company.seller_cnic],
        ["FBR Business Nature", company.business_activity, "Required for FBR digital invoicing"],
        ["FBR Business Sector", company.sector, "Required for FBR digital invoicing"],
        ["Province", company.province],
        ["Registered address", company.address],
        ["Contact person", company.contact_person],
        ["Company email", company.email],
        ["Phone", company.phone],
    ];

    return (
        <Card title="Company profile">
            <dl className="company-details">
                {details.map(([label, value, hint]) => (
                    <div key={label}>
                        <dt>{label}</dt>
                        <dd>
                            {value ? (
                                <strong>{value}</strong>
                            ) : (
                                <span className="field-hint" style={{ color: "var(--color-warning, #d97706)", fontWeight: 500 }}>
                                    ⚠️ {hint || "Not provided"}
                                </span>
                            )}
                        </dd>
                    </div>
                ))}
            </dl>
        </Card>
    );
}
