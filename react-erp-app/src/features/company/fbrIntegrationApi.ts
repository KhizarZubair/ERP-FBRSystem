import { api } from "../../lib/api";

export type FbrAssignedScenario = {
    id: string;
    title: string;
    is_supported: boolean;
};

export type FbrScenarioResult = {
    scenario_id: string;
    title: string;
    passed: boolean;
    fbr_invoice_no: string | null;
    error_code: string | number | null;
    error: string | null;
    category?: string | null;
    guidance?: string | null;
    solution?: string | null;
    raw_response: unknown;
};

export type FbrVerification = {
    verified: boolean;
    status?: string;
    registration_type?: string;
    checked_at?: string;
    message?: string;
};

export type FbrIntegrationStatus = {
    verified: boolean;
    verification: FbrVerification | null;
    business_activity?: string | null;
    sector?: string | null;
    scenarios: FbrAssignedScenario[];
    results: FbrScenarioResult[];
    all_passed: boolean;
    certified_token_fingerprint?: string | null;
    sandbox_configured: boolean;
    production_configured: boolean;
};

export const fbrIntegrationApi = {
    status: () => api<FbrIntegrationStatus>("/api/company/fbr/status"),
    verify: (sandbox_token?: string) =>
        api<FbrVerification>("/api/company/verify-fbr", {
            method: "POST",
            body: JSON.stringify(sandbox_token ? { sandbox_token } : {}),
        }),
    saveSandboxToken: (sandbox_token: string) =>
        api<{ saved: boolean; sandbox_configured: boolean }>("/api/company/sandbox-token", {
            method: "POST",
            body: JSON.stringify({ sandbox_token }),
        }),
    certify: (sandbox_token?: string, scenario_id?: string) =>
        api<{ all_passed: boolean; results: FbrScenarioResult[]; checked_at: string }>(
            "/api/company/run-sandbox-certification",
            { method: "POST", body: JSON.stringify({ ...(sandbox_token ? { sandbox_token } : {}), ...(scenario_id ? { scenario_id } : {}) }) },
        ),
    saveProductionToken: (production_token: string) =>
        api<{ saved: boolean; production_configured: boolean }>("/api/company/production-token", {
            method: "POST", body: JSON.stringify({ production_token }),
        }),
};
