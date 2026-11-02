import { useEffect, useState } from "react";
import { Badge, Button, Card, Notice, PasswordField } from "../../components/ui";
import { Modal } from "../../components/Modal";
import { fbrIntegrationApi } from "./fbrIntegrationApi";
import type { FbrAssignedScenario, FbrIntegrationStatus, FbrScenarioResult } from "./fbrIntegrationApi";

type RunState = Record<string, "pending" | "checking" | "passed" | "failed">;

export function FbrIntegrationPanel() {
    const [status, setStatus] = useState<FbrIntegrationStatus | null>(null);
    const [sandboxToken, setSandboxToken] = useState("");
    const [productionToken, setProductionToken] = useState("");
    const [loadingStatus, setLoadingStatus] = useState(true);
    const [verifying, setVerifying] = useState(false);
    const [savingProduction, setSavingProduction] = useState(false);
    const [running, setRunning] = useState(false);
    const [modalOpen, setModalOpen] = useState(false);
    const [runState, setRunState] = useState<RunState>({});
    const [results, setResults] = useState<FbrScenarioResult[]>([]);
    const [expanded, setExpanded] = useState<Record<string, boolean>>({});
    const [error, setError] = useState("");
    const [notice, setNotice] = useState("");

    async function reloadStatus() {
        setLoadingStatus(true);
        try {
            const data = await fbrIntegrationApi.status();
            setStatus(data);
            setResults(data.results || []);
        } catch (failure) {
            setError(failure instanceof Error ? failure.message : "Could not load FBR integration status.");
        } finally { setLoadingStatus(false); }
    }
    useEffect(() => { void reloadStatus(); }, []);

    const verified = status?.verified === true;
    const allPassed = status?.all_passed === true;
    const assignedScenarios: FbrAssignedScenario[] = status?.scenarios || [];
    const hasAssignedScenarios = assignedScenarios.length > 0;

    const retryableFailedIds = results
        .filter(result => !result.passed)
        .map(result => result.scenario_id)
        .filter(id => assignedScenarios.find(s => s.id === id)?.is_supported === true);

    const hasUnsupportedFailed = results.some(
        result => !result.passed && assignedScenarios.find(s => s.id === result.scenario_id)?.is_supported !== true
    );

    async function verifyCompany() {
        setVerifying(true); setError(""); setNotice("");
        try {
            const result = await fbrIntegrationApi.verify();
            setStatus(current => current ? { ...current, verified: result.verified, verification: result } : {
                verified: result.verified, verification: result, scenarios: [], results: [], all_passed: false, production_configured: false,
            });
            if (!result.verified) setError(result.message || "FBR did not verify this company.");
            else setNotice("Company taxpayer record verified with FBR.");
            await reloadStatus();
        } catch (failure) {
            setError(failure instanceof Error ? failure.message : "FBR verification failed.");
            await reloadStatus();
        } finally { setVerifying(false); }
    }

    async function runScenarios(ids?: string[]) {
        if (!sandboxToken.trim()) { setError("Enter the Sandbox Token first."); return; }
        if (!hasAssignedScenarios) {
            setError("Configure at least one assigned sandbox scenario in Company Details before running certification.");
            return;
        }

        // Never submit unsupported scenarios to the FBR API
        const candidateIds = ids && ids.length > 0 ? ids : assignedScenarios.map(s => s.id);
        const targetIds = candidateIds.filter(id => assignedScenarios.find(s => s.id === id)?.is_supported === true);
        const unsupportedAssigned = assignedScenarios.filter(s => !s.is_supported);

        setRunning(true); setError(""); setNotice(""); setModalOpen(true);
        const nextState: RunState = {};
        assignedScenarios.forEach(s => {
            if (!s.is_supported) {
                nextState[s.id] = "failed";
            } else if (targetIds.includes(s.id)) {
                nextState[s.id] = "checking";
            } else {
                nextState[s.id] = results.find(r => r.scenario_id === s.id)?.passed ? "passed" : "pending";
            }
        });
        setRunState(nextState);

        // Pre-populate or maintain unsupported records in results with clear fail-closed status
        if (unsupportedAssigned.length > 0) {
            setResults(current => {
                const updated = [...current];
                unsupportedAssigned.forEach(un => {
                    const idx = updated.findIndex(r => r.scenario_id === un.id);
                    const record: FbrScenarioResult = {
                        scenario_id: un.id,
                        title: un.title,
                        passed: false,
                        fbr_invoice_no: null,
                        error_code: "UNSUPPORTED",
                        error: `Scenario ${un.id} is not supported in this release. Official tax formulas and SRO rules are pending.`,
                        raw_response: null,
                    };
                    if (idx >= 0) updated[idx] = record;
                    else updated.push(record);
                });
                return updated;
            });
        }

        try {
            const isFullRun = !ids || ids.length === 0 || targetIds.length === assignedScenarios.filter(s => s.is_supported).length;
            if (isFullRun) {
                // Execute complete suite in one atomic certification attempt
                const response = await fbrIntegrationApi.certify(sandboxToken.trim());
                setResults(response.results || []);
                const updatedState: RunState = { ...nextState };
                (response.results || []).forEach(res => {
                    updatedState[res.scenario_id] = res.passed ? "passed" : "failed";
                });
                setRunState(updatedState);
            } else {
                // Targeted single scenario check
                for (const id of targetIds) {
                    setRunState(current => ({ ...current, [id]: "checking" }));
                    const response = await fbrIntegrationApi.certify(sandboxToken.trim(), id);
                    setResults(response.results || []);
                    const currentResult = (response.results || []).find(result => result.scenario_id === id);
                    setRunState(current => ({ ...current, [id]: currentResult?.passed ? "passed" : "failed" }));
                }
            }
            await reloadStatus();
            setNotice(isFullRun ? "Complete sandbox certification run finished. Review results below." : "Targeted scenario test finished. Complete all assigned scenarios together to unlock production.");
        } catch (failure) {
            setRunState(current => {
                const updated = { ...current };
                targetIds.forEach(id => {
                    if (updated[id] === "checking") updated[id] = "failed";
                });
                return updated;
            });
            setError(failure instanceof Error ? failure.message : "Sandbox certification could not be completed.");
            await reloadStatus();
        } finally { setRunning(false); }
    }

    async function saveProduction() {
        if (!productionToken.trim()) { setError("Enter the Production Token first."); return; }
        setSavingProduction(true); setError(""); setNotice("");
        try {
            await fbrIntegrationApi.saveProductionToken(productionToken.trim());
            setProductionToken("");
            setNotice("Production token configured locally. Live invoice submission remains a separate action with strict user confirmation.");
            await reloadStatus();
        } catch (failure) {
            setError(failure instanceof Error ? failure.message : "Could not save the production token.");
        } finally { setSavingProduction(false); }
    }

    return <>
        <section className="fbr-integration-stack">
            <Card title="FBR Integration & Certification">
                <div className="fbr-status-row">
                    <div><h3>Phase 1 · Verify company with FBR</h3><p className="muted">Confirm your seller registration before configuring FBR credentials.</p></div>
                    <Badge tone={verified ? "success" : status && !verified ? "danger" : "neutral"}>{loadingStatus ? "Checking status…" : verified ? "Verified with FBR" : "Unverified"}</Badge>
                </div>
                {verified && status?.verification && <Notice tone="success">
                    Taxpayer status: <strong>{status.verification.status || "Active"}</strong>
                    {status.verification.registration_type ? <> · Registration: <strong>{status.verification.registration_type}</strong></> : null}
                    {status.verification.checked_at ? <> · Checked {new Date(status.verification.checked_at).toLocaleString()}</> : null}
                </Notice>}
                <div className="fbr-panel-actions">
                    <Button onClick={verifyCompany} disabled={verifying || loadingStatus}>{verifying ? "Verifying with FBR…" : "Verify Company with FBR"}</Button>
                </div>
            </Card>

            <div className={verified ? "" : "fbr-locked-wrap"}>
                {!verified && <div className="fbr-lock-overlay" role="status"><span aria-hidden="true">🔒</span><strong>FBR credentials locked</strong><span>Verify your company first to unlock sandbox testing.</span></div>}
                <Card title="Phase 2 · FBR Credentials & Sandbox Testing" className={!verified ? "fbr-locked-card" : ""}>
                    {verified && (
                        <div style={{ marginBottom: "1rem", padding: "0.75rem 1rem", background: "var(--color-surface-subtle, #f8fafc)", borderRadius: "8px", border: "1px solid var(--border-color, #e2e8f0)" }}>
                            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", flexWrap: "wrap", gap: "0.5rem" }}>
                                <div>
                                    <strong style={{ fontSize: "0.95rem" }}>FBR Sector Testing Suite:</strong>
                                    <span style={{ marginLeft: "0.5rem" }}>
                                        {status?.sector || "All Other Sectors"} · {status?.business_activity || "General"}
                                    </span>
                                </div>
                                <Badge tone="neutral">{assignedScenarios.length} Scenarios Auto-Mapped</Badge>
                            </div>
                            <p style={{ margin: "0.35rem 0 0 0", fontSize: "0.825rem", color: "var(--text-muted, #64748b)" }}>
                                Official test scenarios automatically mapped from your Business Nature & Sector per FBR Documentation Section 10.
                            </p>
                        </div>
                    )}
                    {!hasAssignedScenarios && verified && (
                        <Notice tone="warning">No assigned sandbox scenarios found. Configure your company's assigned scenarios in Company Details before running certification.</Notice>
                    )}
                    <div className="fbr-token-grid">
                        <div>
                            <PasswordField label="Sandbox Token" required autoComplete="off" value={sandboxToken} onChange={event => setSandboxToken(event.target.value)} disabled={!verified || running} hint="Sandbox token provided for your FBR/PRAL integration." />
                            <div className="fbr-panel-actions">
                                <Button disabled={!verified || !sandboxToken.trim() || !hasAssignedScenarios || running} onClick={() => void runScenarios()}>
                                    {running ? "Running scenarios…" : "Verify Token & Run Scenarios"}
                                </Button>
                            </div>
                        </div>
                        <div>
                            <div className="fbr-production-heading">
                                <h3>Production Token</h3>
                                <Badge tone={status?.production_configured ? "neutral" : allPassed ? "success" : "neutral"}>
                                    {status?.production_configured ? "Configured Locally" : allPassed ? "Unlocked (Not Configured)" : "Locked"}
                                </Badge>
                            </div>
                            <PasswordField
                                label="Production Token"
                                autoComplete="off"
                                value={productionToken}
                                onChange={event => setProductionToken(event.target.value)}
                                disabled={!verified || !allPassed || savingProduction}
                                hint={
                                    !allPassed
                                        ? "Production Token is locked. Complete all Sandbox Scenarios with passing status to unlock live production access."
                                        : status?.production_configured
                                        ? "Token is stored locally. This credentials file enables ERP communication with FBR. Enter a new token only to replace it."
                                        : "Unlocked after all sandbox scenarios pass."
                                }
                            />
                            {status?.production_configured && (
                                <p className="fbr-lock-hint">
                                    <span aria-hidden="true">ℹ️</span> <strong>Configured locally:</strong> Saving a token stores credentials in the ERP. Actual live invoice acceptance is granted by FBR individually upon submitting each invoice.
                                </p>
                            )}
                            {!allPassed && (
                                <p className="fbr-lock-hint"><span aria-hidden="true">🔒</span> Complete all assigned sandbox scenarios successfully to unlock production credentials.</p>
                            )}
                            <div className="fbr-panel-actions">
                                <Button disabled={!verified || !allPassed || !productionToken.trim() || savingProduction} onClick={saveProduction}>
                                    {savingProduction ? "Saving…" : "Save Production Token"}
                                </Button>
                            </div>
                        </div>
                    </div>
                    {allPassed && <Notice tone="success">All configured sandbox scenarios passed. Production token entry is unlocked.</Notice>}
                    {results.length > 0 && <div className="fbr-saved-results">
                        <h3>Latest sandbox results</h3>
                        {assignedScenarios.map(scenario => {
                            const result = results.find(r => r.scenario_id === scenario.id);
                            if (!result) return null;
                            return <div className="fbr-result-line" key={scenario.id}>
                                <span>{scenario.id} · {scenario.title}</span>
                                <Badge tone={result.passed ? "success" : "danger"}>{result.passed ? "Passed" : "Failed"}</Badge>
                            </div>;
                        })}
                    </div>}
                </Card>
            </div>
            {error && <Notice tone="warning">{error}</Notice>}
            {notice && <Notice tone="success">{notice}</Notice>}
        </section>

        {modalOpen && <Modal title="FBR Sandbox Automated Certification" wide busy={running} onClose={() => setModalOpen(false)} footer={<div className="fbr-modal-footer">
            {allPassed && <Notice tone="success">All scenarios passed! Production token unlocked.</Notice>}
            {!running && retryableFailedIds.length > 0 && (
                <Button variant="secondary" onClick={() => void runScenarios()}>
                    Rerun Full Certification
                </Button>
            )}
            <Button variant="secondary" disabled={running} onClick={() => setModalOpen(false)}>Close</Button>
        </div>}>
            <p className="muted">Running test scenarios against the FBR Sandbox gateway. No production invoice endpoint is used by this certification flow.</p>
            {hasUnsupportedFailed && (
                <p className="fbr-lock-hint">
                    <span aria-hidden="true">⚠️</span> Unsupported scenarios cannot be submitted to FBR Sandbox until official tax formulas and SRO rules are released.
                </p>
            )}
            <div className="fbr-scenario-list">
                {assignedScenarios.map(scenario => {
                    const result = results.find(r => r.scenario_id === scenario.id);
                    const state = runState[scenario.id];
                    return <section className="fbr-scenario-card" key={scenario.id}>
                        <div className="fbr-scenario-heading">
                            <div>
                                <strong>{scenario.id}</strong>
                                <h3>{scenario.title}</h3>
                            </div>
                            {!scenario.is_supported ? (
                                <Badge tone="neutral">Unsupported in this release</Badge>
                            ) : state === "checking" ? (
                                <Badge tone="neutral">◌ Checking…</Badge>
                            ) : state === "passed" || result?.passed ? (
                                <Badge tone="success">✓ Passed</Badge>
                            ) : state === "failed" || result ? (
                                <Badge tone="danger">✕ Failed</Badge>
                            ) : (
                                <Badge tone="neutral">Not run</Badge>
                            )}
                        </div>
                        {!scenario.is_supported && (
                            <p className="muted">This scenario is assigned to your company profile but is not yet executable. Official fiscal formulas and SRO rules are pending.</p>
                        )}
                        {result?.passed && <p className="fbr-pass-detail">FBR reference: <strong>{result.fbr_invoice_no || "Accepted"}</strong></p>}
                        {result && !result.passed && <div className="fbr-failure-detail">
                            <p><strong>Error code:</strong> {result.error_code || "Not supplied by FBR"}</p>
                            <p><strong>Reason:</strong> {result.error || "FBR did not accept this scenario."}</p>
                            {result.error_code !== "UNSUPPORTED" && (
                                <>
                                    <Button variant="quiet" onClick={() => setExpanded(current => ({ ...current, [scenario.id]: !current[scenario.id] }))}>
                                        {expanded[scenario.id] ? "Hide raw response" : "Show raw response & advice"}
                                    </Button>
                                    {expanded[scenario.id] && <div className="fbr-raw-response">
                                        <pre>{JSON.stringify(result.raw_response ?? { error: result.error }, null, 2)}</pre>
                                        <p>Check the exact FBR error code, token permissions, taxpayer status, official HS code, UOM and tax rate before retrying.</p>
                                    </div>}
                                </>
                            )}
                        </div>}
                    </section>;
                })}
            </div>
            {running && <p role="status" className="fbr-running-note">Certification is running sequentially. Keep this window open until the current scenario finishes.</p>}
        </Modal>}
    </>;
}
