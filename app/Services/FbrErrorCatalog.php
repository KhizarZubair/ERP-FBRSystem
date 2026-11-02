<?php

namespace App\Services;

class FbrErrorCatalog
{
    /** @var array<string, array{category: string, official: string, brief: string, guidance: string, solution: string}> */
    private const CATALOG = [
        '0001' => [
            'category' => 'AUTHENTICATION',
            'official' => 'please provide valid registration/NTN. Invalid Buyer Registration No or NTN',
            'brief' => 'valid seller registration/NTN. Buyer Registration Number or NTN is not in proper',
            'guidance' => 'Verify that the seller NTN is registered for sales tax on the FBR portal.',
            'solution' => 'Company settings mein Seller NTN check karein aur FBR portal par Sales Tax Active status verify karein.',
        ],
        '0002' => [
            'category' => 'BUYER',
            'official' => ':',
            'brief' => 'format, please provide buyer registration number in 13 digits or NTN in 7 or 9 digits Invoice type is not valid or empty, please provide valid',
            'guidance' => 'Provide buyer CNIC (13 digits) or NTN (7 or 8 digits) without dashes.',
            'solution' => 'Buyer ka NTN 7 digits ya CNIC 13 digits hona chahiye (bina spaces ya dashes ke).',
        ],
        '0003' => [
            'category' => 'INVOICE_HEADER',
            'official' => 'Provide proper invoice type. please provide date in valid format',
            'brief' => 'invoice type Invoice date is not in proper format, please provide',
            'guidance' => 'Select a valid FBR invoice type (Sale Invoice or Debit Note).',
            'solution' => 'Invoice Type select karein (e.g. Sale Invoice ya Debit Note).',
        ],
        '0005' => [
            'category' => 'INVOICE_HEADER',
            'official' => '01-DEC-2021',
            'brief' => 'invoice date in "YYYY-MM-DD" format. For example: 2025-05-25',
            'guidance' => 'Invoice date must be in YYYY-MM-DD format and cannot be in the future.',
            'solution' => 'Invoice date ka format YYYY-MM-DD (e.g. 2025-05-25) hona chahiye.',
        ],
        '0006' => [
            'category' => 'CALCULATION',
            'official' => 'Sale invoice not exist. Wrong Sale type is selected with',
            'brief' => 'Sales invoice does not exist against STWH Selected invoice type is not associated with proper',
            'guidance' => 'Review invoice field: Sale invoice not exist. Wrong Sale type is selected with',
            'solution' => 'FBR Message: Sale invoice not exist. Wrong Sale type is selected with. Check values and retry.',
        ],
        '0007' => [
            'category' => 'INVOICE_HEADER',
            'official' => 'invoice no (Invoice no) ST withheld at source should either',
            'brief' => 'registration number, please select actual invoice type ST withheld at source is not equal to zero or sales tax,',
            'guidance' => 'Selected sale type is not allowed for this invoice type or taxpayer registration.',
            'solution' => 'Selected Sale Type is invoice type ke mutabiq nahi hai. Sahi Sale Type choose karein.',
        ],
        '0008' => [
            'category' => 'CALCULATION',
            'official' => 'be zero or same as sales tax/fed in st mode.',
            'brief' => 'please enter ST withheld at source zero or equal to sales tax Buyer Registration Number cannot be empty, please',
            'guidance' => 'Sales tax withheld at source must be zero or equal to applicable sales tax.',
            'solution' => 'Withheld tax ya to zero hona chahiye ya total sales tax ke barabar.',
        ],
        '0009' => [
            'category' => 'BUYER',
            'official' => 'Provide Buyer registration No.',
            'brief' => 'provide proper buyer registration number Buyer Name cannot be empty, please provide valid',
            'guidance' => 'Buyer registration number or CNIC cannot be empty for registered transactions.',
            'solution' => 'Buyer Registration No darj karna lazmi hai. Customer profile mein NTN/CNIC update karein.',
        ],
        '0010' => [
            'category' => 'BUYER',
            'official' => 'Provide Buyer Name.',
            'brief' => 'buyer name Buyer Registration type cannot be empty, please',
            'guidance' => 'Buyer business name cannot be empty.',
            'solution' => 'Buyer ka registered karobari naam enter karein.',
        ],
        '0011' => [
            'category' => 'INVOICE_HEADER',
            'official' => 'Provide invoice type.',
            'brief' => 'invoice type Sale type cannot be empty/null, please provide valid',
            'guidance' => 'Select a valid FBR invoice type (Sale Invoice or Debit Note).',
            'solution' => 'Invoice Type select karein (e.g. Sale Invoice ya Debit Note).',
        ],
        '0012' => [
            'category' => 'BUYER',
            'official' => 'Provide Buyer Registration Type',
            'brief' => 'provide valid Buyer Registration type Invoice type cannot be empty, please provide valid',
            'guidance' => 'Specify buyer registration type: Registered or Unregistered.',
            'solution' => 'Buyer ki registration type (Registered ya Unregistered) select karein.',
        ],
        '0013' => [
            'category' => 'ITEM_REFERENCE',
            'official' => 'Provide valid Sale type. Please provide Sales Tax/FED in ST',
            'brief' => 'sale type Sales Tax/FED cannot be empty, please valid provide',
            'guidance' => 'Select an FBR-approved sale type for the invoice line.',
            'solution' => 'Item line par valid Sale Type select karein.',
        ],
        '0018' => [
            'category' => 'CALCULATION',
            'official' => 'Mode',
            'brief' => 'Sales Tax/FED HS Code cannot be empty, please provide valid HS',
            'guidance' => 'Sales tax / FED amount cannot be empty. Recalculate invoice line taxes.',
            'solution' => 'Sales tax ya FED amount khali nahi ho sakti. Taxes recalculate karein.',
        ],
        '0019' => [
            'category' => 'ITEM_REFERENCE',
            'official' => 'Please provide HSCode',
            'brief' => 'Code',
            'guidance' => 'Provide a valid 8-digit Harmonized System (HS) code.',
            'solution' => 'Product ka 8-digit official FBR HS Code darj karein.',
        ],
        '0020' => [
            'category' => 'ITEM_REFERENCE',
            'official' => 'Please provide Rate Please provide Value of Sales Excl. ST',
            'brief' => 'Rate field cannot be empty, please provide Rate Value of Sales Excl. ST /Quantity cannot be empty,',
            'guidance' => 'Provide a valid tax rate percentage approved for this sale type.',
            'solution' => 'Selected sale type ke liye FBR approved tax rate select karein.',
        ],
        '0021' => [
            'category' => 'CALCULATION',
            'official' => '/Quantity Please provide ST withheld at Source',
            'brief' => 'Please provide valid Value of Sales Excl. ST /Quantity ST withheld at Source or STS Withheld cannot be',
            'guidance' => 'Review invoice field: /Quantity Please provide ST withheld at Source',
            'solution' => 'FBR Message: /Quantity Please provide ST withheld at Source. Check values and retry.',
        ],
        '0022' => [
            'category' => 'CALCULATION',
            'official' => 'or STS Withheld',
            'brief' => 'empty, Please provide valid ST withheld at Source or STS Withheld Sales Tax cannot be empty, Please provide valid Sales',
            'guidance' => 'Review invoice field: or STS Withheld',
            'solution' => 'FBR Message: or STS Withheld. Check values and retry.',
        ],
        '0023' => [
            'category' => 'CALCULATION',
            'official' => 'Please provide Sales Tax',
            'brief' => 'Tax Sales Tax withheld cannot be empty, Please provide',
            'guidance' => 'Review invoice field: Please provide Sales Tax',
            'solution' => 'FBR Message: Please provide Sales Tax. Check values and retry.',
        ],
        '0024' => [
            'category' => 'CALCULATION',
            'official' => 'Please provide ST withheld',
            'brief' => 'valid Sales Tax withheld Invoice Reference No. is mandatory requirement for',
            'guidance' => 'Review invoice field: Please provide ST withheld',
            'solution' => 'FBR Message: Please provide ST withheld. Check values and retry.',
        ],
        '0026' => [
            'category' => 'INVOICE_HEADER',
            'official' => 'Invoice Reference No. is required.',
            'brief' => 'debit/credit note. Please provide valid Invoice Reference No. Reason is mandatory requirement for debit/credit',
            'guidance' => 'Reference invoice number (22 or 28 digits) is required for debit/credit notes.',
            'solution' => 'Debit/Credit note ke liye original FBR invoice ka 22 ya 28 digit reference number enter karein.',
        ],
        '0027' => [
            'category' => 'INVOICE_HEADER',
            'official' => 'Reason is required.',
            'brief' => 'note. Please provide valid reason for debit/credit note Reason is selected as "Others". Please provide valid',
            'guidance' => 'Review invoice field: Reason is required.',
            'solution' => 'FBR Message: Reason is required.. Check values and retry.',
        ],
        '0028' => [
            'category' => 'INVOICE_HEADER',
            'official' => 'Reason Remarks are required. Invoice date must be greater or',
            'brief' => 'remarks against this reason Debit/Credit note date should be equal or greater',
            'guidance' => 'Review invoice field: Reason Remarks are required. Invoice date must be greater or',
            'solution' => 'FBR Message: Reason Remarks are required. Invoice date must be greater or. Check values and retry.',
        ],
        '0029' => [
            'category' => 'INVOICE_HEADER',
            'official' => 'equal to original invoice no. Unregistered distributer type not',
            'brief' => 'from original invoice date Unregistered distributer type not allowed before',
            'guidance' => 'Review invoice field: equal to original invoice no. Unregistered distributer type not',
            'solution' => 'FBR Message: equal to original invoice no. Unregistered distributer type not. Check values and retry.',
        ],
        '0030' => [
            'category' => 'INVOICE_HEADER',
            'official' => 'allowed before date',
            'brief' => 'system cut of date',
            'guidance' => 'Review invoice field: allowed before date',
            'solution' => 'FBR Message: allowed before date. Check values and retry.',
        ],
        '0031' => [
            'category' => 'INVOICE_HEADER',
            'official' => 'Provide Sales Tax STWH can only be created for',
            'brief' => 'Sales Tax is not mentioned, please provide Sales Tax User is not FTN holder, STWH can only be created for',
            'guidance' => 'Review invoice field: Provide Sales Tax STWH can only be created for',
            'solution' => 'FBR Message: Provide Sales Tax STWH can only be created for. Check values and retry.',
        ],
        '0032' => [
            'category' => 'INVOICE_HEADER',
            'official' => 'GOV/FTN Holders. {0} only allowed within {1} days of',
            'brief' => 'GOV/FTN Holders without sales invoice. Debit/Credit note can only be added within 180 days',
            'guidance' => 'Review invoice field: GOV/FTN Holders. {0} only allowed within {1} days of',
            'solution' => 'FBR Message: GOV/FTN Holders. {0} only allowed within {1} days of. Check values and retry.',
        ],
        '0034' => [
            'category' => 'INVOICE_HEADER',
            'official' => 'invoice date of the original invoice {0} date must be greater or equal to',
            'brief' => 'of original invoice date Note Date must be greater or equal to original invoice',
            'guidance' => 'Debit note can only be issued within 180 days of the original invoice.',
            'solution' => 'FBR qanoon ke mutabiq debit note sirf original invoice ke 180 din ke andar issue ho sakta hai.',
        ],
        '0035' => [
            'category' => 'INVOICE_HEADER',
            'official' => 'original invoice date. Total {1} value of {0} invoice(s) is',
            'brief' => 'date Credit Note Value of Sale must be less or equal to the',
            'guidance' => 'Review invoice field: original invoice date. Total {1} value of {0} invoice(s) is',
            'solution' => 'FBR Message: original invoice date. Total {1} value of {0} invoice(s) is. Check values and retry.',
        ],
        '0036' => [
            'category' => 'INVOICE_HEADER',
            'official' => 'greater than {1} of original invoice. Value of Sales Total {1} value of {0} invoice(s) is',
            'brief' => 'value of Sale in original invoice. Credit Note Value of ST Withheld must be less or',
            'guidance' => 'Reference invoice number (22 or 28 digits) is required for debit/credit notes.',
            'solution' => 'Debit/Credit note ke liye original FBR invoice ka 22 ya 28 digit reference number enter karein.',
        ],
        '0037' => [
            'category' => 'INVOICE_HEADER',
            'official' => 'greater than {1} of original invoice.ST Withheld as WH Agent',
            'brief' => 'equal to the value of ST Withheld in original invoice. For registered users, STWH invoice fields must be',
            'guidance' => 'Review invoice field: greater than {1} of original invoice.ST Withheld as WH Agent',
            'solution' => 'FBR Message: greater than {1} of original invoice.ST Withheld as WH Agent. Check values and retry.',
        ],
        '0039' => [
            'category' => 'INVOICE_HEADER',
            'official' => 'Sale invoice not exist.',
            'brief' => 'same as sale invoice Invoice number cannot be empty, please provide',
            'guidance' => 'Review invoice field: Sale invoice not exist.',
            'solution' => 'FBR Message: Sale invoice not exist.. Check values and retry.',
        ],
        '0041' => [
            'category' => 'INVOICE_HEADER',
            'official' => 'Provide invoice No. In',
            'brief' => 'invoice number. voice date cannot be empty, please provide invoice',
            'guidance' => 'Review invoice field: Provide invoice No. In',
            'solution' => 'FBR Message: Provide invoice No. In. Check values and retry.',
        ],
        '0042' => [
            'category' => 'INVOICE_HEADER',
            'official' => 'Provide invoice date. da In',
            'brief' => 'te. voice date is not valid, please provide valid invoice',
            'guidance' => 'Review invoice field: Provide invoice date. da In',
            'solution' => 'FBR Message: Provide invoice date. da In. Check values and retry.',
        ],
        '0043' => [
            'category' => 'INVOICE_HEADER',
            'official' => 'Provide valid Date. da',
            'brief' => 'te.',
            'guidance' => 'Review invoice field: Provide valid Date. da',
            'solution' => 'FBR Message: Provide valid Date. da. Check values and retry.',
        ],
        '0044' => [
            'category' => 'ITEM_REFERENCE',
            'official' => 'Provide HS Code.                         HS Ra',
            'brief' => 'Code cannot be empty, please provide HS Code te cannot be empty, please provide valid rate as per',
            'guidance' => 'Provide a valid Unit of Measurement (UOM) approved by FBR.',
            'solution' => 'FBR master UOM list se valid unit select karein.',
        ],
        '0046' => [
            'category' => 'ITEM_REFERENCE',
            'official' => 'Provide rate. se Please provide valid Sales Tax Pl withheld. For sale type \'Cotton',
            'brief' => 'lected Sales Type. ease provide valid Sales Tax withheld. For sale type',
            'guidance' => 'Reload and select an FBR-approved rate for the selected sale type.',
            'solution' => 'FBR Message: Provide rate. se Please provide valid Sales Tax Pl withheld. For sale type \'Cotton. Check values and retry.',
        ],
        '0050' => [
            'category' => 'SRO_SCHEDULE',
            'official' => '\'C ginners\', Sales Tax Withheld must be Sa equal to Sales Tax or zero Please provide valid HS Code against     HS',
            'brief' => 'otton ginners\', Sales Tax Withheld must be equal to les Tax or zero Code that does not match with provided sale type,',
            'guidance' => 'Review invoice field: \'C ginners\', Sales Tax Withheld must be Sa equal to Sales Tax or zero Please provide valid HS Code against     HS',
            'solution' => 'FBR Message: \'C ginners\', Sales Tax Withheld must be Sa equal to Sales Tax or zero Please provide valid HS Code against     HS. Check values and retry.',
        ],
        '0052' => [
            'category' => 'ITEM_REFERENCE',
            'official' => 'invoice no:                              Pl Provided buyer registration type is      Bu',
            'brief' => 'ease provide valid HS Code against sale type yer Registration Type is invalid, please provide valid',
            'guidance' => 'Review the item HS code and sale type combination.',
            'solution' => 'FBR Message: invoice no:                              Pl Provided buyer registration type is      Bu. Check values and retry.',
        ],
        '0053' => [
            'category' => 'BUYER',
            'official' => 'invalid                                  Bu Please Provide ST Withheld as WH         Sa',
            'brief' => 'yer Registration Type les tax withheld cannot be empty or invalid format.',
            'guidance' => 'Verify the buyer registration type with FBR.',
            'solution' => 'FBR Message: invalid                                  Bu Please Provide ST Withheld as WH         Sa. Check values and retry.',
        ],
        '0055' => [
            'category' => 'ITEM_REFERENCE',
            'official' => 'Agent                                    Pl',
            'brief' => 'ease provide valid sales tax withheld.',
            'guidance' => 'HS code is not active in current customs tariff. Select an active HS code.',
            'solution' => 'Yeh HS Code FBR tariff mein active nahi hai. FBR item lookup se taza code lein.',
        ],
        '0056' => [
            'category' => 'ITEM_REFERENCE',
            'official' => 'Buyer not exists in steel sector.        Bu Re',
            'brief' => 'yer does not exist in steel sector ference invoice for debit/ credit note does not',
            'guidance' => 'Buyer does not exist in Steel Sector. Verify buyer steel registration or sale type.',
            'solution' => 'Buyer steel sector mein registered nahi hai. Steel sale type sirf registered steel buyers ke liye hai.',
        ],
        '0057' => [
            'category' => 'ITEM_REFERENCE',
            'official' => 'Reference Invoice does not exist. ex Bu',
            'brief' => 'ists. Please provide valid Invoice Reference No. yer and Seller Registration number are same, this',
            'guidance' => 'Use Toll Manufacturing Sale Type for toll processing goods in steel/manufacturing.',
            'solution' => 'Steel sector processing ke liye Toll Manufacturing sale type select karein.',
        ],
        '0058' => [
            'category' => 'BUYER',
            'official' => 'Self-invoicing not allowed ty',
            'brief' => 'pe of invoice is not allowed',
            'guidance' => 'Self-invoicing is prohibited: Seller NTN and Buyer NTN cannot be identical.',
            'solution' => 'Buyer aur Seller ka NTN/CNIC aik jesa nahi ho sakta. Customer ka NTN check karein.',
        ],
        '0064' => [
            'category' => 'CALCULATION',
            'official' => 'Reference invoice already exist.         Cr {1} of {0} invoice is greater than {1}   Sa',
            'brief' => 'edit note is already added to a invoice les Tax value of Debit Note is greater than original',
            'guidance' => 'Discount cannot exceed quantity multiplied by unit price.',
            'solution' => 'Discount total gross amount (Quantity x Price) se zyada nahi ho sakta.',
        ],
        '0067' => [
            'category' => 'CALCULATION',
            'official' => 'of original invoice.                     in {1} of {0} invoice is less than {1} of   Sa',
            'brief' => 'voice\'s sales tax les Tax value of Credit Note is less than original',
            'guidance' => 'Sales tax withheld at source cannot exceed applicable sales tax.',
            'solution' => 'Withheld sales tax total sales tax se barha nahi ho sakta.',
        ],
        '0068' => [
            'category' => 'CALCULATION',
            'official' => 'original invoice.                        in STWH cannot be created for               Us',
            'brief' => 'voice\'s sales tax according to the rate. er is not registered, STWH is allowed only for',
            'guidance' => 'Review invoice field: original invoice.                        in STWH cannot be created for               Us',
            'solution' => 'FBR Message: original invoice.                        in STWH cannot be created for               Us. Check values and retry.',
        ],
        '0070' => [
            'category' => 'CALCULATION',
            'official' => 'unregistered buyers.                     re Entry of {0} against the declared',
            'brief' => 'gistered user',
            'guidance' => 'Invoice total values must exactly equal valueSalesExcludingST + taxes - discount.',
            'solution' => 'Invoice totals line items ke sum ke barabar honi chahiye. Recalculate totals karein.',
        ],
        '0071' => [
            'category' => 'INVOICE_HEADER',
            'official' => 'Cr invoice is not allowed. Sa Provide Sale Origination Province of',
            'brief' => 'edit note allowed to add only for specific users le Origination Province of Supplier cannot be empty,',
            'guidance' => 'Review invoice field: Cr invoice is not allowed. Sa Provide Sale Origination Province of',
            'solution' => 'FBR Message: Cr invoice is not allowed. Sa Provide Sale Origination Province of. Check values and retry.',
        ],
        '0073' => [
            'category' => 'CALCULATION',
            'official' => 'pl Supplier Su De',
            'brief' => 'ease provide valid Sale Origination Province of pplier. stination of Supply cannot be empty, please',
            'guidance' => 'Review invoice field: pl Supplier Su De',
            'solution' => 'FBR Message: pl Supplier Su De. Check values and retry.',
        ],
        '0074' => [
            'category' => 'INVOICE_HEADER',
            'official' => 'Provide Destination of Supply pr S',
            'brief' => 'ovide valid Destination of Supply RO/Schedule Number cannot be empty, please',
            'guidance' => 'Review invoice field: Provide Destination of Supply pr S',
            'solution' => 'FBR Message: Provide Destination of Supply pr S. Check values and retry.',
        ],
        '0077' => [
            'category' => 'SRO_SCHEDULE',
            'official' => 'Provide SRO/Schedule No. p I',
            'brief' => 'rovide valid SRO/Schedule Number tem serial number cannot be empty, please provide',
            'guidance' => 'Provide SRO / Schedule Number for reduced rate, exempt, or zero-rated goods.',
            'solution' => 'Exempt ya concessional rate claim karne ke liye FBR SRO Schedule Number enter karein.',
        ],
        '0078' => [
            'category' => 'SRO_SCHEDULE',
            'official' => 'Provide Item Sr. No. v If Value of Sales Excl. ST greater than   I',
            'brief' => 'alid item serial number f sales value is greater than 20,000 than rate 5% is',
            'guidance' => 'Provide Item Serial Number corresponding to the SRO Schedule.',
            'solution' => 'SRO Schedule ke andar item ka Serial Number darj karna lazmi hai.',
        ],
        '0079' => [
            'category' => 'SRO_SCHEDULE',
            'official' => '{0}. Rate {1} not allowed.                n F',
            'brief' => 'ot allowed urther Tax\' cannot be empty, please provide valid',
            'guidance' => 'Tax cannot be zero or reduced without a valid SRO schedule and item serial number.',
            'solution' => 'Bina valid SRO ke tax zero ya 5% concessional nahi ho sakta. SRO details enter karein.',
        ],
        '0080' => [
            'category' => 'CALCULATION',
            'official' => 'Please provide Further Tax F Please provide Input Credit not           \'',
            'brief' => 'urther Tax Input Credit not Allowed\' cannot be empty, please',
            'guidance' => 'Unregistered buyers require 3% Further Tax. Recalculate taxes to add Further Tax.',
            'solution' => 'Buyer Unregistered hai, is liye 3% Further Tax mandatory hai. Auto-calculate tax chalayein.',
        ],
        '0081' => [
            'category' => 'CALCULATION',
            'official' => 'Allowed                                   p The Seller is not registered for sales T',
            'brief' => 'rovide ‘Input Credit not Allowed\' he Seller is not registered for sales tax. Please',
            'guidance' => 'Further Tax is not applicable on registered buyers or exempt/zero-rated goods.',
            'solution' => 'Registered buyers ya exempt goods par Further Tax zero hona chahiye.',
        ],
        '0082' => [
            'category' => 'AUTHENTICATION',
            'official' => 'tax. Please provide a valid p registration/NTN. S',
            'brief' => 'rovide a valid registration/NTN. eller Reg No. doesn’t match. Please provide valid',
            'guidance' => 'Verify that the seller is registered for sales tax.',
            'solution' => 'FBR Message: tax. Please provide a valid p registration/NTN. S. Check values and retry.',
        ],
        '0083' => [
            'category' => 'AUTHENTICATION',
            'official' => 'Mismatch Seller Registration No. S Please provide Total Value of Sales       T',
            'brief' => 'eller Registration Number otal Value of Sales is not provided, please provide',
            'guidance' => 'Ensure the seller NTN matches the owner of the configured token.',
            'solution' => 'FBR Message: Mismatch Seller Registration No. S Please provide Total Value of Sales       T. Check values and retry.',
        ],
        '0085' => [
            'category' => 'CALCULATION',
            'official' => '(In case of PFAD only)                    v You are not an EFS license holder Y',
            'brief' => 'alid Total Value of Sales (In case of PFAD only) ou are not an EFS license holder who has imported',
            'guidance' => 'Review invoice field: (In case of PFAD only)                    v You are not an EFS license holder Y',
            'solution' => 'FBR Message: (In case of PFAD only)                    v You are not an EFS license holder Y. Check values and retry.',
        ],
        '0086' => [
            'category' => 'CALCULATION',
            'official' => 'who has imported Compressor Scrap C in the last 12 months. Petroleum Levy rates not configured       P',
            'brief' => 'ompressor Scrap in the last 12 months. etroleum Levy rates not configured properly. Please',
            'guidance' => 'Review invoice field: who has imported Compressor Scrap C in the last 12 months. Petroleum Levy rates not configured       P',
            'solution' => 'FBR Message: who has imported Compressor Scrap C in the last 12 months. Petroleum Levy rates not configured       P. Check values and retry.',
        ],
        '0087' => [
            'category' => 'CALCULATION',
            'official' => 'properly.                                 u Alphanumeric and (-) contained            I',
            'brief' => 'pdate levy rates properly nvoice number is not valid, please provide valid',
            'guidance' => 'Negative amounts are not allowed in tax or price fields.',
            'solution' => 'Kisi bhi amount field mein manfi (negative) raqam darj na karein.',
        ],
        '0088' => [
            'category' => 'CALCULATION',
            'official' => 'invoice No. is allowed. (-) should be     i in between Alphanumeric string.           I F',
            'brief' => 'nvoice number in alphanumeric format. For example: nv-001 ED Charged cannot be empty, please provide valid',
            'guidance' => 'Invoice total values must exactly equal valueSalesExcludingST + taxes - discount.',
            'solution' => 'Invoice totals line items ke sum ke barabar honi chahiye. Recalculate totals karein.',
        ],
        '0089' => [
            'category' => 'CALCULATION',
            'official' => 'Please provide FED Charged F F Please provide Fixed / notified value',
            'brief' => 'ED Charged ixed / notified value or Retail Price cannot be empty,',
            'guidance' => 'Review invoice field: Please provide FED Charged F F Please provide Fixed / notified value',
            'solution' => 'FBR Message: Please provide FED Charged F F Please provide Fixed / notified value. Check values and retry.',
        ],
        '0090' => [
            'category' => 'ITEM_REFERENCE',
            'official' => 'p or Retail Price P',
            'brief' => 'lease provide valid Fixed / notified value or Retail rice',
            'guidance' => 'Unit of Measurement does not match the provided HS code. Check FBR HS_UOM.',
            'solution' => 'Is HS Code par yeh UOM allowed nahi hai. FBR HS_UOM list ke mutabiq unit choose karein.',
        ],
        '0091' => [
            'category' => 'ITEM_REFERENCE',
            'official' => 'Extra tax must be empty.                  E P',
            'brief' => 'xtra tax must be empty. urchase type cannot be empty, please provide valid',
            'guidance' => 'Review invoice field: Extra tax must be empty.                  E P',
            'solution' => 'FBR Message: Extra tax must be empty.                  E P. Check values and retry.',
        ],
        '0092' => [
            'category' => 'CALCULATION',
            'official' => 'Provide Valid Sale Type. p Selected Sale Type are not allowed        S',
            'brief' => 'urchase type elected Sale is are not allowed to Manufacturer.',
            'guidance' => 'Review invoice field: Provide Valid Sale Type. p Selected Sale Type are not allowed        S',
            'solution' => 'FBR Message: Provide Valid Sale Type. p Selected Sale Type are not allowed        S. Check values and retry.',
        ],
        '0093' => [
            'category' => 'SRO_SCHEDULE',
            'official' => 'to Manufacturer.                          P Please provide Extra Tax                  E',
            'brief' => 'lease select proper sale type xtra Tax cannot be empty, please provide valid extra',
            'guidance' => 'Item serial number was not found in the selected SRO schedule.',
            'solution' => 'Selected SRO Schedule mein yeh Item Serial Number mojood nahi hai.',
        ],
        '0095' => [
            'category' => 'ITEM_REFERENCE',
            'official' => 't For selected HSCode only KWH UOM',
            'brief' => 'ax',
            'guidance' => 'Item quantity must be greater than zero.',
            'solution' => 'Quantity zero ya negative nahi ho sakti. Positive miqdaar enter karein.',
        ],
        '0096' => [
            'category' => 'ITEM_REFERENCE',
            'official' => 'Fo is allowed.',
            'brief' => 'r provided HS Code, only KWH UOM is allowed',
            'guidance' => 'Review invoice field: Fo is allowed.',
            'solution' => 'FBR Message: Fo is allowed.. Check values and retry.',
        ],
        '0097' => [
            'category' => 'ITEM_REFERENCE',
            'official' => 'Provide UOM KG.                          Pl Please provide Quantity / Electricity    Qu',
            'brief' => 'ease provide UOM in KG antity / Electricity Unit cannot be empty, please',
            'guidance' => 'Review invoice field: Provide UOM KG.                          Pl Please provide Quantity / Electricity    Qu',
            'solution' => 'FBR Message: Provide UOM KG.                          Pl Please provide Quantity / Electricity    Qu. Check values and retry.',
        ],
        '0098' => [
            'category' => 'ITEM_REFERENCE',
            'official' => 'Units                                    pr UO',
            'brief' => 'ovide valid Quantity / Electricity Unit M is not valid. UOM must be according to given HS',
            'guidance' => 'Review invoice field: Units                                    pr UO',
            'solution' => 'FBR Message: Units                                    pr UO. Check values and retry.',
        ],
        '0099' => [
            'category' => 'ITEM_REFERENCE',
            'official' => 'Provide uom. Co Cotton Ginners allowed against           Re',
            'brief' => 'de gistered user cannot add sale invoice. Only cotton',
            'guidance' => 'Select a UOM returned by FBR for this HS code.',
            'solution' => 'FBR Message: Provide uom. Co Cotton Ginners allowed against           Re. Check values and retry.',
        ],
        '0100' => [
            'category' => 'ITEM_REFERENCE',
            'official' => 'registered buyers only.                  gi Please Use Toll Manufacturing Sale       Sa',
            'brief' => 'nner sale type is allowed for registered users. le type is not selected properly, please use Toll',
            'guidance' => 'Cotton Ginners sale type is restricted to registered buyers only.',
            'solution' => 'Textile/Cotton Ginner sale type sirf registered buyers ke liye allow hai.',
        ],
        '0101' => [
            'category' => 'ITEM_REFERENCE',
            'official' => 'Type for Steel Sector.                   Ma Calculated tax not matched in 3rd        Th',
            'brief' => 'nufacturing Sale Type for Steel Sector. e calculated sales tax not calculated as per 3rd',
            'guidance' => 'Use Toll Manufacturing Sale Type for toll processing goods in steel/manufacturing.',
            'solution' => 'Steel sector processing ke liye Toll Manufacturing sale type select karein.',
        ],
        '0102' => [
            'category' => 'CALCULATION',
            'official' => 'schedule                                 sc Ca The calculated tax for Potassium',
            'brief' => 'hedule calculation formula lculated tax not matched for potassium chlorate.',
            'guidance' => 'Third Schedule goods require sales tax calculated on printed Retail Price, not net sale value.',
            'solution' => '3rd Schedule items par tax net invoice value par nahi, balki printed Retail Price x Quantity par lagna chahiye.',
        ],
        '0103' => [
            'category' => 'CALCULATION',
            'official' => 'Ca Chlorate does not match. po Ca The calculated percentage sales tax',
            'brief' => 'lculated value doesn’t match according to tassium chlorate for sales potassium invoices. lculated percentage of sales tax not matched.',
            'guidance' => 'Potassium Chlorate sales tax calculation mismatch. Check fixed duty rate.',
            'solution' => 'Potassium Chlorate ka tax formula check karein.',
        ],
        '0104' => [
            'category' => 'CALCULATION',
            'official' => 'Ca does not match. ra The calculated sales tax for the',
            'brief' => 'lculation must be correct with respect to provided te',
            'guidance' => 'Recalculate sales tax using the FBR-approved rate.',
            'solution' => 'FBR Message: Ca does not match. ra The calculated sales tax for the. Check values and retry.',
        ],
        '0105' => [
            'category' => 'CALCULATION',
            'official' => 'Th quantity is incorrect. The Buyer is not registered for sales Th',
            'brief' => 'e calculated sales tax for the quantity is incorrect. e Buyer is not registered for sales tax. Please',
            'guidance' => 'Cement / Concrete block sales tax calculation mismatch.',
            'solution' => 'Cement sector ke specific tax formula ke mutabiq tax recalculate karein.',
        ],
        '0106' => [
            'category' => 'BUYER',
            'official' => 'tax. Please provide a valid pr registration/NTN. Bu',
            'brief' => 'ovide a valid registration/NTN. yer Reg No. doesn’t match. Please provide valid',
            'guidance' => 'Verify that the buyer is registered for sales tax.',
            'solution' => 'FBR Message: tax. Please provide a valid pr registration/NTN. Bu. Check values and retry.',
        ],
        '0107' => [
            'category' => 'BUYER',
            'official' => 'Mismatch Buyer Registration No. Bu Se',
            'brief' => 'yer Registration Number ller Reg No. is not valid. Please provide valid Seller',
            'guidance' => 'Verify the buyer NTN and registration details.',
            'solution' => 'FBR Message: Mismatch Buyer Registration No. Bu Se. Check values and retry.',
        ],
        '0108' => [
            'category' => 'AUTHENTICATION',
            'official' => 'Invalid Seller Registration No or NTN Re Wrong invoice type is selected in        In',
            'brief' => 'gistration Number/NTN voice type is not selected properly, please select',
            'guidance' => 'Provide a valid 7 or 8 digit seller NTN in Company Details.',
            'solution' => 'Seller registration number ya NTN ghalat hai. Company settings mein sahi NTN darj karein.',
        ],
        '0109' => [
            'category' => 'INVOICE_HEADER',
            'official' => 'invoice no                               pr Wrong purchase type is selected          Pu',
            'brief' => 'oper invoice type rchase type is not selected properly, please provide',
            'guidance' => 'Invoice type mismatch: verify whether this is a standard sale invoice or debit note.',
            'solution' => 'Invoice type ghalat select hui hai. Debit note ya normal invoice check karein.',
        ],
        '0111' => [
            'category' => 'ITEM_REFERENCE',
            'official' => 'with invoice no                          pr System is unable to parse date. Da',
            'brief' => 'oper purchase type te is not in proper format, please provide date in',
            'guidance' => 'Electricity distribution sector requires KWH as the Unit of Measurement.',
            'solution' => 'Electricity distribution ke liye UOM lazmi KWH hona chahiye.',
        ],
        '0113' => [
            'category' => 'INVOICE_HEADER',
            'official' => 'Please provide date in valid format "Y dd-MMM-yy. Di no Provided decimal value is not valid at   it',
            'brief' => 'YYY-MM-DD" format. For example: 2025-05-25 scount Value is not valid at item 1 | Total Value is t valid at item 1 | Fed Payable Value is not valid at em 1 | Extra Tax Value is not valid at item 1 | Further',
            'guidance' => 'Provide a valid FBR sandbox scenario ID (e.g. SN001 to SN028).',
            'solution' => 'Sandbox testing ke liye valid Scenario ID (jaise SN001, SN002) lazmi hai.',
        ],
        '0156' => [
            'category' => 'PURCHASE',
            'official' => 'Invalid NTN / Reg No. provided. N The Buyer is not registered for sales T',
            'brief' => 'TN/Reg. No. he Buyer is not registered for sales tax. Please',
            'guidance' => 'Annexure A Purchase validation error (0156): check purchase details against FBR Annexure C.',
            'solution' => 'Purchase invoice error 0156: Supplier NTN, purchase date, ya tax withheld check karein.',
        ],
        '0157' => [
            'category' => 'PURCHASE',
            'official' => 'tax. Please provide a valid p registration/NTN.. B',
            'brief' => 'rovide valid Registration/NTN. uyer Reg No. doesn’t match. Please provide valid',
            'guidance' => 'Annexure A Purchase validation error (0157): check purchase details against FBR Annexure C.',
            'solution' => 'Purchase invoice error 0157: Supplier NTN, purchase date, ya tax withheld check karein.',
        ],
        '0158' => [
            'category' => 'PURCHASE',
            'official' => 'Mismatch Buyer Registration No. B FTN holder as seller not allowed for',
            'brief' => 'uyer Registration Number',
            'guidance' => 'Annexure A Purchase validation error (0158): check purchase details against FBR Annexure C.',
            'solution' => 'Purchase invoice error 0158: Supplier NTN, purchase date, ya tax withheld check karein.',
        ],
        '0159' => [
            'category' => 'PURCHASE',
            'official' => 'F purchases. B',
            'brief' => 'TN Holder as Seller is not allowed for purchases uyer Name cannot be empty, please provide valid',
            'guidance' => 'Annexure A Purchase validation error (0159): check purchase details against FBR Annexure C.',
            'solution' => 'Purchase invoice error 0159: Supplier NTN, purchase date, ya tax withheld check karein.',
        ],
        '0160' => [
            'category' => 'PURCHASE',
            'official' => 'Provide Buyer Name. b Invoice Date must be greater or           I',
            'brief' => 'uyer name nvoice Date must be greater or equal to original sale',
            'guidance' => 'Annexure A Purchase validation error (0160): check purchase details against FBR Annexure C.',
            'solution' => 'Purchase invoice error 0160: Supplier NTN, purchase date, ya tax withheld check karein.',
        ],
        '0161' => [
            'category' => 'PURCHASE',
            'official' => 'equal to {0}                              i S',
            'brief' => 'nvoice date ale Type cannot be empty/Invalid, please provide',
            'guidance' => 'Annexure A Purchase validation error (0161): check purchase details against FBR Annexure C.',
            'solution' => 'Purchase invoice error 0161: Supplier NTN, purchase date, ya tax withheld check karein.',
        ],
        '0162' => [
            'category' => 'PURCHASE',
            'official' => 'Provide Sale Type. v Selected Sale Type are not allowed',
            'brief' => 'alid Sale Type',
            'guidance' => 'Annexure A Purchase validation error (0162): check purchase details against FBR Annexure C.',
            'solution' => 'Purchase invoice error 0162: Supplier NTN, purchase date, ya tax withheld check karein.',
        ],
        '0163' => [
            'category' => 'PURCHASE',
            'official' => 'P to Manufacturer. For selected HSCode only KWH UOM',
            'brief' => 'rovided Sale Type is not allowed for Manufacturer.',
            'guidance' => 'Annexure A Purchase validation error (0163): check purchase details against FBR Annexure C.',
            'solution' => 'Purchase invoice error 0163: Supplier NTN, purchase date, ya tax withheld check karein.',
        ],
        '0164' => [
            'category' => 'PURCHASE',
            'official' => 'F is allowed.',
            'brief' => 'or provided HS Code, only KWH UOM is allowed',
            'guidance' => 'Annexure A Purchase validation error (0164): check purchase details against FBR Annexure C.',
            'solution' => 'Purchase invoice error 0164: Supplier NTN, purchase date, ya tax withheld check karein.',
        ],
        '0165' => [
            'category' => 'PURCHASE',
            'official' => 'Provide UOM KG.                           P Please provide Quantity / Electricity     Q',
            'brief' => 'lease provide UOM in KG uantity / Electricity Unit cannot be empty, please',
            'guidance' => 'Annexure A Purchase validation error (0165): check purchase details against FBR Annexure C.',
            'solution' => 'Purchase invoice error 0165: Supplier NTN, purchase date, ya tax withheld check karein.',
        ],
        '0166' => [
            'category' => 'PURCHASE',
            'official' => 'Units                                     p V',
            'brief' => 'rovide valid Quantity / Electricity Unit alue of Sales Excl. ST cannot be empty/Invalid, please',
            'guidance' => 'Annexure A Purchase validation error (0166): check purchase details against FBR Annexure C.',
            'solution' => 'Purchase invoice error 0166: Supplier NTN, purchase date, ya tax withheld check karein.',
        ],
        '0167' => [
            'category' => 'PURCHASE',
            'official' => 'Provide Value of Sales Excl. ST p Cotton Ginners allowed against            O',
            'brief' => 'rovide valid Value of Sales Excl. ST nly cotton ginner purchase type is allowed for',
            'guidance' => 'Annexure A Purchase validation error (0167): check purchase details against FBR Annexure C.',
            'solution' => 'Purchase invoice error 0167: Supplier NTN, purchase date, ya tax withheld check karein.',
        ],
        '0168' => [
            'category' => 'PURCHASE',
            'official' => 'registered buyers only.                   r STWH can only be created for              U',
            'brief' => 'egistered users. ser is not FTN holder, STWH can only be created for',
            'guidance' => 'Annexure A Purchase validation error (0168): check purchase details against FBR Annexure C.',
            'solution' => 'Purchase invoice error 0168: Supplier NTN, purchase date, ya tax withheld check karein.',
        ],
        '0169' => [
            'category' => 'PURCHASE',
            'official' => 'GOV/FTN Holders.                          G If Value of Sales Excl. ST greater than   I',
            'brief' => 'OV/FTN Holders without purchase invoice. f Value of Sales Excl. ST greater than 20000 than rate',
            'guidance' => 'Annexure A Purchase validation error (0169): check purchase details against FBR Annexure C.',
            'solution' => 'Purchase invoice error 0169: Supplier NTN, purchase date, ya tax withheld check karein.',
        ],
        '0170' => [
            'category' => 'PURCHASE',
            'official' => '{0}. Rate {1} not allowed.                5 You are not an EFS license holder Y',
            'brief' => '% is not allowed. ou are not an EFS license holder who has imported',
            'guidance' => 'Annexure A Purchase validation error (0170): check purchase details against FBR Annexure C.',
            'solution' => 'Purchase invoice error 0170: Supplier NTN, purchase date, ya tax withheld check karein.',
        ],
        '0171' => [
            'category' => 'PURCHASE',
            'official' => 'who has imported Compressor Scrap C in the last 12 months. Petroleum Levy rates not configured       P',
            'brief' => 'ompressor Scrap in the last 12 months. etroleum Levy rates not configured properly. Please',
            'guidance' => 'Annexure A Purchase validation error (0171): check purchase details against FBR Annexure C.',
            'solution' => 'Purchase invoice error 0171: Supplier NTN, purchase date, ya tax withheld check karein.',
        ],
        '0172' => [
            'category' => 'PURCHASE',
            'official' => 'properly.                                 u Alphanumeric and (-) contained            I',
            'brief' => 'pdate levy rates properly nvoice number is not valid, please provide valid',
            'guidance' => 'Annexure A Purchase validation error (0172): check purchase details against FBR Annexure C.',
            'solution' => 'Purchase invoice error 0172: Supplier NTN, purchase date, ya tax withheld check karein.',
        ],
        '0173' => [
            'category' => 'PURCHASE',
            'official' => 'invoice No. is allowed. (-) should be     i in between Alphanumeric string.           I S',
            'brief' => 'nvoice number in alphanumeric format. For example: nv-001 ales Tax cannot be empty, please provide valid Sales',
            'guidance' => 'Annexure A Purchase validation error (0173): check purchase details against FBR Annexure C.',
            'solution' => 'Purchase invoice error 0173: Supplier NTN, purchase date, ya tax withheld check karein.',
        ],
        '0174' => [
            'category' => 'PURCHASE',
            'official' => 'Please provide Sales Tax T F Please provide Fixed / notified value',
            'brief' => 'ax ixed / notified value or Retail Price cannot be empty,',
            'guidance' => 'Annexure A Purchase validation error (0174): check purchase details against FBR Annexure C.',
            'solution' => 'Purchase invoice error 0174: Supplier NTN, purchase date, ya tax withheld check karein.',
        ],
        '0175' => [
            'category' => 'PURCHASE',
            'official' => 'p or Retail Price P',
            'brief' => 'lease provide valid Fixed / notified value or Retail rice',
            'guidance' => 'Annexure A Purchase validation error (0175): check purchase details against FBR Annexure C.',
            'solution' => 'Purchase invoice error 0175: Supplier NTN, purchase date, ya tax withheld check karein.',
        ],
        '0176' => [
            'category' => 'PURCHASE',
            'official' => 'Please provide ST withheld at Source p F',
            'brief' => 'rovide valid ST withheld at Source urther Tax cannot be empty, please provide valid',
            'guidance' => 'Annexure A Purchase validation error (0176): check purchase details against FBR Annexure C.',
            'solution' => 'Purchase invoice error 0176: Supplier NTN, purchase date, ya tax withheld check karein.',
        ],
        '0177' => [
            'category' => 'PURCHASE',
            'official' => 'Please provide Further Tax f N',
            'brief' => 'urther tax TN/Reg. No is invalid/Null, please provide valid',
            'guidance' => 'Annexure A Purchase validation error (0177): check purchase details against FBR Annexure C.',
            'solution' => 'Purchase invoice error 0177: Supplier NTN, purchase date, ya tax withheld check karein.',
        ],
        '0300' => [
            'category' => 'CALCULATION',
            'official' => 'field                                    Ta Sa | Una The provided seller NTN/CNIC does num',
            'brief' => 'x Value is not valid at item 1 | lesTaxWithheldAtSource Value is not valid at item 1 Quantity Value is not valid at item 1 uthorized access: Provided seller registration ber is not 13 digits (CNIC) or 7 digits (NTN) or the',
            'guidance' => 'Review numeric formats and supported decimal precision.',
            'solution' => 'FBR Message: field                                    Ta Sa | Una The provided seller NTN/CNIC does num. Check values and retry.',
        ],
        '0401' => [
            'category' => 'AUTHENTICATION',
            'official' => 'not have a valid or authorized access aut token reg Una The provided buyer NTN/CNIC does num',
            'brief' => 'horized token does not exist against seller istration number uthorized access: Provided buyer registration ber is not 13 digits (CNIC) or 7 digits (NTN) or the',
            'guidance' => 'Check seller NTN, token ownership, and IP approval.',
            'solution' => 'FBR Message: not have a valid or authorized access aut token reg Una The provided buyer NTN/CNIC does num. Check values and retry.',
        ],
        '0402' => [
            'category' => 'BUYER',
            'official' => 'not have a valid or authorized access aut token reg',
            'brief' => 'horized token does not exist against buyer istration number',
            'guidance' => 'Provided buyer NTN/CNIC is unauthorized or invalid for this invoice type.',
            'solution' => 'Buyer NTN/CNIC FBR rules ke mutabiq authorized nahi hai.',
        ],
    ];

