<?php

namespace App\Exports;

use App\Services\ReportService;
use App\Services\SettingService;
use Illuminate\Support\Carbon;

/**
 * The whole procurement chain on one row per demand form: who raised it, who
 * ordered, who verified the goods, and what was billed. Chronological, oldest
 * first, so it reads as a running record.
 */
class ProcurementExport extends Workbook
{
    public function __construct(
        SettingService $settings,
        private ReportService $reports,
        private ?string $from = null,
        private ?string $to = null,
    ) {
        parent::__construct($settings);
    }

    public function title(): string
    {
        return 'Procurement Register';
    }

    public function filename(): string
    {
        return 'procurement-'.now()->format('Y-m-d').'.xlsx';
    }

    protected function build(): void
    {
        $sheet = $this->book->getActiveSheet();
        $sheet->setTitle('Procurement');

        $headings = [
            'S.N.', 'Demand Ref', 'Raised', 'Raised By', 'Status', 'Approved (Rs.)',
            'PO Ref', 'Ordered By', 'Ordered', 'Ordered (Rs.)',
            'Received By', 'Received', 'Discrepancy',
            'Bill No.', 'Billed (Rs.)', 'Match',
        ];

        $columns = count($headings);

        $this->titleRows(
            $sheet,
            'Procurement Register',
            $this->period().'. '.$this->stamp(),
            $columns,
        );

        $this->headerRow($sheet, 4, $headings);

        $rows = $this->reports->procurementTimeline($this->from, $this->to);

        $row = 5;
        $serial = 1;

        foreach ($rows as $r) {
            $values = [
                $serial++,
                $r->demand_ref,
                $r->raised_at ? Carbon::parse($r->raised_at)->format('d M Y') : '',
                $r->raised_by,
                $r->status,
                (float) $r->total_amount,
                $r->po_ref ?? '',
                $r->ordered_by ?? '',
                $r->ordered_at ? Carbon::parse($r->ordered_at)->format('d M Y') : '',
                $r->order_amount === null ? '' : (float) $r->order_amount,
                $r->received_by ?? '',
                $r->received_at ? Carbon::parse($r->received_at)->format('d M Y') : '',
                $r->discrepancy_note ?? '',
                $r->bill_no ?? '',
                $r->bill_amount === null ? '' : (float) $r->bill_amount,
                $r->match_status ?? '',
            ];

            foreach ($values as $i => $value) {
                $sheet->setCellValue([$i + 1, $row], $value);
            }

            $row++;
        }

        $this->bodyStyle($sheet, 5, $row - 1, $columns);
        $this->moneyColumns($sheet, [6, 10, 15], 5, $row - 1);
        $this->autoSize($sheet, $columns);

        $this->spendSheet();
    }

    /** A second sheet: spend broken down the way management asks for it. */
    private function spendSheet(): void
    {
        $sheet = $this->book->createSheet();
        $sheet->setTitle('Spend by Category');

        $headings = ['Category', 'Subcategory', 'Bills', 'Approved Value (Rs.)', 'Units Received'];
        $columns = count($headings);

        $this->titleRows($sheet, 'Spend by Category', $this->period().'. '.$this->stamp(), $columns);
        $this->headerRow($sheet, 4, $headings);

        $row = 5;

        foreach ($this->reports->spendByCategory($this->from, $this->to) as $r) {
            $values = [
                $r->category,
                $r->subcategory,
                (int) $r->bills,
                (float) $r->approved_value,
                (int) $r->units_received,
            ];

            foreach ($values as $i => $value) {
                $sheet->setCellValue([$i + 1, $row], $value);
            }

            $row++;
        }

        $this->bodyStyle($sheet, 5, $row - 1, $columns);
        $this->moneyColumns($sheet, [4], 5, $row - 1);
        $this->autoSize($sheet, $columns);
    }

    private function period(): string
    {
        if (! $this->from && ! $this->to) {
            return 'All records';
        }

        return trim(sprintf(
            '%s to %s',
            $this->from ? Carbon::parse($this->from)->format('d M Y') : 'the beginning',
            $this->to ? Carbon::parse($this->to)->format('d M Y') : 'today',
        ));
    }
}
