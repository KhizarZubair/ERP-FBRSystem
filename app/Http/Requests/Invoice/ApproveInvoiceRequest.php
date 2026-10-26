<?php

namespace App\Http\Requests\Invoice;

use App\Services\CustomerService;
use Illuminate\Foundation\Http\FormRequest;

class ApproveInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $this->attributes->set('workspace', app(CustomerService::class)->company($this->user(), (int) $this->route('company'), true));

        return true;
    }

    public function rules(): array
    {
        return ['version' => ['required', 'string', 'size:64'], 'confirmed' => ['required', 'accepted']];
    }
}
