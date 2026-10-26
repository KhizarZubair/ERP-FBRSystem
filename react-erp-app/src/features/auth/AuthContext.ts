import { createContext, useContext } from "react";
import type { AuthCompany, AuthUser, LoginData, RegisterData } from "./authApi";
export type AuthState = {
    user: AuthUser | null;
    activeCompany: AuthCompany | null;
    selectCompany: (companyId: number) => void;
    status: "loading" | "ready" | "error";
    error: string;
    refresh: () => Promise<void>;
    login: (data: LoginData) => Promise<void>;
    register: (data: RegisterData) => Promise<void>;
    logout: () => Promise<void>;
};
export const AuthContext = createContext<AuthState | null>(null);
export function useAuth() {
    const value = useContext(AuthContext);
    if (!value) throw new Error("AuthProvider is required.");
    return value;
}