    /**
     * Preserved default guidance strings for backwards compatibility and test assertions.
     *
     * @var array<string, string>
     */
    private const BASELINE_GUIDANCE = [
        '0046' => 'Reload and select an FBR-approved rate for the selected sale type.',
        '0052' => 'Review the item HS code and sale type combination.',
        '0053' => 'Verify the buyer registration type with FBR.',
        '0082' => 'Verify that the seller is registered for sales tax.',
        '0083' => 'Ensure the seller NTN matches the owner of the configured token.',
        '0099' => 'Select a UOM returned by FBR for this HS code.',
        '0104' => 'Recalculate sales tax using the FBR-approved rate.',
        '0106' => 'Verify that the buyer is registered for sales tax.',
        '0107' => 'Verify the buyer NTN and registration details.',
        '0300' => 'Review numeric formats and supported decimal precision.',
        '0401' => 'Check seller NTN, token ownership, and IP approval.',
    ];

    /**
     * Returns actionable guidance for an FBR error code with optional sector context.
     */
    public function guidance(?string $code, ?string $sector = null, ?string $businessActivity = null): ?string
    {
        if ($code === null) {
            return null;
        }

        // Sector-specific contextual enhancements
        if ($sector !== null) {
            $normalizedSector = strtolower(trim($sector));
            if ($code === '0102' && str_contains($normalizedSector, 'fmcg')) {
                return 'FMCG 3rd Schedule Goods: Sales tax must be calculated on printed Retail Price multiplied by quantity, not on discounted net sale value.';
            }
            if (($code === '0056' || $code === '0057') && str_contains($normalizedSector, 'steel')) {
                return 'Steel Sector Rule: Use "Toll Manufacturing" sale type or ensure buyer has an active Steel Sector registration on FBR.';
            }
            if ($code === '0100' && str_contains($normalizedSector, 'textile')) {
                return 'Textile Sector Rule: Cotton Ginner sale type is only permitted for registered buyers. Verify buyer registration.';
            }
        }

        if (isset(self::BASELINE_GUIDANCE[$code])) {
            return self::BASELINE_GUIDANCE[$code];
        }

        return self::CATALOG[$code]['guidance'] ?? null;
    }

