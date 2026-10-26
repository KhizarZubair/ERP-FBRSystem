<?php

namespace App\Services;

class FbrErrorCatalog
{
    /** @var array<string, string> */
    private const GUIDANCE = [
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

    public function guidance(?string $code): ?string
    {
        return $code === null ? null : (self::GUIDANCE[$code] ?? null);
    }
}
