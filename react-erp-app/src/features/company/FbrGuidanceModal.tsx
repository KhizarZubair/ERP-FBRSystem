import { Button } from "../../components/ui";
import { Modal } from "../../components/Modal";

const steps = [
    { title: "Open IRIS API Integration", text: "In IRIS, choose API Integration and a licensed integrator." },
    { title: "Complete integration details", text: "Provide your technical contact, ERP/software, business nature and sector details." },
    { title: "Provide hosting details for PRAL", text: "For PRAL integration, submit your hosting server and outbound public IP for approval." },
    { title: "Complete sandbox scenarios", text: "Complete the required sandbox scenarios before using your production token." },
];

export function FbrGuidanceModal({ onClose }: { onClose: () => void }) {
    return <Modal title="FBR Credential Details" onClose={onClose} footer={<Button variant="secondary" onClick={onClose}>Close</Button>}>
            <p className="muted">Follow these steps to prepare your FBR integration details.</p>
            <ol className="fbr-guidance-steps">{steps.map(step => <li key={step.title}>
                <h3>{step.title}</h3><p>{step.text}</p>
            </li>)}</ol>
            <p className="notice">Keep IRIS and CRM passwords in their respective portals. Buyer details, HS codes, units, quantities and tax classifications belong to customers, items and individual invoices.</p>
            <a href="https://www.fbr.gov.pk/di-technical-assistance/173967/174202" target="_blank" rel="noreferrer">Official FBR setup guide</a>
    </Modal>;
}
