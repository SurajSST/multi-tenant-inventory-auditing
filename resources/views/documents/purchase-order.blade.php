<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Purchase Order {{ $order->ref }}</title>
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
            Purchase Order: {{ $order->ref }}
        </div>
        <div style="display: flex; gap: 8px;">
            <button onclick="window.print()">
                Print Order
            </button>
            <a href="{{ route('orders.pdf', $order) }}">
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
                <p class="school-sub">Pokhara, Nepal · Stock Auditing & Procurement Department</p>
                <p class="school-sub" style="font-size: 9.5px; color: #64748b;">Official Institutional Purchase Order</p>
            </td>
            <td style="text-align: right; vertical-align: middle; font-size: 10.5px;">
                <strong>PO Ref:</strong> {{ $order->ref }}<br>
                <strong>Date:</strong> {{ $order->ordered_at->format('d M Y') }}<br>
                <strong>Demand Ref:</strong> {{ $order->demand->ref }}
            </td>
        </tr>
    </table>

    <div class="doc-badge">
        PURCHASE ORDER / खरीद आदेश
    </div>

    {{-- Meta Information --}}
    <table class="meta-table">
        <tr>
            <td class="meta-label">Vendor Name:</td>
            <td class="meta-val" style="width: 32%;"><strong>{{ $order->vendor->name }}</strong></td>
            <td class="meta-label">Order Status:</td>
            <td class="meta-val"><strong>{{ $order->status->label() }}</strong></td>
        </tr>
        <tr>
            <td class="meta-label">Ordered By:</td>
            <td class="meta-val">{{ $order->orderedBy->full_name }} ({{ $order->orderedBy->designation }})</td>
            <td class="meta-label">Expected Date:</td>
            <td class="meta-val">{{ $order->expected_date ? $order->expected_date->format('d M Y') : 'Immediate Delivery' }}</td>
        </tr>
        <tr>
            <td class="meta-label">Against Demand:</td>
            <td class="meta-val">{{ $order->demand->ref }} ({{ $order->demand->department }})</td>
            <td class="meta-label">Order Value:</td>
            <td class="meta-val"><strong>{{ \App\Support\Money::npr($order->order_amount) }}</strong></td>
        </tr>
    </table>

    @if ($order->note)
        <div class="section-title">Purchase Officer's Instructions / Remarks</div>
        <div class="box">
            {{ $order->note }}
        </div>
    @endif

    {{-- Items Table --}}
    <div class="section-title">Items Ordered (आदेश गरिएका सामग्रीहरू)</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 30px;" class="text-center">S.N.</th>
                <th>Item Description / Specification</th>
                <th style="width: 85px;" class="text-center">Code</th>
                <th style="width: 60px;" class="text-right">Ordered Qty</th>
                @if ($order->receipt)
                    <th style="width: 60px;" class="text-right">Received Qty</th>
                    <th style="width: 120px;">Condition / Note</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($order->demand->lines as $idx => $line)
                @php $rl = $order->receipt?->lines->firstWhere('demand_line_id', $line->id) @endphp
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>
                        <strong>{{ $line->item_name }}</strong>
                        @if ($line->specification)
                            <div style="font-size: 9.5px; color: #475569;">Spec: {{ $line->specification }}</div>
                        @endif
                    </td>
                    <td class="text-center" style="font-family: monospace; font-size: 9.5px;">
                        {{ $line->itemType?->code_prefix ?? '—' }}
                    </td>
                    <td class="text-right"><strong>{{ $line->quantity }}</strong></td>
                    @if ($order->receipt)
                        <td class="text-right" style="{{ $rl && $rl->isShort() ? 'color: #b45309; font-weight: bold;' : '' }}">
                            {{ $rl?->qty_received ?? '—' }}
                        </td>
                        <td style="font-size: 9.5px; color: #475569;">
                            {{ $rl?->remark ?? 'Verified on delivery' }}
                        </td>
                    @endif
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="background-color: #f8fafc; font-weight: bold;">
                <td colspan="{{ $order->receipt ? 3 : 3 }}" class="text-right" style="padding: 7px 8px;">ORDER VALUE (जम्मा खरीद रकम):</td>
                <td class="text-right" style="font-size: 11px; padding: 7px 8px;" colspan="{{ $order->receipt ? 3 : 1 }}">
                    {{ \App\Support\Money::npr($order->order_amount) }}
                </td>
            </tr>
        </tfoot>
    </table>

    {{-- Goods Verification Status --}}
    @if ($order->receipt)
        <div class="section-title">Delivery & Verification Log (सामग्री दाखिला विवरण)</div>
        <div class="box">
            <strong>Verified By:</strong> {{ $order->receipt->receivedBy->full_name }} ({{ $order->receipt->receivedBy->designation }})<br>
            <strong>Date Received:</strong> {{ $order->receipt->received_at->format('d M Y, H:i') }} · <strong>Location:</strong> {{ $order->receipt->location->name }} · <strong>Condition:</strong> {{ $order->receipt->condition->label() }}<br>
            @if ($order->receipt->challan_no)
                <strong>Vendor Challan No:</strong> {{ $order->receipt->challan_no }}<br>
            @endif
            @if ($order->receipt->discrepancy_note)
                <span style="color: #b45309;"><strong>Discrepancy:</strong> “{{ $order->receipt->discrepancy_note }}”</span>
            @endif
        </div>
    @endif

    {{-- Full Audit Trail ("with copy of trial") --}}
    <div class="section-title">Copy of Audit Trail & Procurement History (निर्णय तथा अडिट ट्रेल अभिलेख)</div>
    <table class="data-table" style="font-size: 10px;">
        <thead>
            <tr>
                <th style="width: 25px;" class="text-center">#</th>
                <th style="width: 110px;">Stage</th>
                <th style="width: 140px;">Actor / Officer</th>
                <th>Details & Reference</th>
                <th style="width: 110px;" class="text-right">Timestamp</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="text-center">1</td>
                <td>Demand Approved</td>
                <td>{{ $order->demand->raisedBy->full_name }} (Raised)</td>
                <td>Ref: {{ $order->demand->ref }} · Approved value: {{ \App\Support\Money::npr($order->demand->total_amount) }}</td>
                <td class="text-right">{{ $order->demand->closed_at ? $order->demand->closed_at->format('d M Y, H:i') : $order->demand->created_at->format('d M Y, H:i') }}</td>
            </tr>
            <tr>
                <td class="text-center">2</td>
                <td><strong>Purchase Order Issued</strong></td>
                <td>{{ $order->orderedBy->full_name }}<br><span style="color:#64748b; font-size: 9px;">{{ $order->orderedBy->designation }}</span></td>
                <td>Issued to {{ $order->vendor->name }} · Total: {{ \App\Support\Money::npr($order->order_amount) }}</td>
                <td class="text-right">{{ $order->ordered_at->format('d M Y, H:i') }}</td>
            </tr>
            @if ($order->receipt)
                <tr>
                    <td class="text-center">3</td>
                    <td><strong>Goods Inspected & Verified</strong></td>
                    <td>{{ $order->receipt->receivedBy->full_name }}<br><span style="color:#64748b; font-size: 9px;">{{ $order->receipt->receivedBy->designation }}</span></td>
                    <td>Received into {{ $order->receipt->location->name }}{{ $order->receipt->challan_no ? ' · Challan: ' . $order->receipt->challan_no : '' }}</td>
                    <td class="text-right">{{ $order->receipt->received_at->format('d M Y, H:i') }}</td>
                </tr>
            @endif
            @foreach ($order->bills as $i => $bill)
                <tr>
                    <td class="text-center">{{ 4 + $i }}</td>
                    <td>Bill Entered ({{ $bill->bill_no }})</td>
                    <td>{{ $bill->enteredBy->full_name }}<br><span style="color:#64748b; font-size: 9px;">{{ $bill->enteredBy->designation }}</span></td>
                    <td>Bill Amount: {{ \App\Support\Money::npr($bill->bill_amount) }} · Status: {{ $bill->match_status->label() }}</td>
                    <td class="text-right">{{ $bill->entered_at->format('d M Y, H:i') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Formal Signatures Block --}}
    <table class="signatures-table">
        <tr>
            <td>
                <div class="sig-line">
                    <p class="sig-role">Purchase Officer</p>
                    <p class="sig-name">{{ $order->orderedBy->full_name }}</p>
                    <p class="sig-date">{{ $order->orderedBy->designation }} · {{ $order->ordered_at->format('d M Y') }}</p>
                </div>
            </td>
            <td>
                <div class="sig-line">
                    <p class="sig-role">Vendor Acceptance</p>
                    <p class="sig-name">{{ $order->vendor->name }}</p>
                    <p class="sig-date">Signature & Seal: ____________</p>
                </div>
            </td>
            <td>
                <div class="sig-line">
                    <p class="sig-role">Receiving Officer</p>
                    <p class="sig-name">{{ $order->receipt ? $order->receipt->receivedBy->full_name : 'Pending Goods Arrival' }}</p>
                    <p class="sig-date">{{ $order->receipt ? $order->receipt->received_at->format('d M Y') : 'Date: _______________' }}</p>
                </div>
            </td>
            <td>
                <div class="sig-line">
                    <p class="sig-role">Principal / Head</p>
                    <p class="sig-name">Authorized Signature</p>
                    <p class="sig-date">{{ $order->ordered_at->format('d M Y') }}</p>
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
        if (window.location.search.indexOf('autoprint=1') !== -1) {
            window.addEventListener('load', () => {
                setTimeout(() => window.print(), 300);
            });
        }
    </script>
@endif

</body>
</html>
