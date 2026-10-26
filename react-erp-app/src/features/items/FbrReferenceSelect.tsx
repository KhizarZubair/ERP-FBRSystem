import { useEffect, useState } from "react";
import { api } from "../../lib/api";
import { Input, Select } from "../../components/ui";

type ReferenceResult = { data: { code: string; description: string }[]; meta: { stale: boolean } };

export function FbrReferenceSelect({
    label,
    path,
    value,
    onChange,
    error,
    autoSelectSingle = false,
    autoSelectStandardRate = false,
}: {
    label: string;
    path: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
    autoSelectSingle?: boolean;
    autoSelectStandardRate?: boolean;
}) {
    const [requested, setRequested] = useState(true);
    const [revision, setRevision] = useState(0);
    const [result, setResult] = useState<{ path: string; data?: ReferenceResult; failed?: boolean }>();
    const [manual, setManual] = useState(false);
    const current = result?.path === path ? result : undefined;
    const loading = requested && !current;

    useEffect(() => {
        if (!requested) return;
        const controller = new AbortController();
        api<ReferenceResult>(path, { signal: controller.signal })
            .then((data) => {
                if (!controller.signal.aborted) setResult({ path, data });
            })
            .catch(() => {
                if (!controller.signal.aborted) setResult({ path, failed: true });
            });
        return () => controller.abort();
    }, [requested, path, revision]);

    const single = autoSelectSingle && current?.data?.data.length === 1 ? current.data.data[0].description : null;
    const standardRate = autoSelectStandardRate
        ? current?.data?.data.find((row) => row.description.toLowerCase().includes("standard rate"))?.description
        : null;

    useEffect(() => {
        if (!value && standardRate) onChange(standardRate);
        else if (!value && single) onChange(single);
    }, [single, standardRate, value, onChange]);

    const options = (current?.data?.data || []).map((row) => ({ value: row.description, label: row.description }));
    if (value && !options.some((option) => option.value === value)) options.push({ value, label: value });

    return (
        <div className="fbr-reference-field">
            {manual ? (
                <Input
                    label={label}
                    value={value}
                    error={error}
                    required
                    maxLength={label.toLowerCase().includes("sale type") ? 255 : 150}
                    placeholder={`Enter ${label.toLowerCase()}`}
                    onChange={(event) => onChange(event.target.value)}
                />
            ) : (
                <Select
                    label={label}
                    value={value}
                    error={error}
                    required
                    onFocus={() => setRequested(true)}
                    options={[{ value: "", label: loading ? "Loading FBR options…" : `Select ${label.toLowerCase()}` }, ...options]}
                    onChange={(event) => onChange(event.target.value)}
                />
            )}

            {current?.failed && (
                <div className="fbr-ref-toolbar" role="status">
                    <div className="fbr-ref-status">
                        <svg className="fbr-ref-status-icon" viewBox="0 0 20 20" fill="currentColor" width="14" height="14" aria-hidden="true">
                            <path fillRule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clipRule="evenodd" />
                        </svg>
                        <span>FBR list unavailable</span>
                    </div>
                    <div className="fbr-ref-actions">
                        <button
                            type="button"
                            className="fbr-ref-btn fbr-ref-btn--retry"
                            onClick={() => {
                                setResult(undefined);
                                setRevision((curr) => curr + 1);
                            }}
                            title="Retry loading from FBR"
                            aria-label="Retry loading from FBR"
                        >
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                                <path d="M21 2v6h-6" /><path d="M3 12a9 9 0 0 1 15-6.7L21 8" /><path d="M3 22v-6h6" /><path d="M21 12a9 9 0 0 1-15 6.7L3 16" />
                            </svg>
                            <span>Retry</span>
                        </button>
                        <button
                            type="button"
                            className="fbr-ref-btn fbr-ref-btn--toggle"
                            onClick={() => setManual((curr) => !curr)}
                            title={manual ? "Switch to FBR dropdown" : "Type manual value"}
                            aria-label={manual ? "Switch to FBR dropdown" : "Type manual value"}
                        >
                            {manual ? (
                                <>
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                                        <polyline points="6 9 12 15 18 9" />
                                    </svg>
                                    <span>Use Dropdown</span>
                                </>
                            ) : (
                                <>
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                                        <path d="M12 20h9" /><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z" />
                                    </svg>
                                    <span>Type Manually</span>
                                </>
                            )}
                        </button>
                    </div>
                </div>
            )}

            {manual && !current?.failed && (
                <div className="fbr-ref-toolbar" role="status">
                    <span className="field-hint" style={{ margin: 0 }}>Manual entry — FBR verification pending.</span>
                    <button
                        type="button"
                        className="fbr-ref-btn fbr-ref-btn--toggle"
                        onClick={() => setManual(false)}
                    >
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                            <polyline points="6 9 12 15 18 9" />
                        </svg>
                        <span>Use Dropdown</span>
                    </button>
                </div>
            )}

            {current?.data?.meta.stale && <p className="field-hint">Using cached FBR options.</p>}
        </div>
    );
}
