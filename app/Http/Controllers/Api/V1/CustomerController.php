<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\ListCustomersRequest;
use App\Http\Requests\Customer\SaveCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerStatusRequest;
use App\Http\Resources\CustomerResource;
use App\Services\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(private CustomerService $customers) {}

    public function index(ListCustomersRequest $request, int $company): JsonResponse
    {
        return CustomerResource::collection($this->customers->listing($request->attributes->get('customer_company'), $request->validated()))->response();
    }

    public function show(Request $request, int $company, int $customer): JsonResponse
    {
        $workspace = $this->customers->company($request->user(), $company);

        return (new CustomerResource($this->customers->find($workspace, $customer)))->response();
    }

    public function store(SaveCustomerRequest $request, int $company): JsonResponse
    {
        return (new CustomerResource($this->customers->save($request->attributes->get('customer_company'), $request->validated())))->response()->setStatusCode(201);
    }

    public function update(SaveCustomerRequest $request, int $company, int $customer): JsonResponse
    {
        return (new CustomerResource($this->customers->save($request->attributes->get('customer_company'), $request->validated(), $customer)))->response();
    }

    public function status(UpdateCustomerStatusRequest $request, int $company, int $customer): JsonResponse
    {
        return (new CustomerResource($this->customers->status($request->attributes->get('customer_company'), $customer, $request->boolean('is_active'))))->response();
    }

    public function destroy(Request $request, int $company, int $customer): JsonResponse
    {
        $workspace = $this->customers->company($request->user(), $company, true);
        $this->customers->delete($workspace, $customer);

        return response()->json(['message' => 'Customer deleted successfully.']);
    }
}
