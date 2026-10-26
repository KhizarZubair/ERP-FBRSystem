import { Card } from "../../components/ui";
import type { CompanyDetails } from "./companyApi";
export function CompanyDetailsView({company}:{company:CompanyDetails}) {
 const details=[["Registered business name",company.name],["Seller NTN",company.ntn_cnic],["Seller CNIC",company.seller_cnic],["Province",company.province],["Registered address",company.address],["Contact person",company.contact_person],["Company email",company.email],["Phone",company.phone]];
 return <Card title="Company profile"><dl className="company-details">{details.map(([label,value])=><div key={label}><dt>{label}</dt><dd>{value||"Not provided"}</dd></div>)}</dl></Card>;
}
