<?php

namespace Tests\Unit;

use App\Http\Requests\Company\CreateCompanyRequest;
use App\Http\Requests\Company\UpdateCompanyRequest;
use App\Models\Company;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class CompanyConfigurationTest extends TestCase
{
    public function test_all_eight_official_fbr_business_activities_are_configured(): void
    {
        $activities = config('fbr.business_activities');

        $this->assertCount(8, $activities);
        $this->assertEqualsCanonicalizing([
            'Manufacturer',
            'Importer',
            'Distributor',
            'Wholesaler',
            'Retailer',
            'Exporter',
            'Service Provider',
            'Other',
        ], $activities);
    }

    public function test_all_sixteen_official_fbr_sectors_are_configured(): void
    {
        $sectors = config('fbr.sectors');

        $this->assertCount(16, $sectors);
        $this->assertEqualsCanonicalizing([
            'Potassium Chlorate',
            'Mobile',
            'Pharmaceuticals',
            'Automobile',
            'Gas Distribution',
            'Petroleum',
            'Textile',
            'Steel',
            'Cement or Concrete Blocks',
            'Wholesale / Retails',
            'CNG Stations',
            'Services',
            'Electricity Distribution',
            'Telecom',
            'FMCG',
            'All Other Sectors',
        ], $sectors);
    }

    public function test_update_company_validation_accepts_all_official_activities_and_sectors(): void
    {
        $rules = (new UpdateCompanyRequest)->rules();

        foreach (['Manufacturer', 'Retailer', 'Exporter', 'Service Provider', 'Wholesaler'] as $activity) {
            $validator = Validator::make([
                'name' => 'Valid Company',
                'seller_ntn' => '1234567',
                'seller_cnic' => '3520112345671',
                'province' => 'Sindh',
                'address' => 'Valid Address',
                'business_activity' => [$activity],
                'sector' => ['FMCG'],
            ], $rules);

            $this->assertFalse($validator->errors()->has('business_activity'), "Activity $activity failed validation");
            $this->assertFalse($validator->errors()->has('sector'));
        }
    }

    public function test_invalid_business_activity_or_sector_is_rejected(): void
    {
        $rules = (new UpdateCompanyRequest)->rules();

        $validator = Validator::make([
            'name' => 'Valid Company',
            'seller_ntn' => '1234567',
            'seller_cnic' => '3520112345671',
            'province' => 'Sindh',
            'address' => 'Valid Address',
            'business_activity' => ['Astronaut'],
            'sector' => ['Crypto Mining'],
        ], $rules);

        $this->assertTrue($validator->errors()->has('business_activity.0'));
        $this->assertTrue($validator->errors()->has('sector.0'));
    }

    public function test_create_company_request_allows_optional_activity_and_sector(): void
    {
        $rules = (new CreateCompanyRequest)->rules();

        $validator = Validator::make([
            'name' => 'New Company',
            'seller_ntn' => '1234567',
            'seller_cnic' => '3520112345671',
            'province' => 'Punjab',
            'address' => 'Factory Area',
            'email' => 'seller@example.com',
            'business_activity' => ['Retailer'],
            'sector' => ['Steel'],
        ], $rules);

        $this->assertFalse($validator->fails());
    }

    public function test_fbr_readiness_rules_require_business_activity_and_sector(): void
    {
        $company = new Company([
            'name' => 'Test Corp',
            'ntn_cnic' => '1234567',
            'province' => 'Sindh',
            'address' => 'Karachi',
        ]);

        $blockers = [];
        foreach (['name' => 'registered business name', 'ntn_cnic' => 'seller NTN/CNIC', 'province' => 'seller province',
            'address' => 'registered address', 'business_activity' => 'business activity', 'sector' => 'sector'] as $field => $label) {
            if (blank($company->$field)) {
                $blockers[] = 'Add '.$label.' in Company Details.';
            }
        }

        $this->assertContains('Add business activity in Company Details.', $blockers);
        $this->assertContains('Add sector in Company Details.', $blockers);

        $company->business_activity = ['Retailer'];
        $company->sector = ['FMCG'];

        $updatedBlockers = [];
        foreach (['name' => 'registered business name', 'ntn_cnic' => 'seller NTN/CNIC', 'province' => 'seller province',
            'address' => 'registered address', 'business_activity' => 'business activity', 'sector' => 'sector'] as $field => $label) {
            if (blank($company->$field)) {
                $updatedBlockers[] = 'Add '.$label.' in Company Details.';
            }
        }

        $this->assertNotContains('Add business activity in Company Details.', $updatedBlockers);
        $this->assertNotContains('Add sector in Company Details.', $updatedBlockers);
    }

    public function test_company_validation_accepts_either_ntn_or_cnic_exclusively(): void
    {
        $rules = (new UpdateCompanyRequest)->rules();

        // 1. Only NTN provided (7 or 8 digits)
        $v1 = Validator::make([
            'name' => 'NTN Only Co',
            'seller_ntn' => '1234567',
            'seller_cnic' => null,
            'province' => 'Sindh',
            'address' => 'Karachi',
        ], $rules);
        $this->assertFalse($v1->fails(), 'Company with only NTN must pass validation');

        // 2. Only CNIC provided (13 digits)
        $v2 = Validator::make([
            'name' => 'CNIC Only Sole Proprietor',
            'seller_ntn' => null,
            'seller_cnic' => '3520112345671',
            'province' => 'Punjab',
            'address' => 'Lahore',
        ], $rules);
        $this->assertFalse($v2->fails(), 'Company with only CNIC must pass validation');

        // 3. Both provided
        $v3 = Validator::make([
            'name' => 'Both Co',
            'seller_ntn' => '1234567',
            'seller_cnic' => '3520112345671',
            'province' => 'Punjab',
            'address' => 'Lahore',
        ], $rules);
        $this->assertFalse($v3->fails(), 'Company with both NTN and CNIC must pass validation');

        // 4. Neither provided -> must fail
        $v4 = Validator::make([
            'name' => 'No Identity Co',
            'seller_ntn' => null,
            'seller_cnic' => null,
            'province' => 'Punjab',
            'address' => 'Lahore',
        ], $rules);
        $this->assertTrue($v4->fails(), 'Company with neither NTN nor CNIC must fail');
        $this->assertTrue($v4->errors()->has('seller_ntn'));
        $this->assertTrue($v4->errors()->has('seller_cnic'));

        // 5. Invalid NTN (less than 7 digits)
        $v5 = Validator::make([
            'name' => 'Bad NTN Co',
            'seller_ntn' => '12345',
            'seller_cnic' => null,
            'province' => 'Punjab',
            'address' => 'Lahore',
        ], $rules);
        $this->assertTrue($v5->fails());
        $this->assertTrue($v5->errors()->has('seller_ntn'));

        // 6. Invalid CNIC (not 13 digits)
        $v6 = Validator::make([
            'name' => 'Bad CNIC Co',
            'seller_ntn' => null,
            'seller_cnic' => '35201123',
            'province' => 'Punjab',
            'address' => 'Lahore',
        ], $rules);
        $this->assertTrue($v6->fails());
        $this->assertTrue($v6->errors()->has('seller_cnic'));
    }
}
