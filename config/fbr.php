<?php

return [
    // Optional server-side token for taxpayer lookup before a company sandbox token is configured.
    'verification_token' => env('FBR_VERIFICATION_TOKEN'),
    'sandbox_validate_url' => 'https://gw.fbr.gov.pk/di_data/v1/di/validateinvoicedata_sb',
    'sandbox_submit_url' => 'https://gw.fbr.gov.pk/di_data/v1/di/postinvoicedata_sb',
    'production_validate_url' => 'https://gw.fbr.gov.pk/di_data/v1/di/validateinvoicedata',
    'production_submit_url' => 'https://gw.fbr.gov.pk/di_data/v1/di/postinvoicedata',
    'units_url' => 'https://gw.fbr.gov.pk/pdi/v1/uom',
    'provinces_url' => 'https://gw.fbr.gov.pk/pdi/v1/provinces',
    'hs_units_url' => 'https://gw.fbr.gov.pk/pdi/v2/HS_UOM',
    'sale_types_url' => 'https://gw.fbr.gov.pk/pdi/v1/transtypecode',
    'hs_codes_url' => 'https://gw.fbr.gov.pk/pdi/v1/itemdesccode',
    'rates_url' => 'https://gw.fbr.gov.pk/pdi/v2/SaleTypeToRate',
    'sro_schedules_url' => 'https://gw.fbr.gov.pk/pdi/v1/SroSchedule',
    'sro_items_url' => 'https://gw.fbr.gov.pk/pdi/v2/SROItem',
    'statl_url' => 'https://gw.fbr.gov.pk/dist/v1/statl',
    'registration_type_url' => 'https://gw.fbr.gov.pk/dist/v1/Get_Reg_Type',
    // Digital sales invoices are reported in sales return Annexure C (FBR API ID 3).
    'sales_annexure_id' => 3,
    'supported_scenarios' => [
        'SN001' => [
            'description' => 'Goods at standard rate to registered buyers',
            'sale_type' => 'Goods at Standard Rate (default)',
            'buyer_registration_type' => 'Registered',
        ],
        'SN002' => [
            'description' => 'Goods at standard rate to unregistered buyers',
            'sale_type' => 'Goods at Standard Rate (default)',
            'buyer_registration_type' => 'Unregistered',
        ],
    ],
    'business_activities' => ['Manufacturer', 'Importer', 'Distributor', 'Wholesaler'],
    'sectors' => [
        'All Other Sectors', 'Steel', 'FMCG', 'Textile', 'Telecom', 'Petroleum',
        'Electricity Distribution', 'Gas Distribution', 'Services', 'Automobile',
        'CNG Stations', 'Pharmaceuticals', 'Wholesale / Retails',
    ],
    'reference_cache_store' => env('FBR_REFERENCE_CACHE_STORE', 'file'),
    'reference_fresh_seconds' => 86400,
    'reference_retention_seconds' => 604800,
    'reference_retry_seconds' => 60,
];
