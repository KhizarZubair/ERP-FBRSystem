import { useEffect, useState } from "react";
import { api, ApiError } from "./api";

export function failureMessage(failure: unknown) {
    if (failure instanceof ApiError) return Object.values(failure.errors).flat().join(" ") || failure.message;
    return failure instanceof Error ? failure.message : "The request could not be completed.";
}
export function useApiQuery<T>(path: string | null, delay = 0) {
    const [revision, setRevision] = useState(0);
    const key = JSON.stringify([path, revision]);
    const [result, setResult] = useState<{ key: string; data?: T; error: string }>();
    useEffect(() => {
        if (!path) return;
        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            api<T>(path, { signal: controller.signal }).then(data => {
                if (!controller.signal.aborted) setResult({ key, data, error: "" });
            }).catch(failure => {
                if (!controller.signal.aborted) setResult({ key, error: failureMessage(failure) });
            });
        }, delay);
        return () => { window.clearTimeout(timer); controller.abort(); };
    }, [path, key, delay]);
    const current = path && result?.key === key ? result : undefined;
    return { data: current?.data, error: current?.error || "", loading: Boolean(path && !current),
        reload: () => setRevision(value => value + 1) };
}