    /**
     * Get the full structured dictionary entry for an error code.
     *
     * @return array{code: string, category: string, official: string, brief: string, guidance: string, solution: string}|null
     */
    public function entry(?string $code, ?string $sector = null, ?string $businessActivity = null): ?array
    {
        if ($code === null || ! isset(self::CATALOG[$code])) {
            return null;
        }

        $item = self::CATALOG[$code];
        $contextGuidance = $this->guidance($code, $sector, $businessActivity);

        return [
            'code' => $code,
            'category' => $item['category'],
            'official' => $item['official'],
            'brief' => $item['brief'],
            'guidance' => $contextGuidance ?? $item['guidance'],
            'solution' => $item['solution'],
        ];
    }

    public function find(?string $code): ?array
    {
        return $this->entry($code);
    }

    public function has(string $code): bool
    {
        return isset(self::CATALOG[$code]);
    }

    public function category(?string $code): string
    {
        return $code !== null && isset(self::CATALOG[$code]) ? self::CATALOG[$code]['category'] : 'UNKNOWN';
    }

    /**
     * @return array<string, array{category: string, official: string, brief: string, guidance: string, solution: string}>
     */
    public function all(): array
    {
        return self::CATALOG;
    }

    /**
     * @return array<int, string>
     */
    public function categories(): array
    {
        return ['AUTHENTICATION', 'BUYER', 'INVOICE_HEADER', 'ITEM_REFERENCE', 'CALCULATION', 'SRO_SCHEDULE', 'PURCHASE'];
    }
}
