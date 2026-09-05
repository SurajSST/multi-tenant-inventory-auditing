<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Demand Form {{ $demand->ref }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm 15mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #0f172a;
            font-size: 11px;
            line-height: 1.4;
            margin: 0;
            padding: 0;
            background: #fff;
        }
        .toolbar {
            position: sticky;
            top: 0;
            background: #0f172a;
            color: #fff;
            padding: 10px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            z-index: 100;
        }
        .toolbar button, .toolbar a {
            background: #2563eb;
            color: #fff;
            border: none;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .toolbar button.secondary, .toolbar a.secondary {
            background: rgba(255,255,255,0.15);
        }
        .document {
            max-width: 800px;
            margin: 20px auto;
            padding: 24px;
            border: 1px solid #e2e8f0;
            background: #fff;
        }
        @media print {
            .no-print, .toolbar {
                display: none !important;
            }
            .document {
                border: none;
                margin: 0;
                padding: 0;
                max-width: 100%;
            }
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 8px;
            margin-bottom: 14px;
        }
        .school-title {
            font-size: 18px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #0284c7;
            margin: 0 0 2px 0;
        }
        .school-sub {
            font-size: 10.5px;
            color: #475569;
            margin: 0;
        }
        .doc-badge {
            font-size: 14px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #0f172a;
            margin: 10px 0 4px 0;
            text-align: center;
            background: #f1f5f9;
            padding: 5px 0;
            border-top: 1px solid #cbd5e1;
            border-bottom: 1px solid #cbd5e1;
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            font-size: 11px;
        }
        .meta-table td {
            padding: 4px 6px;
            vertical-align: top;
        }
        .meta-label {
            font-weight: bold;
            color: #475569;
            width: 18%;
        }
        .meta-val {
            color: #0f172a;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            font-size: 10.5px;
        }
        .data-table th, .data-table td {
            border: 1px solid #cbd5e1;
            padding: 6px 8px;
        }
        .data-table th {
            background-color: #f8fafc;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 9.5px;
            color: #334155;
            letter-spacing: 0.5px;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .section-title {
            font-size: 11.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #0f172a;
            margin: 14px 0 6px 0;
            padding-bottom: 3px;
            border-bottom: 1px solid #cbd5e1;
        }
        .box {
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            padding: 8px 12px;
            border-radius: 4px;
            margin-bottom: 12px;
            font-size: 11px;
        }
        .signatures-table {
            width: 100%;
            margin-top: 30px;
            border-collapse: collapse;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .signatures-table td {
            width: 25%;
            vertical-align: bottom;
            text-align: center;
            padding: 0 8px;
        }
        .sig-line {
            border-top: 1.5px solid #0f172a;
            padding-top: 6px;
        }
        .sig-role {
            font-weight: bold;
            text-transform: uppercase;
            font-size: 10px;
            color: #0f172a;
            margin: 0;
        }
        .sig-name {
            font-size: 10px;
            color: #1e293b;
            margin: 2px 0 0 0;
        }
        .sig-date {
            font-size: 9px;
            color: #64748b;
            margin: 2px 0 0 0;
        }
        .footer-note {
            margin-top: 24px;
            border-top: 1px dashed #cbd5e1;
            padding-top: 6px;
            font-size: 8.5px;
            color: #64748b;
            display: flex;
            justify-content: space-between;
        }
    </style>
</head>
<body>

@if (! $isPdf)
    <div class="toolbar no-print">
        <div style="font-weight: 600; font-size: 13px;">
            Demand Form: {{ $demand->ref }}
        </div>
        <div style="display: flex; gap: 8px;">
            <button onclick="window.print()">
                Print Document
            </button>
            <a href="{{ route('demands.pdf', $demand) }}">
                Download PDF
            </a>
            <button class="secondary" onclick="window.close(); history.back();">
                Close
            </button>
        </div>
    </div>
@endif

<div class="document">
    {{-- Header --}}
    <table class="header-table" cellpadding="0" cellspacing="0">
        <tr>
            <td style="vertical-align: middle;">
                <h1 class="school-title">{{ $schoolName }}</h1>
                <p class="school-sub">Pokhara, Nepal · Internal Stock Auditing & Procurement System</p>
                <p class="school-sub" style="font-size: 9.5px; color: #64748b;">Official Requisition & Procurement Documentation</p>
            </td>
            <td style="text-align: right; vertical-align: middle; font-size: 10.5px;">
                <strong>Ref:</strong> {{ $demand->ref }}<br>
                <strong>Date:</strong> {{ $demand->created_at->format('d M Y') }}<br>
                <strong>Fiscal Year:</strong> {{ $demand->fiscal_year ?? 'Current' }}
            </td>
        </tr>
    </table>

    <div class="doc-badge">
        PURCHASE DEMAND FORM / खरीद माग फाराम
    </div>

    {{-- Meta Information --}}
    <table class="meta-table">
        <tr>
            <td class="meta-label">Department:</td>
            <td class="meta-val" style="width: 32%;"><strong>{{ $demand->department }}</strong></td>
            <td class="meta-label">Status:</td>
            <td class="meta-val"><strong>{{ $demand->status->label() }}</strong></td>
        </tr>
        <tr>
            <td class="meta-label">Requested By:</td>
            <td class="meta-val">{{ $demand->raisedBy->full_name }} ({{ $demand->raisedBy->designation }})</td>
            <td class="meta-label">Needed By Date:</td>
            <td class="meta-val">{{ $demand->need_by_date ? $demand->need_by_date->format('d M Y') : 'Normal / As Scheduled' }}</td>
        </tr>
    </table>

    {{-- Justification --}}
    <div class="section-title">Justification / Requisition Purpose (मागको प्रयोजन)</div>
    <div class="box">
        {{ $demand->justification }}
    </div>

    {{-- Items Table --}}
    <div class="section-title">Requisitioned Items (माग गरिएका सामानहरू)</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 30px;" class="text-center">S.N.</th>
                <th>Item Description / Particulars</th>
                <th style="width: 85px;" class="text-center">Code</th>
                <th style="width: 45px;" class="text-right">Qty</th>
                <th style="width: 85px;" class="text-right">Est. Rate</th>
                <th style="width: 95px;" class="text-right">Total Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($demand->lines as $idx => $line)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>
                        <strong>{{ $line->item_name }}</strong>
                        @if ($line->specification)
                            <div style="font-size: 9.5px; color: #475569; margin-top: 1px;">Spec: {{ $line->specification }}</div>
                        @endif
                    </td>
                    <td class="text-center" style="font-family: monospace; font-size: 9.5px;">
                        {{ $line->itemType?->code_prefix ?? 'NEW' }}
                    </td>
                    <td class="text-right">{{ $line->quantity }}</td>
                    <td class="text-right">{{ \App\Support\Money::npr($line->unit_rate) }}</td>
                    <td class="text-right"><strong>{{ \App\Support\Money::npr($line->line_total) }}</strong></td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="background-color: #f8fafc; font-weight: bold;">
                <td colspan="5" class="text-right" style="padding: 7px 8px;">GRAND TOTAL (जम्मा रकम):</td>
                <td class="text-right" style="font-size: 11px; padding: 7px 8px;">
                    {{ \App\Support\Money::npr($demand->total_amount) }}
                </td>
            </tr>
        </tfoot>
    </table>

    {{-- 3-Way Match Check (If ordered or billed) --}}
    @php
        $order = $demand->orders->first();
        $receipt = $order?->receipt;
        $bill = $order?->bills->first();
    @endphp

    @if ($order || $bill)
        <div class="section-title">Procurement Cross-Check (Approved ↔ Ordered ↔ Billed)</div>
        <table class="data-table" style="margin-bottom: 12px;">
            <thead>
                <tr>
                    <th class="text-center">Approved Amount</th>
                    <th class="text-center">Purchase Order ({{ $order?->ref ?? 'Pending' }})</th>
                    <th class="text-center">Vendor Billed ({{ $bill?->bill_no ?? 'Pending' }})</th>
                    <th class="text-center">3-Way Match Status</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="text-center"><strong>{{ \App\Support\Money::npr($demand->total_amount) }}</strong></td>
                    <td class="text-center">{{ $order ? \App\Support\Money::npr($order->order_amount) : '—' }}</td>
                    <td class="text-center">{{ $bill ? \App\Support\Money::npr($bill->bill_amount) : '—' }}</td>
                    <td class="text-center">
                        @if ($bill)
                            <strong>{{ $bill->match_status->label() }}</strong>
                            @if (! \App\Support\Money::isZero($bill->variance_amount))
                                (Diff: {{ \App\Support\Money::npr($bill->variance_amount) }})
                            @endif
                        @elseif ($order)
                            <span>Order Placed</span>
                        @else
                            <span>Approved for Order</span>
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>
    @endif

    {{-- Full Audit Trail ("with copy of trial") --}}
    <div class="section-title">Copy of Audit Trail & Governance Log (निर्णय तथा अडिट ट्रेल अभिलेख)</div>
    <table class="data-table" style="font-size: 10px;">
        <thead>
            <tr>
                <th style="width: 25px;" class="text-center">#</th>
                <th style="width: 100px;">Action / Stage</th>
                <th style="width: 130px;">Action Taken By</th>
                <th>Remarks / Minute Reference / Notes</th>
                <th style="width: 110px;" class="text-right">Timestamp</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="text-center">1</td>
                <td><strong>Requisition Raised</strong></td>
                <td>{{ $demand->raisedBy->full_name }}<br><span style="color:#64748b; font-size: 9px;">{{ $demand->raisedBy->designation }}</span></td>
                <td>Raised for department: {{ $demand->department }}</td>
                <td class="text-right">{{ $demand->created_at->format('d M Y, H:i') }}</td>
            </tr>

            @foreach ($demand->approvals as $i => $app)
                <tr>
                    <td class="text-center">{{ $i + 2 }}</td>
                    <td>
                        <strong>Tier {{ $app->tier_no }} ({{ $app->action->label() }})</strong>
                    </td>
                    <td>
                        {{ $app->actor->full_name }}<br>
                        <span style="color:#64748b; font-size: 9px;">{{ $app->actor->designation }}</span>
                    </td>
                    <td>
                        @if ($app->minute_ref)
                            <strong>Minute: {{ $app->minute_ref }}</strong><br>
                        @endif
                        {{ $app->reason ? '“' . $app->reason . '”' : 'Approved as per institutional procurement guidelines.' }}
                    </td>
                    <td class="text-right">{{ $app->acted_at->format('d M Y, H:i') }}</td>
                </tr>
            @endforeach

            @if ($order)
                <tr>
                    <td class="text-center">{{ $demand->approvals->count() + 2 }}</td>
                    <td><strong>Purchase Order Placed</strong></td>
                    <td>{{ $order->orderedBy->full_name }}<br><span style="color:#64748b; font-size: 9px;">{{ $order->orderedBy->designation }}</span></td>
                    <td>PO Ref: {{ $order->ref }} · Vendor: {{ $order->vendor->name }} · {{ \App\Support\Money::npr($order->order_amount) }}</td>
                    <td class="text-right">{{ $order->ordered_at->format('d M Y, H:i') }}</td>
                </tr>
            @endif

            @if ($receipt)
                <tr>
                    <td class="text-center">{{ $demand->approvals->count() + 3 }}</td>
                    <td><strong>Goods Verified</strong></td>
                    <td>{{ $receipt->receivedBy->full_name }}<br><span style="color:#64748b; font-size: 9px;">{{ $receipt->receivedBy->designation }}</span></td>
                    <td>Verified into {{ $receipt->location->name }} (Condition: {{ $receipt->condition->label() }}){{ $receipt->challan_no ? ' · Challan: ' . $receipt->challan_no : '' }}</td>
                    <td class="text-right">{{ $receipt->received_at->format('d M Y, H:i') }}</td>
                </tr>
            @endif

            @if ($bill)
                <tr>
                    <td class="text-center">{{ $demand->approvals->count() + 4 }}</td>
                    <td><strong>Bill Entered & Matched</strong></td>
                    <td>{{ $bill->enteredBy->full_name }}<br><span style="color:#64748b; font-size: 9px;">{{ $bill->enteredBy->designation }}</span></td>
                    <td>Bill No: {{ $bill->bill_no }} · Amount: {{ \App\Support\Money::npr($bill->bill_amount) }} · Status: {{ $bill->match_status->label() }}</td>
                    <td class="text-right">{{ $bill->entered_at->format('d M Y, H:i') }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    {{-- Formal Signatures Block --}}
    @php
        $finalApproval = $demand->approvals->where('tier_no', $demand->final_tier)->first();
        $tier1Approval = $demand->approvals->first();
    @endphp

    <table class="signatures-table">
        <tr>
            <td>
                <div class="sig-line">
                    <p class="sig-role">Requisitioned By</p>
                    <p class="sig-name">{{ $demand->raisedBy->full_name }}</p>
                    <p class="sig-date">{{ $demand->raisedBy->designation }} · {{ $demand->created_at->format('d M Y') }}</p>
                </div>
            </td>
            <td>
                <div class="sig-line">
                    <p class="sig-role">Tier 1 Verification</p>
                    <p class="sig-name">{{ $tier1Approval ? $tier1Approval->actor->full_name : 'Verified / Recommended' }}</p>
                    <p class="sig-date">{{ $tier1Approval ? $tier1Approval->acted_at->format('d M Y') : 'Date: _______________' }}</p>
                </div>
            </td>
            <td>
                <div class="sig-line">
                    <p class="sig-role">Internal Audit / Accounts</p>
                    <p class="sig-name">Budget Verified</p>
                    <p class="sig-date">Date: _______________</p>
                </div>
            </td>
            <td>
                <div class="sig-line">
                    <p class="sig-role">Approved By (Principal)</p>
                    <p class="sig-name">{{ $finalApproval ? $finalApproval->actor->full_name : ($demand->isPending() ? 'Pending Final Approval' : 'Principal') }}</p>
                    <p class="sig-date">{{ $demand->closed_at ? $demand->closed_at->format('d M Y') : 'Date: _______________' }}</p>
                </div>
            </td>
        </tr>
    </table>

    <div class="footer-note">
        <span>Prativa Secondary School · Official Internal Inventory & Procurement System</span>
        <span>Generated: {{ now()->format('d M Y, H:i') }}</span>
        <span>Office Stamp / छाप</span>
    </div>
</div>

@if (! $isPdf)
    <script>
        // Optional auto-prompt when opened directly
        if (window.location.search.indexOf('autoprint=1') !== -1) {
            window.addEventListener('load', () => {
                setTimeout(() => window.print(), 300);
            });
        }
    </script>
@endif

</body>
</html>
