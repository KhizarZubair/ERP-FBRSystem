import { Badge, Button } from "../../components/ui";
import { Modal } from "../../components/Modal";
import type { FbrAssignedScenario, FbrScenarioResult } from "./fbrIntegrationApi";

export function SandboxScenariosModal({ scenarios, results, checking, allPassed, onRetry, onClose }: {
    scenarios: FbrAssignedScenario[];
    results: FbrScenarioResult[];
    checking: string | null;
    allPassed: boolean;
    onRetry: () => void;
    onClose: () => void;
}) {
    const retryableFailedScenarios = results.filter(
        result => !result.passed && scenarios.find(s => s.id === result.scenario_id)?.is_supported === true
    );

    return <Modal title="FBR Sandbox Automated Certification" wide busy={!!checking} onClose={onClose} footer={<>
        {allPassed && <p className="notice notice--success">All scenarios passed! Production token unlocked.</p>}
        {retryableFailedScenarios.length > 0 && (
            <Button variant="secondary" disabled={!!checking} onClick={onRetry}>
                Retry Failed Scenarios ({retryableFailedScenarios.length})
            </Button>
        )}
        <Button variant="secondary" disabled={!!checking} onClick={onClose}>Close</Button>
    </>}>
        <p className="muted">Running test scenarios against FBR Sandbox gateway. Unsupported scenarios are never submitted to FBR API.</p>
        {scenarios.length === 0 ? (
            <p className="notice notice--warning">No assigned scenarios configured in Company Details.</p>
        ) : (
            scenarios.map(scenario => {
                const result = results.find(row => row.scenario_id === scenario.id);
                return <section className="fbr-scenario-card" key={scenario.id}>
                    <div className="fbr-scenario-heading"><strong>{scenario.id} · {scenario.title}</strong>
                        {!scenario.is_supported ? (
                            <Badge tone="neutral">Unsupported in this release</Badge>
                        ) : checking === scenario.id ? (
                            <Badge tone="neutral">Checking…</Badge>
                        ) : result?.passed ? (
                            <Badge tone="success">✓ Passed</Badge>
                        ) : result ? (
                            <Badge tone="danger">✕ Failed</Badge>
                        ) : (
                            <Badge tone="neutral">Waiting</Badge>
                        )}
                    </div>
                    {!scenario.is_supported && (
                        <p className="muted">Official fiscal formulas and SRO rules are pending for this scenario.</p>
                    )}
                    {result?.passed && <p>FBR code: {result.fbr_invoice_no || "Accepted"}</p>}
                    {result && !result.passed && <><p><strong>Error {result.error_code || "unknown"}:</strong> {result.error || "Scenario rejected."}</p>{result.error_code !== "UNSUPPORTED" && <details><summary>Raw response and corrective advice</summary><pre>{JSON.stringify(result.raw_response, null, 2)}</pre><p>Verify the exact FBR error, seller data, token permissions and official item references before retrying.</p></details>}</>}
                </section>;
            })
        )}
    </Modal>;
}
