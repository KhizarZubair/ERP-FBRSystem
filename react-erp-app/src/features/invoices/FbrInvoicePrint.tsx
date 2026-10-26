import { useEffect, useState } from "react";
import QRCode from "qrcode";
import { Button, Card, Notice } from "../../components/ui";
import type { InvoiceRecord } from "./invoiceApi";
import { money } from "./invoiceApi";

export function FbrInvoicePrint({ invoice }: { invoice: InvoiceRecord }) {
    const [qrResult, setQrResult] = useState<{ reference: string; dataUrl?: string; error?: string }>({ reference: "" });
    const reference = invoice.fbr_invoice_number;
    const qrDataUrl = qrResult.reference === reference ? qrResult.dataUrl || "" : "";
    const qrError = qrResult.reference === reference ? qrResult.error || "" : "";

    useEffect(() => {
        let active = true;
        if (!reference || invoice.fbr_status !== "accepted") {
            return () => { active = false; };
        }

        QRCode.toDataURL(reference, {
            version: 2,
            errorCorrectionLevel: "L",
            margin: 0,
            width: 256,
        }).then((dataUrl) => {
            if (active) setQrResult({ reference, dataUrl });
        }).catch(() => {
            if (active) setQrResult({ reference, error: "This FBR invoice number cannot be encoded using the required Version 2 QR format. Printing is disabled until the accepted reference is checked." });
        });

        return () => { active = false; };
    }, [reference, invoice.fbr_status]);

    if (invoice.fbr_status !== "accepted" || !invoice.fbr_invoice_number) {
        return null;
    }

    return (
        <Card title="FBR invoice print">
            <p>Accepted FBR reference: <strong>{invoice.fbr_invoice_number}</strong></p>
            {qrError && <Notice tone="warning">{qrError}</Notice>}
            {!qrDataUrl && !qrError && <p role="status">Preparing the FBR QR code…</p>}
            {qrDataUrl && (
                <>
                    <div className="fbr-print-preview">
                        <img src={qrDataUrl} alt="QR code containing the accepted FBR invoice number" width={160} height={160} />
                        <p>Scan the QR code to read the FBR invoice reference.</p>
                    </div>
                    <div className="actions fbr-print-actions">
                        <Button onClick={() => window.print()}>Print FBR invoice</Button>
                    </div>
                    <section className="fbr-print-page" aria-label="Printable FBR invoice">
                        <header className="fbr-print-header">
                            <div>
                                <h1>Digital Invoice</h1>
                                <p>FBR invoice number: <strong>{invoice.fbr_invoice_number}</strong></p>
                                <p>Local invoice: {invoice.number} · Date: {invoice.invoice_date}</p>
                            </div>
                            <img src={qrDataUrl} alt="FBR invoice QR code" width={96} height={96} />
                        </header>
                        <div className="fbr-print-parties">
                            <section><h2>Seller</h2><p>{invoice.seller_business_name}</p><p>{invoice.seller_ntn_cnic}</p><p>{invoice.seller_address}, {invoice.seller_province}</p></section>
                            <section><h2>Buyer</h2><p>{invoice.buyer_business_name}</p><p>{invoice.buyer_ntn_cnic || "Not provided"} · {invoice.buyer_registration_type}</p><p>{invoice.buyer_address}, {invoice.buyer_province}</p></section>
                        </div>
                        <table className="fbr-print-table">
                            <thead><tr><th>Item</th><th>HS code</th><th>Qty</th><th>Unit price</th><th>Sales tax</th><th>Total</th></tr></thead>
                            <tbody>{invoice.lines.map((line) => (
                                <tr key={line.id ?? line.line_number}>
                                    <td>{line.product_description || line.sku}</td><td>{line.hs_code || "—"}</td>
                                    <td>{line.quantity}</td><td>{money(line.unit_price)}</td>
                                    <td>{money(line.sales_tax_applicable || "0")}</td><td>{money(line.total_values || "0")}</td>
                                </tr>
                            ))}</tbody>
                        </table>
                        <div className="fbr-print-total"><span>Total</span><strong>{money(invoice.total_values)}</strong></div>
                        <p className="fbr-print-footnote">QR code encodes the accepted FBR invoice number. Retain the original FBR submission response for verification.</p>
                    </section>
                </>
            )}
        </Card>
    );
}
