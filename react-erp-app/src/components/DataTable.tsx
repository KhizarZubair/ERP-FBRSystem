import { useEffect, useRef, useState } from "react";
import type { ReactNode } from "react";
import { Button } from "./ui";
export type Column<T> = {
    key: string;
    label: string;
    render: (row: T) => ReactNode;
    sortValue?: (row: T) => string | number;
};
export function DataTable<T>({
    columns,
    rows,
    rowKey,
    caption,
    empty = "No records match your filters.",
    pageSize = 8,
    pagination,
}: {
    columns: Column<T>[];
    rows: T[];
    rowKey: (row: T) => string;
    caption: string;
    empty?: string;
    pageSize?: number;
    pagination?: { page: number; pages: number; total: number; pageSize: number; onPage: (page: number) => void; sort?: { key: string; descending: boolean }; onSort?: (key: string, descending: boolean) => void };
}) {
    const scrollRegion = useRef<HTMLDivElement>(null);
    const [scrollable, setScrollable] = useState(false);
    useEffect(() => {
        const region = scrollRegion.current;
        if (!region) return;
        const observer = new ResizeObserver(() => setScrollable(region.scrollWidth > region.clientWidth + 1));
        observer.observe(region);
        const table = region.querySelector("table");
        if (table) observer.observe(table);
        return () => observer.disconnect();
    }, []);
    const [localSort, setSort] = useState<{
        key: string;
        descending: boolean;
    } | null>(null);
    const [page, setPage] = useState(1);
    const sort = pagination?.sort ?? localSort;
    const column = columns.find((c) => c.key === sort?.key);
    const ordered = !pagination && column?.sortValue
        ? [...rows].sort((a, b) => {
              const av = column.sortValue!(a),
                  bv = column.sortValue!(b);
              const result =
                  typeof av === "number" && typeof bv === "number"
                      ? av - bv
                      : String(av).localeCompare(String(bv));
              return sort?.descending ? -result : result;
          })
        : rows;
    const size = pagination?.pageSize ?? pageSize;
    const total = pagination?.total ?? rows.length;
    const pages = pagination?.pages ?? Math.max(1, Math.ceil(rows.length / size));
    const current = pagination?.page ?? Math.min(page, pages);
    const changePage = pagination?.onPage ?? setPage;
    const visible = pagination ? rows : ordered.slice((current - 1) * size, current * size);
    return (
        <>
            <div
                ref={scrollRegion}
                className="table-scroll"
                role="region"
                aria-label={caption}
                tabIndex={0}
            >
                <table>
                    <caption className="sr-only">{caption}</caption>
                    <thead>
                        <tr>
                            {columns.map((c) => (
                                <th
                                    key={c.key}
                                    scope="col"
                                    aria-sort={
                                        sort?.key === c.key
                                            ? sort.descending
                                                ? "descending"
                                                : "ascending"
                                            : undefined
                                    }
                                >
                                    {c.sortValue ? (
                                        <button
                                            className="table-sort"
                                            type="button"
                                            onClick={() => {
                                                if (pagination?.onSort) {
                                                    pagination.onSort(c.key, sort?.key === c.key ? !sort.descending : false);
                                                    return;
                                                }
                                                setPage(1);
                                                setSort({
                                                    key: c.key,
                                                    descending:
                                                        sort?.key === c.key
                                                            ? !sort.descending
                                                            : false,
                                                });
                                            }}
                                        >
                                            {c.label}{" "}
                                            <span aria-hidden="true">
                                                {sort?.key === c.key
                                                    ? sort.descending
                                                        ? "↓"
                                                        : "↑"
                                                    : "↕"}
                                            </span>
                                        </button>
                                    ) : (
                                        c.label
                                    )}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {visible.map((row) => (
                                <tr key={rowKey(row)}>
                                    {columns.map((c) => (
                                        <td key={c.key} className={c.key === "actions" ? "table-actions-cell" : undefined}>{c.render(row)}</td>
                                    ))}
                                </tr>
                            ))}
                        {!rows.length && (
                            <tr>
                                <td colSpan={columns.length}>
                                    <div className="empty-state">{empty}</div>
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
            {scrollable && <p className="table-scroll-hint">Scroll horizontally to view all columns.</p>}
            <div className="table-footer">
                <span>
                    {total
                        ? `${(current - 1) * size + 1}–${Math.min(current * size, total)} of ${total} records`
                        : "0 records"}
                </span>
                <div className="actions">
                    <Button
                        variant="quiet"
                        disabled={current === 1}
                        onClick={() => changePage(current - 1)}
                        aria-label="Previous table page"
                    >
                        Previous
                    </Button>
                    <span>
                        {current} / {pages}
                    </span>
                    <Button
                        variant="quiet"
                        disabled={current === pages}
                        onClick={() => changePage(current + 1)}
                        aria-label="Next table page"
                    >
                        Next
                    </Button>
                </div>
            </div>
        </>
    );
}
