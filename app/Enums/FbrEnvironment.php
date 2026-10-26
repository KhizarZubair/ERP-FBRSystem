<?php

namespace App\Enums;

enum FbrEnvironment: string
{
    case Sandbox = 'sandbox';
    case Production = 'production';
}
