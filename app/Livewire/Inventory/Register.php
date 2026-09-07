<?php

namespace App\Livewire\Inventory;

use App\Enums\Lifespan;
use App\Models\Category;
use App\Services\InventoryService;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The register exactly as the school reads it on paper: one row per item type,
 * one column per block, plus the running total.
 */
class Register extends Component
{
    #[Url(except: '')]
    public string $lifespan = '';

    #[Url(except: '')]
    public string $categoryId = '';

    #[Url(except: '')]
    public string $search = '';

    public function clearFilters(): void
    {
        $this->reset(['lifespan', 'categoryId', 'search']);
    }

    public function render(InventoryService $inventory): View
    {
        $register = $inventory->register(
            lifespan: $this->lifespan ? Lifespan::from($this->lifespan) : null,
            categoryId: $this->categoryId ?: null,
            search: $this->search ?: null,
        );

        return view('livewire.inventory.register', [
            'blocks' => $register['blocks'],
            'rows' => $register['rows'],
            'categories' => Category::active()->orderBy('sort_order')->get(),
        ])->title('Stock Register');
    }

    public function exportCsv(InventoryService $inventory): StreamedResponse
    {
        $register = $inventory->register(
            lifespan: $this->lifespan ? Lifespan::from($this->lifespan) : null,
            categoryId: $this->categoryId ?: null,
            search: $this->search ?: null,
        );

        $blocks = $register['blocks'];
        $rows = $register['rows'];
        $fileName = 'stock_register_'.now()->format('Y_m_d_His').'.csv';

        return response()->streamDownload(function () use ($blocks, $rows) {
            $handle = fopen('php://output', 'w');

            $headers = ['Item Code', 'Item Name', 'Category', 'Lifespan', 'UOM'];
            foreach ($blocks as $block) {
                $headers[] = $block->name;
            }
            $headers[] = 'Total Stock';
            $headers[] = 'Indicative Rate (NPR)';
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                $item = $row['item'];
                $data = [
                    $item->code_prefix,
                    $item->name,
                    $item->category?->name ?? 'General',
                    $item->lifespan->value ?? '',
                    $item->unit_of_measure,
                ];
                foreach ($blocks as $block) {
                    $data[] = $row['by_block'][$block->id] ?? 0;
                }
                $data[] = $row['total'];
                $data[] = $item->indicative_rate ?? '0.00';
                fputcsv($handle, $data);
            }

            fclose($handle);
        }, $fileName, ['Content-Type' => 'text/csv']);
    }
}
