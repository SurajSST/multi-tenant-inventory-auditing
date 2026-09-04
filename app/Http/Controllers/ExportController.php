<?php

namespace App\Http\Controllers;

use App\Exports\AuditTrailExport;
use App\Exports\PettyCashExport;
use App\Exports\ProcurementExport;
use App\Exports\StockRegisterExport;
use App\Exports\UnitListExport;
use App\Services\AuditLogger;
use App\Services\InventoryService;
use App\Services\ReportService;
use App\Services\SettingService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function __construct(
        private SettingService $settings,
        private AuditLogger $audit,
    ) {}

    public function stockRegister(InventoryService $inventory): StreamedResponse
    {
        $this->log('stock register');

        return (new StockRegisterExport($this->settings, $inventory))->download();
    }

    public function unitList(InventoryService $inventory): StreamedResponse
    {
        $this->log('unit list');

        return (new UnitListExport($this->settings, $inventory))->download();
    }

    public function procurement(Request $request, ReportService $reports): StreamedResponse
    {
        // Every vendor, every amount, every approval on one sheet. The screens
        // that show this are already restricted; the download was not.
        abort_unless($request->user()->seesEverything(), 403);

        [$from, $to] = $this->period($request);
        $this->log('procurement register');

        return (new ProcurementExport($this->settings, $reports, $from, $to))->download();
    }

    public function pettyCash(Request $request): StreamedResponse
    {
        abort_unless($request->user()->can('handle-accounts'), 403);

        [$from, $to] = $this->period($request);
        $this->log('petty cash register');

        return (new PettyCashExport($this->settings, $from, $to))->download();
    }

    public function auditTrail(Request $request): StreamedResponse
    {
        abort_unless($request->user()->can('view-audit-trail'), 403);

        [$from, $to] = $this->period($request);
        $this->log('audit trail');

        return (new AuditTrailExport($this->settings, $from, $to))->download();
    }

    /** @return array{0: string|null, 1: string|null} */
    private function period(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        return [$validated['from'] ?? null, $validated['to'] ?? null];
    }

    /** Taking records out of the system is itself an action worth recording. */
    private function log(string $what): void
    {
        $this->audit->record(
            action: 'EXPORTED',
            entity: 'reports',
            entityId: null,
            detail: auth()->user()->full_name.' exported the '.$what.' to Excel',
        );
    }
}
