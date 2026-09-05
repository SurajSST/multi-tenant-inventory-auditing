<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Procurement Bills Register</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 10mm 12mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #0f172a;
            font-size: 10.5px;
            line-height: 1.35;
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
            max-width: 1040px;
            margin: 20px auto;
            padding: 20px;
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
            margin-bottom: 12px;
        }
        .school-title {
            font-size: 17px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #0284c7;
            margin: 0 0 2px 0;
        }
        .school-sub {
            font-size: 10px;
            color: #475569;
            margin: 0;
        }
        .doc-badge {
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #0f172a;
            margin: 8px 0;
            text-align: center;
            background: #f1f5f9;
            padding: 4px 0;
            border-top: 1px solid #cbd5e1;
            border-bottom: 1px solid #cbd5e1;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            font-size: 9.5px;
        }
        .data-table th, .data-table td {
            border: 1px solid #cbd5e1;
            padding: 5px 6px;
        }
        .data-table th {
            background-color: #f8fafc;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 9px;
            color: #334155;
            letter-spacing: 0.3px;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .section-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #0f172a;
            margin: 12px 0 5px 0;
            padding-bottom: 2px;
            border-bottom: 1px solid #cbd5e1;
        }
        .signatures-table {
            width: 100%;
            margin-top: 25px;
            border-collapse: collapse;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .signatures-table td {
            width: 33.33%;
            vertical-align: bottom;
            text-align: center;
            padding: 0 10px;
        }
        .sig-line {
            border-top: 1.5px solid #0f172a;
            padding-top: 5px;
        }
        .sig-role {
            font-weight: bold;
            text-transform: uppercase;
            font-size: 9.5px;
            color: #0f172a;
            margin: 0;
        }
        .sig-name {
            font-size: 9.5px;
            color: #1e293b;
            margin: 2px 0 0 0;
        }
        .sig-date {
            font-size: 8.5px;
            color: #64748b;
            margin: 1px 0 0 0;
        }
        .footer-note {
            margin-top: 20px;
            border-top: 1px dashed #cbd5e1;
            padding-top: 5px;
            font-size: 8px;
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
            Procurement Bills & 3-Way Match Register
        </div>
        <div style="display: flex; gap: 8px;">
            <button onclick="window.print()">
                Print Register
            </button>
            <a href="{{ route('bills.pdf', ['status' => $status]) }}">
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
                <p class="school-sub">Pokhara, Nepal · Finance & Accounts Directorate</p>
                <p class="school-sub" style="font-size: 9px; color: #64748b;">Official Procurement Audit Register</p>
            </td>
            <td style="text-align: right; vertical-align: middle; font-size: 10px;">
                <strong>Date Generated:</strong> {{ now()->format('d M Y') }}<br>
                <strong>Filter Scope:</strong> {{ $status ? \App\Enums\MatchStatus::tryFrom($status)?->label() : 'All Bills' }}<br>
                <strong>Total Records:</strong> {{ $bills->count() }} bills
            </td>
        </tr>
    </table>

    <div class="doc-badge">
        PROCUREMENT BILLS & 3-WAY MATCH REGISTER / बिल तथा ३-पक्षीय मिलान विवरण
    </div>

    {{-- Bills Table --}}
    @php
        $totApproved = '0.00';
        $totOrdered = '0.00';
        $totBilled = '0.00';
        $totVariance = '0.00';
    @endphp

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 25px;" class="text-center">#</th>
                <th style="width: 80px;">Bill No</th>
                <th style="width: 70px;">Bill Date</th>
                <th style="width: 85px;">PO Ref</th>
                <th>Vendor</th>
                <th style="width: 80px;" class="text-right">Approved</th>
                <th style="width: 80px;" class="text-right">Ordered</th>
                <th style="width: 85px;" class="text-right">Billed</th>
                <th style="width: 75px;" class="text-right">Variance</th>
                <th style="width: 85px;" class="text-center">Status</th>
                <th style="width: 95px;">Entered By</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($bills as $idx => $bill)
                @php
                    $totApproved = \App\Support\Money::add($totApproved, $bill->approved_amount);
                    $totOrdered = \App\Support\Money::add($totOrdered, $bill->ordered_amount);
                    $totBilled = \App\Support\Money::add($totBilled, $bill->bill_amount);
                    $totVariance = \App\Support\Money::add($totVariance, $bill->variance_amount);
                @endphp
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td><strong>{{ $bill->bill_no }}</strong></td>
                    <td>{{ $bill->bill_date->format('d M Y') }}</td>
                    <td style="font-family: monospace;">{{ $bill->purchaseOrder?->ref ?? '—' }}</td>
                    <td>{{ $bill->vendor->name }}</td>
                    <td class="text-right">{{ $bill->approved_amount ? \App\Support\Money::npr($bill->approved_amount) : '—' }}</td>
                    <td class="text-right">{{ $bill->ordered_amount ? \App\Support\Money::npr($bill->ordered_amount) : '—' }}</td>
                    <td class="text-right"><strong>{{ \App\Support\Money::npr($bill->bill_amount) }}</strong></td>
                    <td class="text-right" style="{{ \App\Support\Money::gt($bill->variance_amount, 0) ? 'color: #b91c1c; font-weight: bold;' : '' }}">
                        {{ \App\Support\Money::isZero($bill->variance_amount) ? '0.00' : (\App\Support\Money::gt($bill->variance_amount, 0) ? '+' : '') . \App\Support\Money::format($bill->variance_amount) }}
                    </td>
                    <td class="text-center">
                        {{ $bill->match_status->label() }}
                    </td>
                    <td>
                        {{ $bill->enteredBy->full_name }}<br>
                        <span style="color: #64748b; font-size: 8.5px;">{{ $bill->entered_at->format('d M Y') }}</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="text-center" style="padding: 16px;">No bills on record for the selected criteria.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr style="background-color: #f8fafc; font-weight: bold;">
                <td colspan="5" class="text-right" style="padding: 6px;">TOTALS (जम्मा रकम):</td>
                <td class="text-right">{{ \App\Support\Money::npr($totApproved) }}</td>
                <td class="text-right">{{ \App\Support\Money::npr($totOrdered) }}</td>
                <td class="text-right">{{ \App\Support\Money::npr($totBilled) }}</td>
                <td class="text-right">{{ \App\Support\Money::npr($totVariance) }}</td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>

    {{-- Cleared Variances Log ("with copy of trial") --}}
    @php
        $clearedBills = $bills->whereNotNull('variance_note');
    @endphp

    @if ($clearedBills->isNotEmpty())
        <div class="section-title">Audit Log of Cleared Variances (फरक रकम फस्र्यौट तथा स्पष्टीकरण अभिलेख)</div>
        <table class="data-table" style="font-size: 9px;">
            <thead>
                <tr>
                    <th style="width: 70px;">Bill No</th>
                    <th style="width: 75px;" class="text-right">Variance</th>
                    <th style="width: 120px;">Cleared By</th>
                    <th>Reason / Auditor Justification</th>
                    <th style="width: 90px;" class="text-right">Cleared At</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($clearedBills as $cb)
                    <tr>
                        <td><strong>{{ $cb->bill_no }}</strong></td>
                        <td class="text-right" style="color: #b91c1c; font-weight: bold;">{{ \App\Support\Money::npr($cb->variance_amount) }}</td>
                        <td>{{ $cb->clearedBy?->full_name }}</td>
                        <td>“{{ $cb->variance_note }}”</td>
                        <td class="text-right">{{ $cb->cleared_at ? $cb->cleared_at->format('d M Y, H:i') : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Signatures --}}
    <table class="signatures-table">
        <tr>
            <td>
                <div class="sig-line">
                    <p class="sig-role">Prepared By (लेखा शाखा)</p>
                    <p class="sig-name">{{ auth()->user()?->full_name }}</p>
                    <p class="sig-date">{{ auth()->user()?->designation }} · {{ now()->format('d M Y') }}</p>
                </div>
            </td>
            <td>
                <div class="sig-line">
                    <p class="sig-role">Internal Auditor (लेखापरीक्षक)</p>
                    <p class="sig-name">Verified & Reconciled</p>
                    <p class="sig-date">Signature & Date: ____________</p>
                </div>
            </td>
            <td>
                <div class="sig-line">
                    <p class="sig-role">Approved By (प्रमाणितकर्ता)</p>
                    <p class="sig-name">Principal / School Head</p>
                    <p class="sig-date">{{ now()->format('d M Y') }}</p>
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
