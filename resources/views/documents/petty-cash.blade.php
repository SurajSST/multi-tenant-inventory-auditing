<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Petty Cash Voucher {{ $token->serial }}</title>
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
            padding: 5px 8px;
            vertical-align: middle;
            border: 1px solid #e2e8f0;
        }
        .meta-label {
            font-weight: bold;
            color: #475569;
            width: 22%;
            background: #f8fafc;
        }
        .meta-val {
            color: #0f172a;
        }
        .amount-box {
            border: 2px solid #0f172a;
            background: #f8fafc;
            padding: 10px 14px;
            text-align: center;
            margin: 14px 0;
            border-radius: 4px;
        }
        .amount-val {
            font-size: 18px;
            font-weight: bold;
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
        .signatures-table {
            width: 100%;
            margin-top: 35px;
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
            Petty Cash Token: {{ $token->serial }}
        </div>
        <div style="display: flex; gap: 8px;">
            <button onclick="window.print()">
                Print Voucher
            </button>
            <a href="{{ route('petty-cash.pdf', $token) }}">
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
                <p class="school-sub">Pokhara, Nepal · Finance & Accounts Section</p>
                <p class="school-sub" style="font-size: 9.5px; color: #64748b;">Official Petty Cash Disbursement Voucher</p>
            </td>
            <td style="text-align: right; vertical-align: middle; font-size: 10.5px;">
                <strong>Token Serial:</strong> {{ $token->serial }}<br>
                <strong>Date Issued:</strong> {{ $token->issued_at->format('d M Y') }}<br>
                <strong>Fiscal Year:</strong> {{ $token->fiscal_year }}
            </td>
        </tr>
    </table>

    <div class="doc-badge">
        PETTY CASH PAYMENT VOUCHER / खुद्रा खर्च भुक्तानी भौचर
    </div>

    {{-- Amount Banner --}}
    <div class="amount-box">
        <div style="font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.5px; color: #475569; margin-bottom: 2px;">Voucher Amount (भुक्तानी रकम)</div>
        <div class="amount-val">{{ \App\Support\Money::npr($token->amount) }}</div>
        <div style="font-size: 10px; color: #64748b; margin-top: 2px;">
            Status: <strong>{{ $token->status->label() }}</strong> · Fiscal Ceiling: {{ \App\Support\Money::npr($token->ceiling_at_issue) }}
        </div>
    </div>

    {{-- Voucher Particulars --}}
    <div class="section-title">Claim & Payment Particulars (भुक्तानी तथा बिल विवरण)</div>
    <table class="meta-table">
        <tr>
            <td class="meta-label">Claimant / Payee:</td>
            <td class="meta-val"><strong>{{ $token->claimant_name }}</strong></td>
            <td class="meta-label">Vendor Name:</td>
            <td class="meta-val">{{ $token->vendor_name }}</td>
        </tr>
        <tr>
            <td class="meta-label">Purpose / Description:</td>
            <td class="meta-val" colspan="3">{{ $token->purpose }}</td>
        </tr>
        <tr>
            <td class="meta-label">Vendor Bill No:</td>
            <td class="meta-val"><strong>{{ $token->bill_no }}</strong></td>
            <td class="meta-label">Vendor Bill Date:</td>
            <td class="meta-val">{{ $token->bill_date ? $token->bill_date->format('d M Y') : '—' }}</td>
        </tr>
        <tr>
            <td class="meta-label">Original Bill Sighted:</td>
            <td class="meta-val" style="color: #047857; font-weight: bold;">Yes, verified in person by Issuer</td>
            <td class="meta-label">Payment Mode:</td>
            <td class="meta-val">Cash (Petty Cash Float)</td>
        </tr>
    </table>

    {{-- Audit Trail & Separation of Duties ("with copy of trial") --}}
    <div class="section-title">Copy of Audit Trail & Governance Log (अडिट ट्रेल तथा प्रमाणीकरण अभिलेख)</div>
    <table class="data-table" style="font-size: 10px;">
        <thead>
            <tr>
                <th style="width: 25px;" style="text-align: center;">#</th>
                <th style="width: 120px;">Operation</th>
                <th style="width: 140px;">Officer / Actor</th>
                <th>Separation of Duties Verification</th>
                <th style="width: 110px;" style="text-align: right;">Timestamp</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="text-align: center;">1</td>
                <td><strong>Token Issued</strong></td>
                <td>{{ $token->issuedBy->full_name }}<br><span style="color:#64748b; font-size: 9px;">{{ $token->issuedBy->designation }}</span></td>
                <td>Issued against original bill sighted. Verified below ceiling ({{ \App\Support\Money::npr($token->ceiling_at_issue) }}).</td>
                <td style="text-align: right;">{{ $token->issued_at->format('d M Y, H:i') }}</td>
            </tr>
            <tr>
                <td style="text-align: center;">2</td>
                <td><strong>Payment Released</strong></td>
                <td>
                    @if ($token->paidBy)
                        {{ $token->paidBy->full_name }}<br><span style="color:#64748b; font-size: 9px;">{{ $token->paidBy->designation }}</span>
                    @else
                        <span style="color: #b45309; font-weight: bold;">Pending Release</span>
                    @endif
                </td>
                <td>
                    @if ($token->paidBy)
                        Separation of duties enforced: Payment disbursed by different officer from issuer.
                    @else
                        Awaiting release by accounts officer other than {{ $token->issuedBy->full_name }}.
                    @endif
                </td>
                <td style="text-align: right;">{{ $token->paid_at ? $token->paid_at->format('d M Y, H:i') : '—' }}</td>
            </tr>
            @if ($token->void_reason)
                <tr>
                    <td style="text-align: center;">3</td>
                    <td><strong style="color: #b91c1c;">Voided</strong></td>
                    <td colspan="3" style="color: #b91c1c;">
                        Reason: {{ $token->void_reason }}
                    </td>
                </tr>
            @endif
        </tbody>
    </table>

    {{-- Formal Signatures Block --}}
    <table class="signatures-table">
        <tr>
            <td>
                <div class="sig-line">
                    <p class="sig-role">Payment Received By</p>
                    <p class="sig-name">{{ $token->claimant_name }}</p>
                    <p class="sig-date">Claimant Signature: ____________</p>
                </div>
            </td>
            <td>
                <div class="sig-line">
                    <p class="sig-role">Token Issued By</p>
                    <p class="sig-name">{{ $token->issuedBy->full_name }}</p>
                    <p class="sig-date">{{ $token->issuedBy->designation }} · {{ $token->issued_at->format('d M Y') }}</p>
                </div>
            </td>
            <td>
                <div class="sig-line">
                    <p class="sig-role">Payment Released By</p>
                    <p class="sig-name">{{ $token->paidBy ? $token->paidBy->full_name : 'Cashier / Accounts' }}</p>
                    <p class="sig-date">{{ $token->paid_at ? $token->paid_at->format('d M Y') : 'Date: _______________' }}</p>
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
