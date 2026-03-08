<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ParkScopeMiddleware;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Unit;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportController extends Controller
{
    public function applications(Request $request)
    {
        $user   = $request->user();
        $parkId = $request->query('park_id');
        $from   = $request->query('from');
        $to     = $request->query('to');
        $format = $request->query('format', 'json');

        if ($parkId && ! ParkScopeMiddleware::hasAccessToPark($user, (int) $parkId)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $rows = Application::with(['customer', 'park', 'unitType'])
            ->when($parkId, fn($q) => $q->where('park_id', $parkId))
            ->when($from, fn($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn($q) => $q->where('created_at', '<=', $to . ' 23:59:59'))
            ->get()
            ->map(fn($a) => [
                'id'           => $a->id,
                'park'         => $a->park?->name,
                'customer'     => $a->customer ? $a->customer->first_name . ' ' . $a->customer->last_name : null,
                'unit_type'    => $a->unitType?->name,
                'status'       => $a->status,
                'source'       => $a->source,
                'created_at'   => $a->created_at?->toDateString(),
            ]);

        if ($format === 'xlsx') {
            return $this->xlsxResponse($rows->toArray(), 'applications');
        }

        return response()->json($rows);
    }

    public function customers(Request $request)
    {
        $user   = $request->user();
        $parkId = $request->query('park_id');
        $from   = $request->query('from');
        $to     = $request->query('to');
        $format = $request->query('format', 'json');

        if ($parkId && ! ParkScopeMiddleware::hasAccessToPark($user, (int) $parkId)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $rows = Customer::query()
            ->when($parkId, fn($q) => $q->whereHas('applications', fn($aq) => $aq->where('park_id', $parkId)))
            ->when($from, fn($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn($q) => $q->where('created_at', '<=', $to . ' 23:59:59'))
            ->get()
            ->map(fn($c) => [
                'id'         => $c->id,
                'name'       => $c->first_name . ' ' . $c->last_name,
                'email'      => $c->email,
                'type'       => $c->type,
                'status'     => $c->status,
                'created_at' => $c->created_at?->toDateString(),
            ]);

        if ($format === 'xlsx') {
            return $this->xlsxResponse($rows->toArray(), 'customers');
        }

        return response()->json($rows);
    }

    public function units(Request $request)
    {
        $user   = $request->user();
        $parkId = $request->query('park_id');
        $format = $request->query('format', 'json');

        if ($parkId && ! ParkScopeMiddleware::hasAccessToPark($user, (int) $parkId)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $rows = Unit::with(['park', 'unitType'])
            ->when($parkId, fn($q) => $q->where('park_id', $parkId))
            ->get()
            ->map(fn($u) => [
                'id'        => $u->id,
                'park'      => $u->park?->name,
                'unit_type' => $u->unitType?->name,
                'number'    => $u->unit_number,
                'status'    => $u->status,
                'size_m2'   => $u->size_m2,
                'floor'     => $u->floor,
            ]);

        if ($format === 'xlsx') {
            return $this->xlsxResponse($rows->toArray(), 'units');
        }

        return response()->json($rows);
    }

    public function finance(Request $request)
    {
        $user   = $request->user();
        $parkId = $request->query('park_id');
        $from   = $request->query('from');
        $to     = $request->query('to');
        $format = $request->query('format', 'json');

        if ($parkId && ! ParkScopeMiddleware::hasAccessToPark($user, (int) $parkId)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $rows = Invoice::with(['customer', 'park'])
            ->when($parkId, fn($q) => $q->where('park_id', $parkId))
            ->when($from, fn($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn($q) => $q->where('created_at', '<=', $to . ' 23:59:59'))
            ->get()
            ->map(fn($i) => [
                'id'           => $i->id,
                'number'       => $i->invoice_number,
                'park'         => $i->park?->name,
                'customer'     => $i->customer ? $i->customer->first_name . ' ' . $i->customer->last_name : null,
                'status'       => $i->status,
                'total_amount' => (float) $i->total_amount,
                'due_date'     => $i->due_date?->toDateString(),
                'created_at'   => $i->created_at?->toDateString(),
            ]);

        if ($format === 'xlsx') {
            return $this->xlsxResponse($rows->toArray(), 'finance');
        }

        return response()->json($rows);
    }

    public function audit(Request $request)
    {
        $user   = $request->user();
        $parkId = $request->query('park_id');
        $from   = $request->query('from');
        $to     = $request->query('to');

        if ($parkId && ! ParkScopeMiddleware::hasAccessToPark($user, (int) $parkId)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $rows = AuditLog::with('user')
            ->when($from, fn($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn($q) => $q->where('created_at', '<=', $to . ' 23:59:59'))
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($log) => [
                'id'         => $log->id,
                'user'       => $log->user?->name,
                'action'     => $log->action,
                'model_type' => $log->model_type,
                'model_id'   => $log->model_id,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->toDateTimeString(),
            ]);

        return response()->json($rows);
    }

    private function xlsxResponse(array $rows, string $filename)
    {
        if (empty($rows)) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setCellValue('A1', 'No data');
        } else {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $headers = array_keys($rows[0]);
            foreach ($headers as $col => $header) {
                $colLetter = Coordinate::stringFromColumnIndex($col + 1);
                $sheet->setCellValue($colLetter . '1', $header);
            }

            foreach ($rows as $rowIndex => $row) {
                foreach (array_values($row) as $col => $value) {
                    $colLetter = Coordinate::stringFromColumnIndex($col + 1);
                    $sheet->setCellValue($colLetter . ($rowIndex + 2), $value);
                }
            }
        }

        $writer = new Xlsx($spreadsheet);

        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        return response($content, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '.xlsx"',
        ]);
    }
}
