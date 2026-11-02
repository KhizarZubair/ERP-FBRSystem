<?php

namespace Tests\Unit;

use App\Http\Requests\Item\SaveItemRequest;
use App\Http\Resources\ItemResource;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ItemConfigurationTest extends TestCase
{
    public function test_item_model_fillable_and_casts_support_fbr_sector_fields(): void
    {
        $item = new Item([
            'sku' => 'FMCG-TEA-001',
            'name' => 'Premium Black Tea 900g',
            'description' => 'FMCG 3rd Schedule Goods',
            'hs_code' => '0902.3000',
            'unit_of_measurement' => 'KG',
            'sale_type' => 'Third Schedule Goods',
            'tax_rate_label' => '18%',
            'tax_rate_percent' => '18.000000',
            'fixed_notified_value_or_retail_price' => '1450.500000',
            'sro_schedule_no' => 'Third Schedule',
            'sro_item_serial_no' => 'Item 14',
            'extra_tax' => '50.000000',
            'fed_payable' => '25.000000',
            'track_stock' => true,
            'reorder_level' => '100.000000',
            'is_active' => true,
        ]);

        $this->assertEquals('1450.500000', $item->fixed_notified_value_or_retail_price);
        $this->assertEquals('Third Schedule', $item->sro_schedule_no);
        $this->assertEquals('Item 14', $item->sro_item_serial_no);
        $this->assertEquals('50.000000', $item->extra_tax);
        $this->assertEquals('25.000000', $item->fed_payable);
    }

    public function test_save_item_request_accepts_valid_retail_price_and_sro_fields(): void
    {
        $rules = (new SaveItemRequest)->rules();
        unset($rules['sku']);

        $validator = Validator::make([
            'name' => 'Life Saving Drug',
            'description' => 'Exempt Pharmaceutical Item under 8th Schedule',
            'hs_code' => '3004.9000',
            'unit_of_measurement' => 'Numbers',
            'sale_type' => 'Goods at Reduced Rate',
            'tax_rate_label' => '1%',
            'tax_rate_percent' => '1.000000',
            'fixed_notified_value_or_retail_price' => '350.000000',
            'sro_schedule_no' => 'Eighth Schedule, Table-1',
            'sro_item_serial_no' => 'Serial 45',
            'extra_tax' => '0',
            'fed_payable' => '0',
            'track_stock' => true,
            'reorder_level' => '10',
            'is_active' => true,
        ], $rules);

        $this->assertFalse($validator->errors()->has('fixed_notified_value_or_retail_price'));
        $this->assertFalse($validator->errors()->has('sro_schedule_no'));
        $this->assertFalse($validator->errors()->has('sro_item_serial_no'));
        $this->assertFalse($validator->errors()->has('extra_tax'));
        $this->assertFalse($validator->errors()->has('fed_payable'));
    }

    public function test_save_item_request_rejects_negative_retail_price_or_tax(): void
    {
        $rules = (new SaveItemRequest)->rules();
        unset($rules['sku']);

        $validator = Validator::make([
            'name' => 'Bad Pricing Item',
            'description' => 'Negative retail price',
            'hs_code' => '0101.1000',
            'unit_of_measurement' => 'KG',
            'sale_type' => 'Standard Rate',
            'tax_rate_label' => '18%',
            'tax_rate_percent' => '18',
            'fixed_notified_value_or_retail_price' => '-50.00',
            'extra_tax' => '-10.00',
            'fed_payable' => '-5.00',
            'track_stock' => true,
            'reorder_level' => '1',
            'is_active' => true,
        ], $rules);

        $this->assertTrue($validator->errors()->has('fixed_notified_value_or_retail_price'));
        $this->assertTrue($validator->errors()->has('extra_tax'));
        $this->assertTrue($validator->errors()->has('fed_payable'));
    }

    public function test_save_item_request_prepares_and_normalizes_empty_sro_to_null(): void
    {
        $request = new SaveItemRequest;
        $request->merge([
            'sro_schedule_no' => '   ',
            'sro_item_serial_no' => '',
            'fixed_notified_value_or_retail_price' => '',
            'extra_tax' => '',
            'fed_payable' => '',
        ]);

        $reflection = new \ReflectionClass($request);
        $method = $reflection->getMethod('prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        $this->assertNull($request->input('sro_schedule_no'));
        $this->assertNull($request->input('sro_item_serial_no'));
        $this->assertEquals('0', $request->input('fixed_notified_value_or_retail_price'));
        $this->assertEquals('0', $request->input('extra_tax'));
        $this->assertEquals('0', $request->input('fed_payable'));
    }

    public function test_item_resource_serializes_fbr_sector_specific_fields(): void
    {
        $item = new Item([
            'sku' => 'FMCG-SOAP-100',
            'name' => 'Beauty Soap Bar 140g',
            'description' => 'FMCG Goods under 3rd schedule',
            'hs_code' => '3401.1100',
            'unit_of_measurement' => 'Numbers',
            'sale_type' => 'Third Schedule Goods',
            'tax_rate_label' => '18%',
            'tax_rate_percent' => '18.000000',
            'fixed_notified_value_or_retail_price' => '180.000000',
            'sro_schedule_no' => 'Third Schedule',
            'sro_item_serial_no' => 'Row 3',
            'extra_tax' => '0.000000',
            'fed_payable' => '0.000000',
            'track_stock' => true,
            'reorder_level' => '50.000000',
            'is_active' => true,
        ]);
        $item->id = 42;

        $resource = new ItemResource($item);
        $data = $resource->toArray(Request::create('/api/v1/companies/1/items/42'));

        $this->assertEquals(42, $data['id']);
        $this->assertEquals('180.000000', $data['fixed_notified_value_or_retail_price']);
        $this->assertEquals('Third Schedule', $data['sro_schedule_no']);
        $this->assertEquals('Row 3', $data['sro_item_serial_no']);
        $this->assertEquals('0.000000', $data['extra_tax']);
        $this->assertEquals('0.000000', $data['fed_payable']);
    }
}
