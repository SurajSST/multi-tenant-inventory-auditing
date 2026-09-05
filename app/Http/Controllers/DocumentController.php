<?php

namespace App\Http\Controllers;

use App\Models\ApprovalTier;
use App\Models\Bill;
use App\Models\DemandForm;
use App\Models\PettyCashToken;
use App\Models\PurchaseOrder;
use App\Services\AuditLogger;
use App\Services\SettingService;
use App\Tenancy\TenantContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DocumentController extends Controller
{
    public function __construct(
        private SettingService $settings,
        private AuditLogger $audit,
    ) {}

    public function demand(DemandForm $demand, Request $request): Response
    {
        $demand->load(['raisedBy', 'lines.itemType', 'approvals.actor', 'orders.vendor', 'orders.receipt.receivedBy', 'orders.bills']);
        $tiers = ApprovalTier::orderBy('tier_no')->get();
        $school = app(TenantContext::class)->current();
        $schoolName = $school?->name ?? config('prativa.school_name', 'Prativa Secondary School');

        $data = [
            'demand' => $demand,
            'tiers' => $tiers,
            'school' => $school,
            'schoolName' => $schoolName,
            'isPdf' => $request->boolean('pdf') || $request->routeIs('*.pdf'),
        ];

        if ($data['isPdf']) {
            $pdf = Pdf::loadView('documents.demand-form', $data)->setPaper('a4', 'portrait');

            return $pdf->download("Demand-{$demand->ref}.pdf");
        }

        return response()->view('documents.demand-form', $data);
    }

    public function order(PurchaseOrder $order, Request $request): Response
    {
        $order->load(['orderedBy', 'vendor', 'demand.lines.itemType', 'demand.raisedBy', 'receipt.receivedBy', 'receipt.lines', 'bills']);
        $school = app(TenantContext::class)->current();
        $schoolName = $school?->name ?? config('prativa.school_name', 'Prativa Secondary School');

        $data = [
            'order' => $order,
            'school' => $school,
            'schoolName' => $schoolName,
            'isPdf' => $request->boolean('pdf') || $request->routeIs('*.pdf'),
        ];

        if ($data['isPdf']) {
            $pdf = Pdf::loadView('documents.purchase-order', $data)->setPaper('a4', 'portrait');

            return $pdf->download("PO-{$order->ref}.pdf");
        }

        return response()->view('documents.purchase-order', $data);
    }

    public function pettyCash(PettyCashToken $token, Request $request): Response
    {
        $token->load(['issuedBy', 'paidBy']);
        $school = app(TenantContext::class)->current();
        $schoolName = $school?->name ?? config('prativa.school_name', 'Prativa Secondary School');

        $data = [
            'token' => $token,
            'school' => $school,
            'schoolName' => $schoolName,
            'isPdf' => $request->boolean('pdf') || $request->routeIs('*.pdf'),
        ];

        if ($data['isPdf']) {
            $pdf = Pdf::loadView('documents.petty-cash', $data)->setPaper('a4', 'portrait');

            return $pdf->download("PettyCash-{$token->serial}.pdf");
        }

        return response()->view('documents.petty-cash', $data);
    }

    public function bills(Request $request): Response
    {
        $status = $request->query('status');
        $query = Bill::with(['vendor', 'enteredBy', 'clearedBy', 'purchaseOrder'])
            ->orderByDesc('bill_date')
            ->orderByDesc('entered_at');

        if ($status) {
            $query->where('match_status', $status);
        }

        $bills = $query->get();
        $school = app(TenantContext::class)->current();
        $schoolName = $school?->name ?? config('prativa.school_name', 'Prativa Secondary School');

        $data = [
            'bills' => $bills,
            'status' => $status,
            'school' => $school,
            'schoolName' => $schoolName,
            'isPdf' => $request->boolean('pdf') || $request->routeIs('*.pdf'),
        ];

        if ($data['isPdf']) {
            $pdf = Pdf::loadView('documents.bills-register', $data)->setPaper('a4', 'portrait');

            return $pdf->download('Bills-Register.pdf');
        }

        return response()->view('documents.bills-register', $data);
    }
}
