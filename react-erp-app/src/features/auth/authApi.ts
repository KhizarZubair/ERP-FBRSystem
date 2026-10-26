import { api } from "../../lib/api";
export type AuthCompany = {
    id: number; name: string; ntn_cnic: string | null; province: string | null;
    address: string | null; technical_contact: string | null; invoice_series: string;
    currency: string; fbr_environment: "sandbox" | "production"; role: string;
    business_activity: string | null; sector: string | null; allowed_sandbox_scenarios: string[];
};
export type AuthUser = { id: number; name: string; email: string; phone: string | null; companies: AuthCompany[] };
export type LoginData = { email: string; password: string };
export type RegisterData = LoginData & { name: string; phone: string; password_confirmation: string };
export type RegistrationStatus = { data: { can_register: boolean } };
type UserResponse = { data: AuthUser };
const base = "/api/v1/auth";
export const authApi = {
    registrationStatus() { return api<RegistrationStatus>("/api/v1/auth/registration-status"); },
    me() {
        return api<UserResponse>(base + "/me");
    },
    login(data: LoginData) { return api<UserResponse>(base + "/login", { method: "POST", body: JSON.stringify(data) }); },
    register(data: RegisterData) { return api<UserResponse>(base + "/register", { method: "POST", body: JSON.stringify(data) }); },
    changePassword(data: { current_password: string; password: string; password_confirmation: string }) {
        return api<{ message: string }>(base + "/password", { method: "PUT", body: JSON.stringify(data) });
    },
    logout() { return api<{ message: string }>(base + "/logout", { method: "POST" }); },
};
