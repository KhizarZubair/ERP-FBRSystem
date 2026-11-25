import { api } from "../../lib/api";
export type CompanyDetails = {
 id:number; name:string; ntn_cnic:string|null; seller_ntn?:string|null; seller_cnic:string|null; province:string|null; address:string|null;
 contact_person:string|null; email:string|null; phone:string|null;
 invoice_series:string; currency:string; fbr_environment:"sandbox"|"production"; can_edit:boolean;
 business_activity:string[]; sector:string[]; is_sales_tax_profile_retailer:boolean; allowed_sandbox_scenarios:string[]; certified_sandbox_scenarios?:string[];
 supported_scenarios:Record<string,{description:string;sale_type:string;buyer_registration_type:string}>;
 business_activities:string[]; sectors:string[]; further_tax_rate_percent?:number;
 credentials:Record<"sandbox"|"production",{configured:boolean;status:string}>;
 client_user?: { id: number; name: string; email: string } | null;
};
export type CompanyDraft = Record<"name"|"ntn_cnic"|"seller_cnic"|"province"|"address"|"contact_person"|"email"|"phone"|"invoice_series"|"currency"|"fbr_environment"|"sandbox_token"|"production_token",string> & {business_activity:string[];sector:string[];is_sales_tax_profile_retailer:boolean;allowed_sandbox_scenarios:string[]};
export type CompanyMember={id:number;name:string;email:string;role:"owner"|"member";is_active:boolean};
export type FbrScenarioResult = {scenario_id:string;title:string;passed:boolean;fbr_invoice_no:string|null;error_code:string|null;error:string|null;raw_response:unknown};
export type FbrIntegrationStatus = {verified:boolean;verification:{verified:boolean;status?:string;registration_type?:string;checked_at?:string}|null;results:FbrScenarioResult[];all_passed:boolean;production_configured:boolean};
export const fbrIntegrationApi = {
 status:(companyId:number)=>api<FbrIntegrationStatus>(`/api/company/fbr/status?company_id=${encodeURIComponent(String(companyId))}`),
 saveSandboxToken:(companyId:number,sandbox_token:string)=>api<{saved:boolean;sandbox_configured:boolean}>("/api/company/sandbox-token",{method:"POST",body:JSON.stringify({company_id:companyId,sandbox_token})}),
 verify:(companyId:number,sandbox_token?:string)=>api<{verified:boolean;status?:string;registration_type?:string;message?:string}>("/api/company/verify-fbr",{method:"POST",body:JSON.stringify({company_id:companyId,...(sandbox_token ? {sandbox_token} : {})})}),
 certify:(companyId:number,sandbox_token?:string,scenario_id?:string)=>api<{all_passed:boolean;results:FbrScenarioResult[];checked_at:string}>("/api/company/run-sandbox-certification",{method:"POST",body:JSON.stringify({company_id:companyId,...(sandbox_token ? {sandbox_token} : {}),...(scenario_id ? {scenario_id} : {})})}),
 saveProductionToken:(companyId:number,production_token:string)=>api<{saved:boolean;production_configured:boolean}>("/api/company/production-token",{method:"POST",body:JSON.stringify({company_id:companyId,production_token})}),
};
export const companyApi={
 get:(id:number)=>api<{data:CompanyDetails}>("/api/v1/companies/"+id),
 update:(id:number,data:Partial<CompanyDraft> & {seller_ntn?:string|null;seller_cnic?:string|null})=>api<{data:CompanyDetails}>("/api/v1/companies/"+id,{method:"PUT",body:JSON.stringify(data)}),
 updateClientPassword:(id:number,data:{password:string;password_confirmation:string})=>api<{message:string;client_user:{id:number;email:string;name:string}}>("/api/v1/companies/"+id+"/client-password",{method:"PUT",body:JSON.stringify(data)}),
 create:(data:{name:string;seller_ntn?:string|null;seller_cnic?:string|null;province:string;address:string;email:string;phone:string;business_activity?:string[];sector?:string[];is_sales_tax_profile_retailer?:boolean})=>api<{data:CompanyDetails}>("/api/v1/companies",{method:"POST",body:JSON.stringify(data)}),
 members:(id:number)=>api<{data:CompanyMember[]}>(`/api/v1/companies/${id}/members`),
 addMember:(id:number,email:string,role:"owner"|"member")=>api<{data:CompanyMember}>(`/api/v1/companies/${id}/members`,{method:"POST",body:JSON.stringify({email,role})}),
 updateMember:(id:number,user:number,data:{role:"owner"|"member";is_active:boolean})=>api<{data:CompanyMember}>(`/api/v1/companies/${id}/members/${user}`,{method:"PUT",body:JSON.stringify(data)}),
 removeMember:(id:number,user:number)=>api<{message:string}>(`/api/v1/companies/${id}/members/${user}`,{method:"DELETE"}),
};
export function companyDraft(company:CompanyDetails):CompanyDraft {
 const ntn = (company.seller_ntn ?? (company.ntn_cnic && company.ntn_cnic.length <= 8 ? company.ntn_cnic : "")) || "";
 const cnic = (company.seller_cnic ?? (company.ntn_cnic && company.ntn_cnic.length === 13 ? company.ntn_cnic : "")) || "";
 return {name:company.name,ntn_cnic:ntn,seller_cnic:cnic,province:company.province||"",address:company.address||"",
 contact_person:company.contact_person||"",email:company.email||"",phone:company.phone||"",
 invoice_series:company.invoice_series,currency:company.currency,fbr_environment:company.fbr_environment,business_activity:Array.isArray(company.business_activity)?[...company.business_activity]:(company.business_activity?[company.business_activity]:[]),
 sector:Array.isArray(company.sector)?[...company.sector]:(company.sector?[company.sector]:[]),is_sales_tax_profile_retailer:company.is_sales_tax_profile_retailer ?? false,allowed_sandbox_scenarios:[...company.allowed_sandbox_scenarios],sandbox_token:"",production_token:""};
}
