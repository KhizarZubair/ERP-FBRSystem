export type FieldErrors = Record<string, string[]>;
export class ApiError extends Error {
    status: number;
    errors: FieldErrors;
    constructor(status: number, message: string, errors: FieldErrors = {}) {
        super(message);
        this.status = status;
        this.errors = errors;
    }
}
let csrfRequest: Promise<void> | null = null;
async function csrf() {
    if (!csrfRequest) {
        csrfRequest = fetch("/sanctum/csrf-cookie", {
            credentials: "include", headers: { Accept: "application/json" },
        }).then((response) => {
            if (!response.ok) throw new ApiError(response.status, "Could not initialize a secure session.");
        }).finally(() => { csrfRequest = null; });
    }
    return csrfRequest;
}
function xsrfToken() {
    const cookie = document.cookie.split("; ").find((part) => part.startsWith("XSRF-TOKEN="));
    return cookie ? decodeURIComponent(cookie.slice("XSRF-TOKEN=".length)) : "";
}
export async function api<T>(path: string, options: RequestInit = {}, retried = false): Promise<T> {
    const method = (options.method || "GET").toUpperCase();
    const mutation = !["GET", "HEAD", "OPTIONS"].includes(method);
    if (mutation && !xsrfToken()) await csrf();
    const headers = new Headers(options.headers);
    headers.set("Accept", "application/json");
    headers.set("X-Requested-With", "XMLHttpRequest");
    if (options.body) headers.set("Content-Type", "application/json");
    if (mutation) headers.set("X-XSRF-TOKEN", xsrfToken());
    let response: Response;
    try {
        response = await fetch(path, { ...options, method, headers, credentials: "include" });
    } catch {
        throw new ApiError(0, "Could not reach the server. Check your connection and try again.");
    }
    if (response.status === 419 && mutation && !retried) {
        await csrf();
        return api<T>(path, options, true);
    }
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
        if (response.status === 401) window.dispatchEvent(new Event("auth-expired"));
        throw new ApiError(response.status, payload.message || (
            response.status === 429 ? "Too many attempts. Please wait before trying again." :
            response.status === 419 ? "Your session expired. Please try again." : "The request could not be completed."
        ), payload.errors || {});
    }
    return payload as T;
}
