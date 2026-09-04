<?php

namespace App\Exports;

use App\Enums\Lifespan;
use App\Services\InventoryService;
use App\Services\SettingService;
use App\Support\FiscalYear;
use Illuminate\Support\Carbon;

/**
 * The register as the school reads it: two sheets, Durable and Consumable, each
 * one row per item type with a column per block.
 *
 * Rows keep the school's Code + Sequential Number ordering — category, then
 * code prefix — so the export lines up with the original sheet.
 */
class StockRegisterExport extends Workbook
{
    public function __construct(SettingService $settings, private InventoryService $inventory)
    {
        parent::__construct($settings);
    }

    public function title(): string
    {
        return 'Stock Register';
    }

    public function filename(): string
    {
        return 'stock-register-'.now()->format('Y-m-d').'.xlsx';
    }

    protected function build(): void
    {
        $first = true;

        foreach (Lifespan::cases() as $lifespan) {
            $sheet = $first
                ? $this->book->getActiveSheet()
                : $this->book->createSheet();

            $first = false;
            $sheet->setTitle($lifespan === Lifespan::DURABLE ? 'Durable Assets' : 'Consumables');

            $register = $this->inventory->register(lifespan: $lifespan);
            $blocks = $register['blocks'];

            $headings = array_merge(
                ['S.N.', 'Item', 'Code', 'Category', 'Subcategory'],
                $blocks->pluck('name')->all(),
                ['Total', 'Last Counted', 'Counted By'],
            );

            $columns = count($headings);

            $this->titleRows(
                $sheet,
                $lifespan->label().' Register',
                'Fiscal year '.FiscalYear::label().'. '.$this->stamp(),
                $columns,
            );

            $this->headerRow($sheet, 4, $headings);

            // Chronological within category, matching the school's own sheet.
            $rows = $register['rows']
                ->sortBy([['category', 'asc'], ['code_prefix', 'asc']])
                ->values();

            $row = 5;
            $serial = 1;

            foreach ($rows as $item) {
                $values = [
                    $serial++,
                    $item['item_name'],
                    $item['code_prefix'],
                    $item['category'],
                    $item['subcategory'] ?? '',
                ];

                foreach ($blocks as $block) {
                    $values[] = $item['by_block'][$block->name] ?? 0;
                }

                $values[] = $item['total'];
                $values[] = $item['last_counted_at']
                    ? Carbon::parse($item['last_counted_at'])->format('d M Y')
                    : '';
                $values[] = $item['last_counted_by'] ?? '';

                foreach ($values as $i => $value) {
                    $sheet->setCellValue([$i + 1, $row], $value);
                }

                $row++;
            }

            $this->bodyStyle($sheet, 5, $row - 1, $columns);
            $this->autoSize($sheet, $columns);
        }
    }
}
