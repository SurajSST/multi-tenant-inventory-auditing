<?php

namespace App\Exports;

use App\Models\AuditLog;
use App\Services\SettingService;

/**
 * The trail, oldest first. Because the table is append-only at the database
 * level, this export is a complete and unedited record of what happened.
 */
class AuditTrailExport extends Workbook
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
        return 'Audit Trail';
    }

    public function filename(): string
    {
        return 'audit-trail-'.now()->format('Y-m-d').'.xlsx';
    }

    protected function build(): void
    {
        $sheet = $this->book->getActiveSheet();
        $sheet->setTitle('Audit Trail');

        $headings = ['S.N.', 'When', 'Who', 'Designation', 'Action', 'Record', 'Detail', 'IP'];
        $columns = count($headings);

        $this->titleRows(
            $sheet,
            'Audit Trail',
            'Append-only: no entry here has ever been edited or removed. '.$this->stamp(),
            $columns,
        );

        $this->headerRow($sheet, 4, $headings);

        $row = 5;
        $serial = 1;

        AuditLog::with(['actor', 'actor.currentMembership'])
            ->when($this->from, fn ($q) => $q->where('at', '>=', $this->from.' 00:00:00'))
            ->when($this->to, fn ($q) => $q->where('at', '<=', $this->to.' 23:59:59'))
            ->orderBy('at')
            ->orderBy('id')
            ->chunk(500, function ($entries) use ($sheet, &$row, &$serial) {
                foreach ($entries as $entry) {
                    $values = [
                        $serial++,
                        $entry->at->format('d M Y H:i:s'),
                        $entry->actor?->full_name ?? 'System',
                        $entry->actor?->designation ?? '',
                        $entry->action,
                        $entry->entity,
                        $entry->detail,
                        $entry->ip ?? '',
                    ];

                    foreach ($values as $i => $value) {
                        $sheet->setCellValue([$i + 1, $row], $value);
                    }

                    $row++;
                }
            });

        $this->bodyStyle($sheet, 5, $row - 1, $columns);
        $this->autoSize($sheet, $columns);

        // The detail column carries sentences; auto-size would make it absurd.
        $sheet->getColumnDimension('G')->setAutoSize(false)->setWidth(80);
        $sheet->getStyle('G5:G'.max(5, $row - 1))->getAlignment()->setWrapText(true);
    }
}
