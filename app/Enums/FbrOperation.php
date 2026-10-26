<?php

namespace App\Enums;

enum FbrOperation: string
{
    case Validate = 'validate';
    case Submit = 'submit';
}
