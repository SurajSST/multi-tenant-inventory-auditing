<?php

namespace App\Exports;

use App\Models\ItemType;
use App\Services\InventoryService;
use App\Services\SettingService;
use App\Support\FiscalYear;

/**
 * One line per physical unit: CHAIR.S.1, CHAIR.S.2, CHAIR.S.3 …
 *
 * The running number continues in order under each code prefix, across blocks
 * in block order — the school's existing Code + Sequential Number scheme,
 * reproduced exactly.
 */
class UnitListExport extends Workbook
{
    public function __construct(SettingService $settings, private InventoryService $inventory)
    {
        parent::__construct($settings);
    }

    public function title(): string
    {
        return 'Unit List';
    }

    public function filename(): string
    {
        return 'unit-list-'.now()->format('Y-m-d').'.xlsx';
    }

    protected function build(): void
    {
        $sheet = $this->book->getActiveSheet();
        $sheet->setTitle('Units');

        $headings = ['S.N.', 'Unit Code', 'Item', 'Code Prefix', 'Unit No.', 'Block', 'Category', 'Slot'];
        $columns = count($headings);

        $this->titleRows(
            $sheet,
            'Unit List',
            'Every physical unit with its own code. Fiscal year '.FiscalYear::label().'. '.$this->stamp(),
            $columns,
        );

        $this->headerRow($sheet, 4, $headings);

        $items = ItemType::active()
            ->with(['category'])
            ->orderBy('code_prefix')
            ->get();

        $row = 5;
        $serial = 1;

        foreach ($items as $item) {
            $expanded = $this->inventory->unitCodes($item->id);

            foreach ($expanded['units'] as $unit) {
                $values = [
                    $serial++,
                    $unit['unit_code'],
                    $item->name,
                    $item->code_prefix,
                    $unit['unit_no'],
                    $unit['block'],
                    $item->category->name,
                    $item->lifespan->label(),
                ];

                foreach ($values as $i => $value) {
                    $sheet->setCellValue([$i + 1, $row], $value);
                }

                $row++;
            }
        }

        $this->bodyStyle($sheet, 5, $row - 1, $columns);
        $this->autoSize($sheet, $columns);
    }
}
