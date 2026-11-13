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
    const [savingSandbox, setSavingSandbox] = useState(false);
    const [verifying, setVerifying] = useState(false);
    const [savingProduction, setSavingProduction] = useState(false);
    const [running, setRunning] = useState(false);
    const [modalOpen, setModalOpen] = useState(false);
    const [runState, setRunState] = useState<RunState>({});
    const [results, setResults] = useState<FbrScenarioResult[]>([]);
    const [expanded, setExpanded] = useState<Record<string, boolean>>({});
    const [error, setError] = useState("");
    const [verificationError, setVerificationError] = useState("");
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
    const isSandboxConfigured = status?.sandbox_configured === true;
    const assignedScenarios: FbrAssignedScenario[] = status?.scenarios || [];
    const hasAssignedScenarios = assignedScenarios.length > 0;

    const retryableFailedIds = results
        .filter(result => !result.passed)
        .map(result => result.scenario_id)
        .filter(id => assignedScenarios.find(s => s.id === id)?.is_supported === true);

    const hasUnsupportedFailed = results.some(
        result => !result.passed && assignedScenarios.find(s => s.id === result.scenario_id)?.is_supported !== true
    );

    async function handleSaveSandboxToken() {
        if (!sandboxToken.trim()) {
            setError("Please enter the FBR Sandbox Token to save.");
            return;
        }
        setSavingSandbox(true);
        setError("");
        setNotice("");
        try {
            await fbrIntegrationApi.saveSandboxToken(sandboxToken.trim());
            setSandboxToken("");
            setNotice("PRAL Sandbox Token saved securely in database.");
            await reloadStatus();
        } catch (failure) {
            setError(failure instanceof Error ? failure.message : "Could not save Sandbox Token.");
        } finally {
            setSavingSandbox(false);
        }
    }

    async function verifyCompany() {
        if (!isSandboxConfigured && !sandboxToken.trim()) {
            setError("Please save the FBR Sandbox Token first.");
            return;
        }
        setVerifying(true);
        setError("");
        setVerificationError("");
        setNotice("");
        try {
            if (sandboxToken.trim()) {
                await fbrIntegrationApi.saveSandboxToken(sandboxToken.trim());
                setSandboxToken("");
            }
            const result = await fbrIntegrationApi.verify();
            setStatus(current => current ? { ...current, verified: result.verified, verification: result, sandbox_configured: true } : null);
            if (!result.verified) {
                setVerificationError(result.message || "FBR did not verify this company.");
            } else {
                setNotice("Company taxpayer record verified with FBR.");
            }
            await reloadStatus();
        } catch (failure) {
            const details = failure && typeof failure === "object" && "errors" in failure
                ? Object.entries((failure as { errors: Record<string, string[]> }).errors).flatMap(([field, messages]) => messages.map(message => `${field}: ${message}`))
                : [];
            setVerificationError([failure instanceof Error ? failure.message : "FBR verification failed.", ...details].join("\n"));
            await reloadStatus();
        } finally {
            setVerifying(false);
        }
    }

    async function runScenarios(ids?: string[]) {
        if (!isSandboxConfigured && !sandboxToken.trim()) {
            setError("Save the Sandbox Token first.");
            return;
        }
        if (!hasAssignedScenarios) {
            setError("Configure at least one assigned sandbox scenario in Company Details before running certification.");
            return;
        }

        const candidateIds = ids && ids.length > 0 ? ids : assignedScenarios.map(s => s.id);
        const targetIds = candidateIds.filter(id => assignedScenarios.find(s => s.id === id)?.is_supported === true);
        const unsupportedAssigned = assignedScenarios.filter(s => !s.is_supported);

        setRunning(true);
        setError("");
        setNotice("");
        setModalOpen(true);
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
            if (sandboxToken.trim()) {
                await fbrIntegrationApi.saveSandboxToken(sandboxToken.trim());
                setSandboxToken("");
            }
            const isFullRun = !ids || ids.length === 0 || targetIds.length === assignedScenarios.filter(s => s.is_supported).length;
            if (isFullRun) {
                const response = await fbrIntegrationApi.certify(undefined);
                setResults(response.results || []);
                const updatedState: RunState = { ...nextState };
                (response.results || []).forEach(res => {
                    updatedState[res.scenario_id] = res.passed ? "passed" : "failed";
                });
                setRunState(updatedState);
            } else {
                for (const id of targetIds) {
                    setRunState(current => ({ ...current, [id]: "checking" }));
                    const response = await fbrIntegrationApi.certify(undefined, id);
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
        } finally {
            setRunning(false);
        }
    }

    async function saveProduction() {
        if (!productionToken.trim()) { setError("Enter the Production Token first."); return; }
        setSavingProduction(true); setError(""); setNotice("");
        try {
            await fbrIntegrationApi.saveProductionToken(productionToken.trim());
            setProductionToken("");
            setNotice("Production token configured in database. Live invoice submission enabled.");
            await reloadStatus();
        } catch (failure) {
            setError(failure instanceof Error ? failure.message : "Could not save the production token.");
        } finally { setSavingProduction(false); }
    }

    return <>
        <section className="fbr-integration-stack">
            {/* Sandbox Credentials */}
            <Card title="FBR PRAL Sandbox Credentials">
                <div className="fbr-status-row">
                    <div>
                        <h3>Sandbox Credentials Configuration</h3>
                        <p className="muted">Enter the PRAL Sandbox Security Token provided in your PRAL Sandbox environment. Saved securely in database for multi-client isolation.</p>
                    </div>
                    <div style={{ display: "flex", gap: "8px", flexWrap: "wrap", alignItems: "center" }}>
                        <Badge tone={isSandboxConfigured ? "success" : "neutral"}>
                            {isSandboxConfigured ? "Token Saved in DB" : "Token Not Saved"}
                        </Badge>
                    </div>
                </div>

                <div style={{ marginTop: "16px" }}>
                    <PasswordField
                        label="PRAL Sandbox Token (Sandbox & Validate APIs)"
                        autoComplete="off"
                        value={sandboxToken}
                        onChange={event => setSandboxToken(event.target.value)}
                        placeholder={isSandboxConfigured ? "•••••••••••••••• (Configured in DB)" : "Enter PRAL Sandbox Token (e.g. 72535495-d1e4-...)"}
                        hint="Covers both postinvoicedata_sb and validateinvoicedata_sb endpoints. Enter a new token to update."
                    />
                </div>

                <div className="fbr-panel-actions" style={{ marginTop: "14px", gap: "10px" }}>
                    <Button
                        onClick={handleSaveSandboxToken}
                        disabled={savingSandbox || loadingStatus || !sandboxToken.trim()}
                    >
                        {savingSandbox ? "Saving Token…" : isSandboxConfigured ? "Update Sandbox Token" : "Save Sandbox Token to DB"}
                    </Button>
                </div>
            </Card>

            {/* Sandbox Testing & Verification */}
            <div className={isSandboxConfigured ? "" : "fbr-locked-wrap"}>
                {!isSandboxConfigured && (
                    <div className="fbr-lock-overlay" role="status">
                        <span aria-hidden="true">🔒</span>
                        <strong>Sandbox Testing & Verification Locked</strong>
                        <span>Save your PRAL Sandbox Token above to unlock testing & verification.</span>
                    </div>
                )}
                <Card title="FBR Verification & Automated Sandbox Certification" className={!isSandboxConfigured ? "fbr-locked-card" : ""}>
                    <div className="fbr-status-row" style={{ marginBottom: "1rem" }}>
                        <div>
                            <strong style={{ fontSize: "0.95rem" }}>FBR Sector Testing Suite:</strong>
                            <span style={{ marginLeft: "0.5rem" }}>
                                {status?.sector || "All Other Sectors"} · {status?.business_activity || "General"}
                            </span>
                        </div>
                        <div style={{ display: "flex", gap: "8px", flexWrap: "wrap", alignItems: "center" }}>
                            <Badge tone={verified ? "success" : "neutral"}>
                                {verified ? "Taxpayer Verified" : "Taxpayer Unverified"}
                            </Badge>
                            <Badge tone="neutral">{assignedScenarios.length} Scenarios Auto-Mapped</Badge>
                        </div>
                    </div>

                    <p style={{ margin: "0 0 1rem 0", fontSize: "0.85rem", color: "var(--text-muted, #64748b)" }}>
                        You can run <strong>Auto Sandbox Testing</strong> immediately with your PRAL sandbox token. <strong>Company Verification</strong> can be performed independently once PRAL whitelists your server IP.
                    </p>

                    {/* Two Independent Action Buttons */}
                    <div className="fbr-panel-actions" style={{ gap: "12px", display: "flex", flexWrap: "wrap" }}>
                        <Button
                            variant="secondary"
                            disabled={!isSandboxConfigured || verifying || loadingStatus}
                            onClick={verifyCompany}
                        >
                            {verifying ? "Verifying with FBR…" : verified ? "Re-verify Company with FBR" : "Verify Company with FBR"}
                        </Button>
                        <Button
                            disabled={!isSandboxConfigured || !hasAssignedScenarios || running || loadingStatus}
                            onClick={() => void runScenarios()}
                        >
                            {running ? "Running Auto Testing…" : "Run Auto Sandbox Testing"}
                        </Button>
                    </div>

                    {verificationError && (
                        <div style={{ marginTop: "14px" }}>
                            <Notice tone="warning">
                                <strong>FBR verification failed</strong>
                                <p style={{ margin: "8px 0 0", whiteSpace: "pre-line" }}>{verificationError}</p>
                            </Notice>
                        </div>
                    )}

                    {verified && status?.verification && <div style={{ marginTop: "14px" }}>
                        <Notice tone="success">
                            <strong>Company verified with FBR.</strong>
                            <ul style={{ margin: "8px 0 0", paddingLeft: "20px" }}>
                                <li>Company NTN: {status.verification.ntn_cnic || "Matched"}</li>
                                <li>STATL status: {status.verification.status || "Unknown"} (code: {status.verification.statl_status_code || "—"})</li>
                                <li>Get_Reg_Type: {status.verification.registration_type || "Registered"}</li>
                            </ul>
                            {status.verification.checked_at ? <p>Checked {new Date(status.verification.checked_at).toLocaleString()}</p> : null}
                        </Notice>
                    </div>}

                    {!hasAssignedScenarios && isSandboxConfigured && (
                        <div style={{ marginTop: "14px" }}>
                            <Notice tone="warning">No assigned sandbox scenarios found. Configure your company's assigned scenarios in Company Details before running certification.</Notice>
                        </div>
                    )}

                    {allPassed && (
                        <div style={{ marginTop: "16px" }}>
                            <Notice tone="success">All configured sandbox scenarios passed successfully! FBR Sandbox Certification achieved. Live Production access is unlocked below.</Notice>
                        </div>
                    )}

                    {results.length > 0 && <div className="fbr-saved-results" style={{ marginTop: "16px" }}>
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

            {/* Live Production Credentials */}
            <div className={allPassed ? "" : "fbr-locked-wrap"}>
                {!allPassed && (
                    <div className="fbr-lock-overlay" role="status">
                        <span aria-hidden="true">🔒</span>
                        <strong>Production Credentials Locked</strong>
                        <span>Complete all assigned sandbox scenarios successfully to unlock live production access.</span>
                    </div>
                )}
                <Card title="Live Production Credentials" className={!allPassed ? "fbr-locked-card" : ""}>
                    <div className="fbr-production-heading">
                        <div>
                            <h3>Production Token</h3>
                            <p className="muted">Live FBR Bearer token for submitting accepted digital tax invoices.</p>
                        </div>
                        <Badge tone={status?.production_configured ? "success" : allPassed ? "neutral" : "neutral"}>
                            {status?.production_configured ? "Configured in DB" : allPassed ? "Unlocked (Not Configured)" : "Locked"}
                        </Badge>
                    </div>
                    <PasswordField
                        label="Production Token"
                        autoComplete="off"
                        value={productionToken}
                        onChange={event => setProductionToken(event.target.value)}
                        disabled={!allPassed || savingProduction}
                        placeholder={status?.production_configured ? "•••••••••••••••• (Configured in DB)" : "Enter live production token"}
                        hint={
                            !allPassed
                                ? "Production Token is locked. Complete all Sandbox Scenarios with passing status in Phase 2 to unlock live production access."
                                : status?.production_configured
                                ? "Token is stored securely in database. Enter a new token only to replace it."
                                : "Unlocked after all sandbox scenarios pass. Enter the live PRAL production token."
                        }
                    />
                    {status?.production_configured && (
                        <p className="fbr-lock-hint">
                            <span aria-hidden="true">ℹ️</span> <strong>Configured in Database:</strong> Live invoice submission uses this company's production credentials.
                        </p>
                    )}
                    <div className="fbr-panel-actions">
                        <Button disabled={!allPassed || !productionToken.trim() || savingProduction} onClick={saveProduction}>
                            {savingProduction ? "Saving…" : "Save Production Token"}
                        </Button>
                    </div>
                </Card>
            </div>

            {error && <Notice tone="warning"><span style={{ whiteSpace: "pre-line" }}>{error}</span></Notice>}
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
