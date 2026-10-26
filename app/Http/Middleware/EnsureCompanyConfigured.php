<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyConfigured
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Company::query()->exists()) {
            return response()->json([
                'message' => 'Please register your company details first before using customers, items, stock, or invoices.',
            ], 422);
        }

        return $next($request);
    }
}
