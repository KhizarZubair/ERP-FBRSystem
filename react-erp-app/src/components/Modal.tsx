import { useEffect, useId, useRef } from "react";
import type { ReactNode } from "react";
import { Button } from "./ui";

export function Modal({ title, children, footer, wide = false, busy = false, onClose }: {
    title: string; children: ReactNode; footer?: ReactNode; wide?: boolean; busy?: boolean; onClose: () => void;
}) {
    const dialog = useRef<HTMLDialogElement>(null);
    const titleId = useId();
    useEffect(() => {
        const element = dialog.current;
        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = "hidden";
        element?.showModal();
        return () => { element?.close(); document.body.style.overflow = previousOverflow; };
    }, []);
    return <dialog ref={dialog} className={`app-modal ${wide ? "app-modal--wide" : ""}`} aria-labelledby={titleId}
        onCancel={event => { event.preventDefault(); if (!busy) onClose(); }}
        onClick={event => {
            if (busy || event.target !== event.currentTarget) return;
            const rect = event.currentTarget.getBoundingClientRect();
            if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) onClose();
        }}>
        <header className="app-modal-header"><h2 id={titleId}>{title}</h2>
            <Button variant="quiet" disabled={busy} aria-label={`Close ${title}`} onClick={onClose}>×</Button></header>
        <div className="app-modal-body">{children}</div>
        {footer && <footer className="app-modal-footer">{footer}</footer>}
    </dialog>;
}
