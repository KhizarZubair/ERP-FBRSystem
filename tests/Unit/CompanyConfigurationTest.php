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

    public function test_all_thirteen_official_fbr_sectors_are_configured(): void
    {
        $sectors = config('fbr.sectors');

        $this->assertCount(13, $sectors);
        $this->assertEqualsCanonicalizing([
            'All Other Sectors',
            'Steel',
            'FMCG',
            'Textile',
            'Telecom',
            'Petroleum',
            'Electricity Distribution',
            'Gas Distribution',
            'Services',
            'Automobile',
            'CNG Stations',
            'Pharmaceuticals',
            'Wholesale / Retails',
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
                'business_activity' => $activity,
                'sector' => 'FMCG',
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
            'business_activity' => 'Astronaut',
            'sector' => 'Crypto Mining',
        ], $rules);

        $this->assertTrue($validator->errors()->has('business_activity'));
        $this->assertTrue($validator->errors()->has('sector'));
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
            'business_activity' => 'Retailer',
            'sector' => 'Steel',
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

        $company->business_activity = 'Retailer';
        $company->sector = 'FMCG';

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
}
