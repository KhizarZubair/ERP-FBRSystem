import { useEffect, useId, useRef, useState } from "react";
import { api } from "../lib/api";
import { RefreshButton } from "./ui";
import type { PageResult } from "../features/invoices/invoiceApi";

type LookupRecord = { id: number; name: string; is_active: boolean };
export function SearchableSelect<T extends LookupRecord>({ path, label, value, selectedLabel, describe, onSelect, filter }: {
    path: string; label: string; value: string; selectedLabel: string; describe: (record: T) => string;
    onSelect: (record: T) => void; filter?: (record: T) => boolean;
}) {
    const id = useId(); const listId = id + "-options";
    const root = useRef<HTMLDivElement>(null); const input = useRef<HTMLInputElement>(null);
    const [open, setOpen] = useState(false); const [search, setSearch] = useState("");
    const [rows, setRows] = useState<T[]>([]); const [page, setPage] = useState(1);
    const [lastPage, setLastPage] = useState(1); const [loading, setLoading] = useState(false);
    const [error, setError] = useState(""); const [active, setActive] = useState(-1); const [retry, setRetry] = useState(0);
    useEffect(() => { input.current?.setCustomValidity(value ? "" : "Select an option from the results."); }, [value]);
    useEffect(() => {
        if (!open) return;
        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            setLoading(true); setError("");
            api<PageResult<T>>(path + (path.includes("?") ? "&" : "?") + new URLSearchParams({ search, page: String(page) }), { signal: controller.signal })
                .then(result => {
                    if (controller.signal.aborted) return;
                    setRows(previous => page === 1 ? result.data.filter(row => row.is_active && (!filter || filter(row))) : [...previous, ...result.data.filter(row => row.is_active && (!filter || filter(row)) && !previous.some(old => old.id === row.id))]);
                    setLastPage(result.meta.last_page);
                }).catch(failure => { if (!controller.signal.aborted) setError(failure instanceof Error ? failure.message : "Could not load options."); })
                .finally(() => { if (!controller.signal.aborted) setLoading(false); });
        }, 250);
        return () => { window.clearTimeout(timer); controller.abort(); };
    }, [path, open, search, page, retry, filter]);
    useEffect(() => { if (active >= 0) root.current?.querySelector(`[data-option="${active}"]`)?.scrollIntoView({ block: "nearest" }); }, [active]);
    function show() { if (open) return; setOpen(true); setSearch(""); setPage(1); setRows([]); setActive(-1); setLoading(true); setError(""); }
    function choose(customer: T) { onSelect(customer); setOpen(false); setActive(-1); input.current?.focus(); }
    return <div className="field customer-combobox" ref={root}
        onBlur={event => { if (!event.currentTarget.contains(event.relatedTarget as Node | null)) setOpen(false); }}>
        <label htmlFor={id}>{label} <span className="required">*</span></label>
        <div className="customer-combobox-control">
            <input ref={input} id={id} role="combobox" aria-autocomplete="list" aria-expanded={open}
                aria-controls={listId} aria-activedescendant={open && active >= 0 ? `${listId}-${active}` : undefined}
                aria-required="true" autoComplete="off" maxLength={100}
                value={open ? search : selectedLabel} placeholder={open ? "Search" : `Select ${label.toLowerCase()}`}
                onClick={show} onChange={event => { setOpen(true); setSearch(event.target.value); setPage(1); setRows([]); setLoading(true); setError(""); setActive(-1); }}
                onKeyDown={event => {
                    if (event.key === "Escape") { event.preventDefault(); setOpen(false); }
                    else if (event.key === "ArrowDown" || event.key === "ArrowUp") {
                        event.preventDefault(); if (!open) { show(); return; }
                        setActive(position => event.key === "ArrowDown" ? Math.min(position + 1, rows.length - 1) : Math.max(0, position - 1));
                    } else if (event.key === "Enter" && open) { event.preventDefault(); if (active >= 0 && rows[active]) choose(rows[active]); }
                }} />
            <button type="button" className="customer-combobox-toggle" aria-label={`Toggle ${label.toLowerCase()} options`} aria-expanded={open}
                onClick={() => { if (open) setOpen(false); else { show(); input.current?.focus(); } }}>⌄</button>
        </div>
        {open && <div className="customer-combobox-panel">
            <div id={listId} role="listbox" aria-label={`${label} results`} className="customer-combobox-options">
                {rows.map((customer, index) => <button key={customer.id} id={`${listId}-${index}`} data-option={index}
                    type="button" role="option" aria-selected={String(customer.id) === value}
                    className={`customer-combobox-option ${active === index ? "is-highlighted" : ""}`}
                    onMouseDown={event => event.preventDefault()} onClick={() => choose(customer)}>
                    <strong>{customer.name}</strong><small>{describe(customer)}</small>
                </button>)}
            </div>
            {loading && <p className="customer-combobox-message" role="status">Searching…</p>}
            {!loading && !error && rows.length === 0 && <p className="customer-combobox-message" role="status">No results found.</p>}
            {error && <div className="customer-combobox-message" role="alert">{error}<RefreshButton label="Retry options" onClick={() => setRetry(current => current + 1)} /></div>}
            {!loading && !error && page < lastPage && <button type="button" className="customer-combobox-more" onClick={() => { setLoading(true); setPage(current => current + 1); }}>Load more results</button>}
        </div>}
    </div>;
}
