<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->requireSupportedDatabase();

        foreach ($this->constraints() as $table => $constraints) {
            foreach ($constraints as $name => $expression) {
                DB::statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$name}` CHECK ({$expression})");
            }
        }
    }

    public function down(): void
    {
        $this->requireSupportedDatabase();

        foreach (array_reverse($this->constraints(), true) as $table => $constraints) {
            foreach (array_reverse($constraints, true) as $name => $expression) {
                DB::statement("ALTER TABLE `{$table}` {$this->dropConstraintClause()} `{$name}`");
            }
        }
    }

    private function requireSupportedDatabase(): void
    {
        $connection = DB::connection();
        if ($connection->getDriverName() !== 'mysql') {
            throw new RuntimeException('ERP constraints require MySQL 8.0.16 or newer, or MariaDB 10.2.1 or newer.');
        }

        $version = (string) $connection->getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION);
        preg_match('/^\\d+\\.\\d+\\.\\d+/', $version, $matches);
        if (! isset($matches[0])) {
            throw new RuntimeException('Unsupported database server version: '.$version);
        }

        $minimumVersion = $this->isMariaDb() ? '10.2.1' : '8.0.16';
        if (version_compare($matches[0], $minimumVersion, '<')) {
            throw new RuntimeException('ERP constraints require MySQL 8.0.16 or newer, or MariaDB 10.2.1 or newer; unsupported server: '.$version);
        }
    }

    private function isMariaDb(): bool
    {
        $version = (string) DB::connection()->getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION);

        return stripos($version, 'MariaDB') !== false;
    }

    private function dropConstraintClause(): string
    {
        return $this->isMariaDb() ? 'DROP CONSTRAINT' : 'DROP CHECK';
    }

    /** @return array<string, array<string, string>> */
    private function constraints(): array
    {
        return [
            'companies' => [
                'companies_fbr_environment_check' => 'BINARY `fbr_environment` IN (\'sandbox\', \'production\')',
                'companies_sequence_check' => '`next_invoice_number` > 0',
            ],
            'company_user' => [
                'company_user_role_check' => 'BINARY `role` IN (\'owner\', \'member\')',
            ],
            'customers' => [
                'customers_registration_type_check' => 'BINARY `registration_type` IN (\'Registered\', \'Unregistered\')',
            ],
            'invoices' => [
                'invoices_status_check' => 'BINARY `status` IN (\'draft\', \'approved\', \'issued\', \'cancelled\')',
                'invoices_fbr_status_check' => 'BINARY `fbr_status` IN (\'not_submitted\', \'pending\', \'validation_failed\', \'accepted\', \'rejected\', \'unknown\')',
                'invoices_fbr_environment_check' => 'BINARY `fbr_environment` IN (\'sandbox\', \'production\')',
                'invoices_buyer_registration_type_check' => 'BINARY `buyer_registration_type` IN (\'Registered\', \'Unregistered\')',
                'invoices_due_date_check' => '`due_date` IS NULL OR `due_date` >= `invoice_date`',
                'invoices_nonnegative_check' => '`value_sales_excluding_st` >= 0 AND `sales_tax_applicable` >= 0 AND `sales_tax_withheld_at_source` >= 0 AND `extra_tax` >= 0 AND `further_tax` >= 0 AND `fed_payable` >= 0 AND `discount` >= 0 AND `total_values` >= 0',
            ],
            'fbr_integrations' => [
                'fbr_integrations_environment_check' => 'BINARY `environment` IN (\'sandbox\', \'production\')',
                'fbr_integrations_status_check' => 'BINARY `status` IN (\'not_configured\', \'configured\', \'verified\', \'disabled\')',
            ],
            'fbr_submissions' => [
                'fbr_submissions_environment_check' => 'BINARY `environment` IN (\'sandbox\', \'production\')',
                'fbr_submissions_status_check' => 'BINARY `status` IN (\'pending\', \'processing\', \'accepted\', \'rejected\', \'failed\', \'unknown\')',
                'fbr_submissions_operation_check' => 'BINARY `operation` IN (\'validate\', \'submit\')',
                'fbr_submissions_attempt_check' => '`attempt_number` > 0',
                'fbr_submissions_http_check' => '`http_status` IS NULL OR `http_status` BETWEEN 100 AND 599',
            ],
            'stock_movements' => [
                'stock_movements_movement_type_check' => 'BINARY `movement_type` IN (\'opening\', \'receipt\', \'issue\', \'adjustment\', \'reversal\')',
                'stock_movements_quantity_check' => '`quantity` <> 0 AND ((BINARY `movement_type` IN (\'opening\', \'receipt\') AND `quantity` > 0) OR (BINARY `movement_type` = \'issue\' AND `quantity` < 0) OR BINARY `movement_type` IN (\'adjustment\', \'reversal\'))',
            ],
            'items' => [
                'items_tax_rate_check' => '`tax_rate_percent` IS NULL OR `tax_rate_percent` BETWEEN 0 AND 100',
                'items_nonnegative_check' => '`reorder_level` >= 0 AND `fixed_notified_value_or_retail_price` >= 0',
            ],
            'item_prices' => [
                'item_prices_nonnegative_check' => '`unit_price` >= 0',
                'item_prices_dates_check' => '`effective_to` IS NULL OR `effective_to` >= `effective_from`',
            ],
            'invoice_items' => [
                'invoice_items_tax_rate_check' => '`tax_rate_percent` IS NULL OR `tax_rate_percent` BETWEEN 0 AND 100',
                'invoice_items_nonnegative_check' => '`unit_price` >= 0 AND `fixed_notified_value_or_retail_price` >= 0 AND `value_sales_excluding_st` >= 0 AND `sales_tax_applicable` >= 0 AND `sales_tax_withheld_at_source` >= 0 AND `extra_tax` >= 0 AND `further_tax` >= 0 AND `fed_payable` >= 0 AND `discount` >= 0 AND `total_values` >= 0',
                'invoice_items_quantity_check' => '`quantity` > 0 AND `line_number` > 0',
                'invoice_items_discount_check' => '`discount` <= `quantity` * `unit_price`',
            ],
            'fbr_submission_items' => [
                'fbr_submission_items_line_check' => '`line_number` > 0',
            ],
        ];
    }
};
