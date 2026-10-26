import { useEffect, useId, useRef, useState } from "react";
import { api, ApiError } from "../../lib/api";
import { Input , RefreshButton } from "../../components/ui";
type HsCode = { code: string; description: string };
type HsResult = { data: HsCode[]; meta: { has_more: boolean; stale: boolean; fetched_at: string } };
export function HsCodeSelect({ companyId, value, onChange, error }: {
    companyId: number; value: string; onChange: (code: string) => void; error?: string;
}) {
    const id = useId();
    const root = useRef<HTMLDivElement>(null);
    const [query, setQuery] = useState(value);
    const [options, setOptions] = useState<HsCode[]>([]);
    const [open, setOpen] = useState(false);
    const [highlight, setHighlight] = useState(-1);
    const [loading, setLoading] = useState(false);
    const [failure, setFailure] = useState("");
    const [stale, setStale] = useState(false);
    const [more, setMore] = useState(false);
    const [revision, setRevision] = useState(0);
    useEffect(() => {
        if (!open) return;
        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            setLoading(true); setFailure("");
            api<HsResult>(`/api/v1/companies/${companyId}/fbr/hs-codes?search=${encodeURIComponent(query.trim())}&limit=30`, { signal: controller.signal })
                .then(result => {
                    if (!controller.signal.aborted) {
                        setOptions(result.data); setStale(result.meta.stale); setMore(result.meta.has_more); setHighlight(-1);
                    }
                }).catch(caught => {
                    if (!controller.signal.aborted) {
                        setOptions([]);
                        setFailure(caught instanceof ApiError ? caught.errors.hs_code?.[0] || caught.message : caught instanceof Error ? caught.message : "Could not load HS codes.");
                    }
                }).finally(() => { if (!controller.signal.aborted) setLoading(false); });
        }, query ? 300 : 0);
        return () => { window.clearTimeout(timer); controller.abort(); };
    }, [companyId, query, open, revision]);
    function select(option: HsCode) {
        onChange(option.code); setQuery(option.code); setOpen(false); setHighlight(-1);
    }
    return <div ref={root} className="hs-select" onBlur={event => {
        if (!root.current?.contains(event.relatedTarget as Node | null)) setOpen(false);
    }}>
        <Input label="HS Code" required role="combobox" aria-autocomplete="list" aria-expanded={open}
            aria-controls={id} aria-activedescendant={highlight >= 0 ? id + "-" + highlight : undefined}
            autoComplete="off" maxLength={100} placeholder="Search" value={query} error={error}
            hint={value ? "Selected HS code: " + value : "Search, then select the confirmed product classification."}
            onFocus={() => { setOpen(true); setLoading(true); }}
            onClick={() => { if (!open) { setOpen(true); setLoading(true); } }}
            onChange={event => { setQuery(event.target.value); onChange(""); setOpen(true); setLoading(true); setOptions([]); setHighlight(-1); }}
            onKeyDown={event => {
                if (event.key === "Escape") { setOpen(false); return; }
                if (event.key === "ArrowDown" || event.key === "ArrowUp") {
                    event.preventDefault(); setOpen(true);
                    if (!options.length) return;
                    setHighlight(current => event.key === "ArrowDown" ? Math.min(current + 1, options.length - 1) : Math.max(current - 1, 0));
                } else if (event.key === "Enter" && open && highlight >= 0 && options[highlight]) {
                    event.preventDefault(); select(options[highlight]);
                }
            }} />
        {open && <div className="hs-select-panel">
            {loading ? <p role="status">Loading HS codes…</p> : failure ? <div>
                <p role="status">{failure}</p><a href="#/company">Open Company Details</a>{" "}
                <RefreshButton variant="quiet" onClick={() => { setLoading(true); setRevision(current => current + 1); }} label="Retry" />
            </div> : <>
                {stale && <p className="field-hint">FBR is temporarily unavailable. Showing the last cached reference list.</p>}
                <ul id={id} role="listbox" aria-label="FBR HS codes">
                    {options.map((option, index) => <li key={option.code} id={id + "-" + index} role="option" aria-selected={highlight === index || value === option.code}>
                        <button type="button" tabIndex={-1} className="hs-select-option" onMouseDown={event => event.preventDefault()}
                            onClick={() => select(option)}>
                            <strong>{option.code}</strong><span>{option.description}</span>
                        </button>
                    </li>)}
                </ul>
                {!options.length && <p role="status">No HS codes match your search.</p>}
                {more && <p className="field-hint">More matches available. Refine your search.</p>}
            </>}
        </div>}
    </div>;
}
