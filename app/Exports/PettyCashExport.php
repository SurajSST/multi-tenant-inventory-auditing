<?php

namespace App\Exports;

use App\Models\PettyCashToken;
use App\Services\SettingService;
use App\Support\Money;

/**
 * Every petty cash token, oldest first, with both names on each row — who
 * issued it and who released the payment. Those are never the same person, and
 * this export is where that is easiest to check.
 */
class PettyCashExport extends Workbook
{
    public function __construct(
        SettingService $settings,
        private ?string $from = null,
        private ?string $to = null,
    ) {
        parent::__construct($settings);
    }

    public function title(): string
    {
        return 'Petty Cash Register';
    }

    public function filename(): string
    {
        return 'petty-cash-'.now()->format('Y-m-d').'.xlsx';
    }

    protected function build(): void
    {
        $sheet = $this->book->getActiveSheet();
        $sheet->setTitle('Petty Cash');

        $headings = [
            'S.N.', 'Token', 'Issued', 'Bill No.', 'Bill Date', 'Vendor',
            'Claimant', 'Purpose', 'Amount (Rs.)', 'Ceiling at Issue (Rs.)',
            'Status', 'Issued By', 'Paid By', 'Paid', 'Void Reason', 'Fiscal Year',
        ];

        $columns = count($headings);

        $this->titleRows(
            $sheet,
            'Petty Cash Register',
            'Ceiling now '.Money::npr($this->settings->pettyCashCeiling()).'. '.$this->stamp(),
            $columns,
        );

        $this->headerRow($sheet, 4, $headings);

        $tokens = PettyCashToken::with(['issuedBy', 'paidBy'])
            ->when($this->from, fn ($q) => $q->where('issued_at', '>=', $this->from.' 00:00:00'))
            ->when($this->to, fn ($q) => $q->where('issued_at', '<=', $this->to.' 23:59:59'))
            ->orderBy('issued_at')
            ->get();

        $row = 5;
        $serial = 1;

        foreach ($tokens as $token) {
            $values = [
                $serial++,
                $token->serial,
                $token->issued_at->format('d M Y H:i'),
                $token->bill_no,
                $token->bill_date?->format('d M Y') ?? '',
                $token->vendor_name,
                $token->claimant_name,
                $token->purpose,
                (float) $token->amount,
                (float) $token->ceiling_at_issue,
                $token->status->label(),
                $token->issuedBy->full_name,
                $token->paidBy?->full_name ?? '',
                $token->paid_at?->format('d M Y H:i') ?? '',
                $token->void_reason ?? '',
                $token->fiscal_year,
            ];

            foreach ($values as $i => $value) {
                $sheet->setCellValue([$i + 1, $row], $value);
            }

            $row++;
        }

        $this->bodyStyle($sheet, 5, $row - 1, $columns);
        $this->moneyColumns($sheet, [9, 10], 5, $row - 1);
        $this->autoSize($sheet, $columns);
    }
}
