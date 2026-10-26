import { useEffect } from "react";
import { AuthProvider } from "./features/auth/AuthProvider";
import { useAuth } from "./features/auth/AuthContext";
import { AuthLayout } from "./layout/AppLayout";
import { Card, Notice, RefreshButton } from "./components/ui";
import { DemoProvider } from "./data/DemoProvider";
import { useRoute, navigate } from "./lib/navigation";
import { AppLayout } from "./layout/AppLayout";
import { AuthPage } from "./features/auth/AuthPage";
import { OverviewPage } from "./features/overview/OverviewPage";
import { CustomersPage } from "./features/customers/CustomersPage";
import { ItemsPage } from "./features/items/ItemsPage";
import { StockPage } from "./features/stock/StockPage";
import { InvoicesPage } from "./features/invoices/InvoicesPage";
import { InvoiceForm } from "./features/invoices/InvoiceForm";
import { InvoiceReview } from "./features/invoices/InvoiceReview";
import { CompanyPage } from "./features/company/CompanyPage";
import { Button, PageHeader } from "./components/ui";

function Workspace() {
    const path = useRoute();
    let page;
    if (path === "/overview") page = <OverviewPage />;
    else if (path === "/customers" || path.startsWith("/customers/"))
        page = <CustomersPage key={path} path={path} />;
    else if (path === "/items" || path.startsWith("/items/"))
        page = <ItemsPage key={path} path={path} />;
    else if (path === "/stock") page = <StockPage />;
    else if (path === "/company") page = <CompanyPage />;
    else if (path === "/invoices") page = <InvoicesPage />;
    else if (path === "/invoices/new") page = <InvoiceForm key={path} />;
    else if (/^\/invoices\/[^/]+\/review$/.test(path))
        page = <InvoiceReview key={path} id={path.split("/")[2]} />;
    else if (/^\/invoices\/[^/]+\/edit$/.test(path))
        page = <InvoiceForm key={path} id={path.split("/")[2]} />;
    else
        page = (
            <>
                <PageHeader
                    title="Page not found"
                    description="Choose a workspace page from the sidebar."
                />
                <Button onClick={() => navigate("/overview")}>
                    Open Overview
                </Button>
            </>
        );
    const { activeCompany } = useAuth();
    return (
        <AppLayout path={path} companyName={activeCompany?.name ?? "Company not configured"}>
            {page}
        </AppLayout>
    );
}
function SessionGate() {
    const path = useRoute();
    const { user, activeCompany, status, error, refresh } = useAuth();
    const authPage = path === "/login" || path === "/register";
    useEffect(() => {
        if (status !== "ready") return;
        if (user && authPage) navigate("/overview");
        else if (!user && !authPage) navigate("/login");
        else if (user && !Boolean(activeCompany?.id && activeCompany?.ntn_cnic) && ["/customers", "/items", "/stock", "/invoices", "/invoices/new"].some(route => path === route || path.startsWith(route + "/"))) navigate("/overview");
    }, [user, activeCompany, status, authPage, path]);
    if (status === "loading") return <AuthLayout><Card title="Loading your workspace"><p role="status">Checking your session…</p></Card></AuthLayout>;
    if (status === "error") return <AuthLayout><Card title="Connection unavailable"><Notice tone="warning">{error}</Notice><RefreshButton onClick={() => { void refresh(); }} label="Try again" /></Card></AuthLayout>;
    if (!user) return <AuthPage key={path} register={path === "/register"} />;
    if (authPage) return null;
    const company = activeCompany;
    return (
        <DemoProvider key={user.id + ":" + (company?.id ?? "unconfigured")} companyProfile={company ? {
            name: company.name, ntn: company.ntn_cnic || "", province: company.province || "",
            address: company.address || "", contact: company.technical_contact || "",
            series: company.invoice_series, currency: company.currency,
            mode: company.fbr_environment === "production" ? "Production" : "Sandbox",
        } : undefined}>
            <Workspace />
        </DemoProvider>
    );
}
export default function App() {
    return <AuthProvider><SessionGate /></AuthProvider>;
}
