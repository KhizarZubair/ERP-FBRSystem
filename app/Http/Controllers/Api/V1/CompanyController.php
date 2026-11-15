<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CompanyRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\CreateCompanyRequest;
use App\Http\Requests\Company\UpdateCompanyRequest;
use App\Http\Resources\Auth\CompanyResource;
use App\Http\Resources\CompanyDetailsResource;
use App\Services\CompanyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
    public function __construct(private CompanyService $companies) {}

    public function index(Request $request): JsonResponse
    {
        $companies = $this->companies->accessibleCompanies($request->user());

        return CompanyResource::collection($companies)->response()->setStatusCode(200);
    }

    public function show(Request $request, int $company): JsonResponse
    {
        return (new CompanyDetailsResource($this->companies->details($request->user(), $company)))->response()->setStatusCode(200);
    }

    public function store(CreateCompanyRequest $request): JsonResponse
    {
        return (new CompanyDetailsResource($this->companies->createAdditionalCompany(
            $request->user(), $request->validated()
        )))->response()->setStatusCode(201);
    }

    /**
     * Enroll a new client company with representative login credentials (REQ-01).
     */
    public function enroll(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403, 'Only Super Admin / Tax Practitioner can enroll client companies.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'seller_ntn' => ['required_without:seller_cnic', 'nullable', 'string', 'regex:/^[0-9]{7,8}$/'],
            'seller_cnic' => ['required_without:seller_ntn', 'nullable', 'string', 'regex:/^[0-9]{13}$/'],
            'province' => ['required', Rule::in(config('erp.provinces'))],
            'address' => ['required', 'string', 'max:2000'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+(). -]+$/'],
            'business_activity' => ['required', 'array', 'min:1'],
            'business_activity.*' => ['required', 'string', 'distinct', Rule::in(config('fbr.business_activities'))],
            'sector' => ['required', 'array', 'min:1'],
            'sector.*' => ['required', 'string', 'distinct', Rule::in(config('fbr.sectors'))],
            'is_sales_tax_profile_retailer' => ['sometimes', 'boolean'],
            'sandbox_token' => ['nullable', 'string', 'max:4096'],
            'production_token' => ['nullable', 'string', 'max:4096'],

            // Client user representative fields
            'client_name' => ['nullable', 'string', 'max:255'],
            'client_email' => ['nullable', 'email', 'max:255'],
            'client_password' => ['nullable', 'string', 'min:6'],
            'client_phone' => ['nullable', 'string', 'max:30'],
        ]);

        $clientUserData = filled($data['client_email'] ?? null) ? [
            'name' => $data['client_name'] ?? $data['name'].' Representative',
            'email' => $data['client_email'],
            'password' => $data['client_password'] ?? 'Welcome@123',
            'phone' => $data['client_phone'] ?? $data['phone'] ?? null,
        ] : null;

        $result = $this->companies->enrollCompanyWithUser($request->user(), $data, $clientUserData);

        return response()->json([
            'message' => 'Client company enrolled successfully.',
            'company' => new CompanyDetailsResource($result['company']),
            'client_user' => $result['client_user'] ? [
                'id' => $result['client_user']->id,
                'name' => $result['client_user']->name,
                'email' => $result['client_user']->email,
            ] : null,
        ], 201);
    }

    /**
     * 1-Click Direct Login / Impersonate Workspace (REQ-01).
     */
    public function switchWorkspace(Request $request, int $company): JsonResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403, 'Only Super Admin / Tax Practitioner can switch workspaces.');

        $companyDetails = $this->companies->details($request->user(), $company);

        return response()->json([
            'switched' => true,
            'message' => 'Switched to company '.$companyDetails->name,
            'company' => new CompanyDetailsResource($companyDetails),
        ]);
    }

    public function members(Request $request, int $company): JsonResponse
    {
        $members = $this->companies->members($request->user(), $company)->map(fn ($user): array => $this->memberData($user));

        return response()->json(['data' => $members]);
    }

    public function addMember(Request $request, int $company): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::enum(CompanyRole::class)],
        ]);
        $member = $this->companies->addMember($request->user(), $company, $data['email'], CompanyRole::from($data['role']));

        return response()->json(['data' => $this->memberData($member)], 201);
    }

    public function updateMember(Request $request, int $company, int $user): JsonResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::enum(CompanyRole::class)], 'is_active' => ['required', 'boolean'],
        ]);
        $member = $this->companies->updateMembership(
            $request->user(), $company, $user, CompanyRole::from($data['role']), $data['is_active']
        );

        return response()->json(['data' => $this->memberData($member)]);
    }

    public function removeMember(Request $request, int $company, int $user): JsonResponse
    {
        $this->companies->removeMember($request->user(), $company, $user);

        return response()->json(['message' => 'Company member removed.']);
    }

    private function memberData($user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email,
            'role' => $user->pivot->role->value, 'is_active' => $user->pivot->is_active];
    }

    public function update(UpdateCompanyRequest $request, int $company): JsonResponse
    {
        return (new CompanyDetailsResource($this->companies->updateDetails($request->user(), $company, $request->validated())))->response()->setStatusCode(200);
    }

    public function toggleStatus(Request $request, int $company): JsonResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403, 'Only Super Admin can toggle company status.');

        $updated = $this->companies->toggleCompanyStatus($request->user(), $company);

        return response()->json([
            'message' => 'Company status updated successfully.',
            'data' => new CompanyResource($updated),
        ]);
    }

    public function destroy(Request $request, int $company): JsonResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403, 'Only Super Admin can delete a company.');

        $this->companies->deleteCompany($request->user(), $company);

        return response()->json(['message' => 'Company soft-deleted successfully.']);
    }
}
