<?php

namespace App\Enums;

enum FbrIntegrationStatus: string
{
    case NotConfigured = 'not_configured';
    case Configured = 'configured';
    case Verified = 'verified';
    case Disabled = 'disabled';
}
