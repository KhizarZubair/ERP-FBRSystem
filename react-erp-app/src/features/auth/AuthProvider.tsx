import { useCallback, useEffect, useRef, useState } from "react";
import type { ReactNode } from "react";
import { ApiError } from "../../lib/api";
import { AuthContext } from "./AuthContext";
import { authApi } from "./authApi";
import type { AuthUser, LoginData, RegisterData } from "./authApi";
export function AuthProvider({ children }: { children: ReactNode }) {
    const [user, setUser] = useState<AuthUser | null>(null);
    const [status, setStatus] = useState<"loading" | "ready" | "error">("loading");
    const [error, setError] = useState("");
    const [activeCompanyId, setActiveCompanyId] = useState<number | null>(() => {
        const saved = Number(localStorage.getItem("erp-active-company"));
        return Number.isInteger(saved) && saved > 0 ? saved : null;
    });
    const revision = useRef(0);
    const channel = useRef<BroadcastChannel | null>(null);
    const refresh = useCallback(async () => {
        const current = ++revision.current;
        try {
            const result = await authApi.me();
            if (current !== revision.current) return;
            setUser(result.data);
            setActiveCompanyId(current => result.data.companies.some(company => company.id === current) ? current : result.data.companies[0]?.id ?? null);
            setError(""); setStatus("ready");
        } catch (failure) {
            if (current !== revision.current) return;
            setUser(null);
            if (failure instanceof ApiError && [401, 403].includes(failure.status)) {
                setStatus("ready");
                setError(failure.status === 403 ? failure.message : "");
            } else {
                setStatus("error");
                setError(failure instanceof Error ? failure.message : "Could not load your session.");
            }
        }
    }, []);
    const cancelRefresh = useCallback(() => { revision.current++; }, []);
    useEffect(() => {
        let active = true;
        queueMicrotask(() => { if (active) void refresh(); });
        const expired = () => { setUser(null); };
        window.addEventListener("auth-expired", expired);
        const authChannel = "BroadcastChannel" in window ? new BroadcastChannel("erp-auth") : null;
        channel.current = authChannel;
        if (authChannel) authChannel.onmessage = () => { void refresh(); };
        return () => {
            active = false; cancelRefresh();
            window.removeEventListener("auth-expired", expired);
            authChannel?.close(); channel.current = null;
        };
    }, [refresh, cancelRefresh]);
    function updateUser(next: AuthUser | null) {
        revision.current++; setUser(next); setStatus("ready"); setError("");
        setActiveCompanyId(current => next?.companies.some(company => company.id === current) ? current : next?.companies[0]?.id ?? null);
        channel.current?.postMessage({ type: "session-changed" });
    }
    function selectCompany(companyId: number) {
        if (!Number.isInteger(companyId) || companyId < 1) return;
        localStorage.setItem("erp-active-company", String(companyId));
        setActiveCompanyId(companyId);
    }
    async function login(data: LoginData) { const result = await authApi.login(data); updateUser(result.data); }
    async function register(data: RegisterData) { const result = await authApi.register(data); updateUser(result.data); }
    async function logout() {
        try { await authApi.logout(); } catch (failure) {
            if (!(failure instanceof ApiError && failure.status === 401)) throw failure;
        }
        updateUser(null);
    }
    const activeCompany = user?.companies.find(company => company.id === activeCompanyId) ?? user?.companies[0] ?? null;
    return <AuthContext.Provider value={{ user, activeCompany, selectCompany, status, error, refresh, login, register, logout }}>{children}</AuthContext.Provider>;
}
