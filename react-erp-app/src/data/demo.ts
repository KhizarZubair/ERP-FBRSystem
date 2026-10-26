export type Customer = {
    id: string;
    code: string;
    name: string;
    registration: string;
    ntn: string;
    province: string;
    address: string;
    city: string;
    phone: string;
    email: string;
    active: string;
};
export type Item = {
    id: string;
    sku: string;
    name: string;
    description: string;
    hs: string;
    uom: string;
    saleType: string;
    rate: string;
    price: number;
    reorder: number;
    trackStock: string;
    retail: number;
    sro: string;
    serial: string;
    priceList: string;
    effectiveFrom: string;
    effectiveTo: string;
    active: string;
};
export type Company = {
    name: string;
    ntn: string;
    province: string;
    address: string;
    contact: string;
    mode: string;
    series: string;
    currency: string;
};
export type InvoiceLine = {
    id: string;
    itemId: string;
    quantity: number;
    price: number;
    rate: number;
    withholding: number;
    extraTax: number;
    furtherTax: number;
    fed: number;
    discount: number;
    retail: number;
    sro: string;
    serial: string;
};
export type Invoice = {
    id: string;
    number: string;
    customerId: string;
    date: string;
    type: string;
    scenario: string;
    reference: string;
    dueDate: string;
    notes: string;
    status: string;
    fbrReference: string;
    lines: InvoiceLine[];
};
export const provinces = [
    "Select province",
    "Sindh",
    "Punjab",
    "Khyber Pakhtunkhwa",
    "Balochistan",
    "Islamabad Capital Territory",
    "Gilgit-Baltistan",
    "Azad Jammu and Kashmir",
];
export const customersSeed: Customer[] = [
    {
        id: "cus-1",
        code: "CUS-0001",
        name: "Ali Traders",
        registration: "Registered",
        ntn: "",
        province: "Sindh",
        city: "Karachi",
        address: "Karachi",
        phone: "",
        email: "",
        active: "Active",
    },
    {
        id: "cus-2",
        code: "CUS-0002",
        name: "City Supplies",
        registration: "Registered",
        ntn: "",
        province: "Punjab",
        city: "Lahore",
        address: "Lahore",
        phone: "",
        email: "",
        active: "Active",
    },
    {
        id: "cus-3",
        code: "CUS-0003",
        name: "New Market Store",
        registration: "Unregistered",
        ntn: "",
        province: "Sindh",
        city: "Karachi",
        address: "Karachi",
        phone: "",
        email: "",
        active: "Active",
    },
];
export const itemsSeed: Item[] = [
    {
        id: "item-1",
        sku: "SKU-001",
        name: "Demo Product A",
        description: "Demo Product A",
        hs: "",
        uom: "Numbers, pieces, units",
        saleType: "Goods at standard rate (default)",
        rate: "18%",
        price: 1000,
        reorder: 10,
        trackStock: "Yes",
        retail: 0,
        sro: "",
        serial: "",
        priceList: "Standard",
        effectiveFrom: "2026-10-01",
        effectiveTo: "",
        active: "Active",
    },
    {
        id: "item-2",
        sku: "SKU-002",
        name: "Demo Product B",
        description: "Demo Product B",
        hs: "",
        uom: "Numbers, pieces, units",
        saleType: "Goods at standard rate (default)",
        rate: "18%",
        price: 500,
        reorder: 10,
        trackStock: "Yes",
        retail: 0,
        sro: "",
        serial: "",
        priceList: "Standard",
        effectiveFrom: "2026-10-01",
        effectiveTo: "",
        active: "Active",
    },
    {
        id: "item-3",
        sku: "SKU-003",
        name: "Demo Product C",
        description: "Demo Product C",
        hs: "",
        uom: "Kilograms",
        saleType: "Goods at standard rate (default)",
        rate: "18%",
        price: 200,
        reorder: 10,
        trackStock: "Yes",
        retail: 0,
        sro: "",
        serial: "",
        priceList: "Standard",
        effectiveFrom: "2026-10-01",
        effectiveTo: "",
        active: "Active",
    },
];
export const companySeed: Company = {
    name: "Demo Trading Co.",
    ntn: "",
    province: "Sindh",
    address: "Karachi",
    contact: "",
    mode: "Sandbox",
    series: "INV",
    currency: "PKR",
};
export function createLine(item?: Item): InvoiceLine {
    return {
        id: crypto.randomUUID(),
        itemId: item?.id || "",
        quantity: 1,
        price: item?.price || 0,
        rate: parseFloat(item?.rate || "0"),
        withholding: 0,
        extraTax: 0,
        furtherTax: 0,
        fed: 0,
        discount: 0,
        retail: item?.retail || 0,
        sro: item?.sro || "",
        serial: item?.serial || "",
    };
}
export const invoicesSeed: Invoice[] = [
    {
        id: "inv-26",
        number: "INV-0026",
        customerId: "cus-1",
        date: "2026-10-09",
        type: "Sale Invoice",
        scenario: "",
        reference: "",
        dueDate: "",
        notes: "Illustrative sandbox result.",
        status: "Accepted (test)",
        fbrReference: "DEMO-TEST-0026",
        lines: [{ ...createLine(itemsSeed[0]), quantity: 10 }],
    },
    {
        id: "inv-25",
        number: "INV-0025",
        customerId: "cus-2",
        date: "2026-10-09",
        type: "Sale Invoice",
        scenario: "",
        reference: "",
        dueDate: "",
        notes: "Demo validation: confirm product HS classification.",
        status: "Validation failed",
        fbrReference: "",
        lines: [{ ...createLine(itemsSeed[1]), quantity: 10 }],
    },
    {
        id: "inv-24",
        number: "INV-0024",
        customerId: "cus-3",
        date: "2026-10-08",
        type: "Sale Invoice",
        scenario: "",
        reference: "",
        dueDate: "",
        notes: "",
        status: "Draft",
        fbrReference: "",
        lines: [{ ...createLine(itemsSeed[2]), quantity: 10 }],
    },
];
export const movements = [
    {
        id: "m1",
        date: "2026-10-08",
        document: "OPEN-0001",
        itemId: "item-2",
        type: "Opening",
        quantity: 10,
        balance: 10,
    },
    {
        id: "m2",
        date: "2026-10-09",
        document: "GRN-0001",
        itemId: "item-1",
        type: "Receipt",
        quantity: 100,
        balance: 100,
    },
    {
        id: "m3",
        date: "2026-10-09",
        document: "GRN-0002",
        itemId: "item-2",
        type: "Receipt",
        quantity: 25,
        balance: 35,
    },
    {
        id: "m4",
        date: "2026-10-09",
        document: "GRN-0003",
        itemId: "item-3",
        type: "Receipt",
        quantity: 20,
        balance: 20,
    },
    {
        id: "m5",
        date: "2026-10-09",
        document: "DEL-0001",
        itemId: "item-1",
        type: "Issue",
        quantity: -30,
        balance: 70,
    },
    {
        id: "m6",
        date: "2026-10-09",
        document: "DEL-0002",
        itemId: "item-2",
        type: "Issue",
        quantity: -10,
        balance: 25,
    },
    {
        id: "m7",
        date: "2026-10-09",
        document: "DEL-0003",
        itemId: "item-3",
        type: "Issue",
        quantity: -12,
        balance: 8,
    },
];
export function money(value: number) {
    return (
        "PKR " +
        new Intl.NumberFormat("en-PK", { maximumFractionDigits: 2 }).format(
            value,
        )
    );
}
export function lineTotals(line: InvoiceLine) {
    const net = Math.max(0, line.quantity * line.price - line.discount);
    const tax = (net * line.rate) / 100;
    return {
        net,
        tax,
        total:
            net +
            tax +
            line.extraTax +
            line.furtherTax +
            line.fed -
            line.withholding,
    };
}
export function invoiceTotals(lines: InvoiceLine[]) {
    return lines.reduce(
        (sum, line) => {
            const result = lineTotals(line);
            return {
                net: sum.net + result.net,
                tax: sum.tax + result.tax,
                total: sum.total + result.total,
            };
        },
        { net: 0, tax: 0, total: 0 },
    );
}
