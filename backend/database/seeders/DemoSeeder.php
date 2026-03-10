<?php

namespace Database\Seeders;

use App\Models\Contract;
use App\Models\Customer;
use App\Models\DamageReport;
use App\Models\DocumentTemplate;
use App\Models\DunningRecord;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\MailTemplate;
use App\Models\Park;
use App\Models\SystemSetting;
use App\Models\Task;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // Admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@gassit.de'],
            [
                'name'     => 'Admin User',
                'password' => Hash::make('password'),
                'role'     => 'admin',
                'active'   => true,
            ]
        );

        // 2 parks
        $park1 = Park::factory()->create(['name' => 'Lagerpark Berlin', 'city' => 'Berlin']);
        $park2 = Park::factory()->create(['name' => 'Lagerpark Hamburg', 'city' => 'Hamburg']);

        // 3 unit types per park
        $types = [];
        foreach ([$park1, $park2] as $park) {
            foreach (['Kleine Box', 'Mittlere Box', 'Große Box'] as $name) {
                $types[$park->id][] = UnitType::factory()->create([
                    'park_id' => $park->id,
                    'name'    => $name,
                ]);
            }
        }

        // 20 units per park
        $units = [];
        foreach ([$park1, $park2] as $park) {
            foreach ($types[$park->id] as $type) {
                $count = intdiv(20, count($types[$park->id]));
                for ($i = 0; $i < $count; $i++) {
                    $units[$park->id][] = Unit::factory()->create([
                        'park_id'      => $park->id,
                        'unit_type_id' => $type->id,
                        'status'       => 'free',
                    ]);
                }
            }
        }

        // 15 customers
        $customers = Customer::factory()->count(15)->create();

        // 10 active contracts
        $allUnits = array_merge($units[$park1->id] ?? [], $units[$park2->id] ?? []);
        $usedUnits = [];
        $activeContracts = [];
        for ($i = 0; $i < 10 && $i < count($allUnits); $i++) {
            $unit = $allUnits[$i];
            $customer = $customers[$i % count($customers)];
            $unit->update(['status' => 'rented']);
            $usedUnits[] = $unit->id;
            $activeContracts[] = Contract::factory()->create([
                'customer_id'        => $customer->id,
                'unit_id'            => $unit->id,
                'status'             => 'active',
                'start_date'         => now()->subMonths(rand(1, 12))->toDateString(),
                'notice_period_days' => 30,
                'rent_amount'        => rand(80, 400),
            ]);
        }

        // 5 terminated contracts
        for ($i = 0; $i < 5; $i++) {
            $unit = $allUnits[10 + $i] ?? Unit::factory()->create([
                'park_id'      => $park1->id,
                'unit_type_id' => $types[$park1->id][0]->id,
            ]);
            $customer = $customers[(10 + $i) % count($customers)];
            Contract::factory()->create([
                'customer_id'        => $customer->id,
                'unit_id'            => $unit->id,
                'status'             => 'terminated_by_customer',
                'start_date'         => now()->subMonths(18)->toDateString(),
                'terminated_at'      => now()->subMonths(1)->toDateString(),
                'notice_period_days' => 30,
                'rent_amount'        => rand(80, 300),
            ]);
        }

        // 20 invoices (mix of statuses)
        $statuses = ['draft', 'sent', 'paid', 'overdue', 'cancelled'];
        foreach ($activeContracts as $idx => $contract) {
            $subtotal = (float) $contract->rent_amount;
            $invoice  = Invoice::create([
                'contract_id'    => $contract->id,
                'customer_id'    => $contract->customer_id,
                'park_id'        => $contract->unit->park_id,
                'invoice_number' => 'DEMO-' . now()->format('ymd') . '-' . str_pad($idx + 1, 4, '0', STR_PAD_LEFT),
                'billing_month'  => now()->subMonth()->format('Y-m'),
                'issue_date'     => now()->subMonth()->format('Y-m-d'),
                'due_date'       => now()->subDays(14)->format('Y-m-d'),
                'subtotal'       => $subtotal,
                'tax_rate'       => 0,
                'tax_amount'     => 0,
                'total_amount'   => $subtotal,
                'status'         => $statuses[$idx % count($statuses)],
            ]);
            InvoiceItem::create([
                'invoice_id'  => $invoice->id,
                'description' => 'Monatsmiete',
                'quantity'    => 1,
                'unit_price'  => $subtotal,
                'total'       => $subtotal,
                'item_type'   => 'rent',
                'sort_order'  => 0,
            ]);
        }

        // 5 dunning records
        $overdueInvoices = Invoice::where('status', 'overdue')->take(5)->get();
        foreach ($overdueInvoices as $inv) {
            DunningRecord::factory()->create([
                'invoice_id'  => $inv->id,
                'customer_id' => $inv->customer_id,
                'level'       => 1,
            ]);
        }

        // 3 damage reports
        foreach (array_slice($allUnits, 0, 3) as $unit) {
            DamageReport::factory()->create([
                'unit_id'     => $unit->id,
                'reported_by' => $admin->id,
                'status'      => 'reported',
            ]);
        }

        // 10 tasks
        Task::factory()->count(10)->create(['assigned_to' => $admin->id]);

        // Mail templates for all types
        foreach (['welcome', 'contract', 'invoice', 'dunning_1', 'dunning_2', 'dunning_3', 'termination', 'deposit_return', 'waiting_list', 'custom'] as $type) {
            MailTemplate::firstOrCreate(
                ['template_type' => $type, 'park_id' => null],
                [
                    'name'      => ucfirst(str_replace('_', ' ', $type)) . ' Template',
                    'subject'   => 'Betreff: ' . ucfirst($type),
                    'body_html' => '<p>Sehr geehrte/r {customer_name},</p><p>Ihre Nachricht bezüglich ' . $type . '.</p>',
                    'active'    => true,
                ]
            );
        }

        // Document templates for all types
        foreach (['rental_contract', 'invoice', 'termination_letter', 'dunning_letter', 'deposit_return', 'welcome_letter'] as $type) {
            DocumentTemplate::firstOrCreate(
                ['document_type' => $type],
                [
                    'name'          => ucfirst(str_replace('_', ' ', $type)),
                    'template_html' => '<html><body><h1>' . ucfirst($type) . '</h1><p>{content}</p></body></html>',
                    'active'        => true,
                    'version'       => 1,
                ]
            );
        }

        // System settings defaults
        $defaults = [
            'invoice_day'             => '1',
            'dunning_delay_1'         => '7',
            'dunning_delay_2'         => '14',
            'dunning_delay_3'         => '30',
            'payment_retry_max'       => '3',
            'session_timeout_minutes' => '60',
            'login_max_attempts'      => '5',
            'data_retention_years'    => '10',
        ];
        foreach ($defaults as $key => $value) {
            SystemSetting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
