<?php

namespace App\Exports;

use App\Services\SettingService;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Shared workbook furniture for every export: the school's title across the
 * top, a dark header band, a frozen header row, an autofilter and proper number
 * formatting. These are real .xlsx files, not CSV renamed.
 */
abstract class Workbook
{
    protected const FONT = 'Arial';

    protected const HEADER_FILL = 'FF0E1A24';

    protected const MONEY_FORMAT = '#,##0.00';

    protected Spreadsheet $book;

    public function __construct(protected SettingService $settings)
    {
        $this->book = new Spreadsheet;
        $this->book->getProperties()
            ->setCreator($this->settings->schoolName().' — Stock and Procurement')
            ->setTitle($this->title());
    }

    abstract public function title(): string;

    abstract public function filename(): string;

    abstract protected function build(): void;

    public function download(): StreamedResponse
    {
        $this->build();
        $this->book->setActiveSheetIndex(0);

        $filename = $this->filename();

        return response()->streamDownload(function () {
            (new Xlsx($this->book))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    /** The school title and a subtitle across the top of a sheet. */
    protected function titleRows(Worksheet $sheet, string $heading, string $subtitle, int $columns): void
    {
        $sheet->mergeCells([1, 1, $columns, 1]);
        $sheet->setCellValue([1, 1], mb_strtoupper($this->settings->schoolName()).' — '.$heading);
        $sheet->getStyle('A1')->getFont()->setName(self::FONT)->setBold(true)->setSize(13);

        $sheet->mergeCells([1, 2, $columns, 2]);
        $sheet->setCellValue([1, 2], $subtitle);
        $sheet->getStyle('A2')->getFont()->setName(self::FONT)->setSize(9);
        $sheet->getStyle('A2')->getFont()->getColor()->setARGB('FF6B7280');
    }

    /** Style the header row, freeze it, and switch on the autofilter. */
    protected function headerRow(Worksheet $sheet, int $row, array $headings): void
    {
        foreach ($headings as $i => $heading) {
            $sheet->setCellValue([$i + 1, $row], $heading);
        }

        $last = $this->columnLetter(count($headings));
        $range = 'A'.$row.':'.$last.$row;

        $style = $sheet->getStyle($range);
        $style->getFont()->setName(self::FONT)->setBold(true)->setSize(10);
        $style->getFont()->getColor()->setARGB('FFFFFFFF');
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::HEADER_FILL);
        $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $style->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN)
            ->getColor()->setARGB('FF8FA6B5');

        $sheet->getRowDimension($row)->setRowHeight(26);
        $sheet->freezePane('A'.($row + 1));
        $sheet->setAutoFilter($range);
    }

    /** Body font, plus a light border under every row so long tables stay readable. */
    protected function bodyStyle(Worksheet $sheet, int $firstRow, int $lastRow, int $columns): void
    {
        if ($lastRow < $firstRow) {
            return;
        }

        $range = 'A'.$firstRow.':'.$this->columnLetter($columns).$lastRow;
        $sheet->getStyle($range)->getFont()->setName(self::FONT)->setSize(10);
        $sheet->getStyle($range)->getBorders()->getBottom()
            ->setBorderStyle(Border::BORDER_HAIR)->getColor()->setARGB('FFE5E7EB');
    }

    protected function moneyColumns(Worksheet $sheet, array $columns, int $firstRow, int $lastRow): void
    {
        if ($lastRow < $firstRow) {
            return;
        }

        foreach ($columns as $column) {
            $letter = $this->columnLetter($column);
            $sheet->getStyle($letter.$firstRow.':'.$letter.$lastRow)
                ->getNumberFormat()->setFormatCode(self::MONEY_FORMAT);
        }
    }

    protected function autoSize(Worksheet $sheet, int $columns): void
    {
        for ($i = 1; $i <= $columns; $i++) {
            $sheet->getColumnDimension($this->columnLetter($i))->setAutoSize(true);
        }
    }

    protected function columnLetter(int $index): string
    {
        return Coordinate::stringFromColumnIndex($index);
    }

    protected function stamp(): string
    {
        return 'Generated '.now()->format('d M Y, H:i').' by '.auth()->user()->full_name;
    }
}
