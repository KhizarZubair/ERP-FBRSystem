<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CompanyRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\CreateCompanyRequest;
use App\Http\Requests\Company\UpdateCompanyRequest;
use App\Http\Resources\CompanyDetailsResource;
use App\Services\CompanyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
    public function __construct(private CompanyService $companies) {}

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
}
