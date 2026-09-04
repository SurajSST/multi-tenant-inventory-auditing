<?php

namespace App\Livewire;

use App\Services\DemandService;
use App\Services\OrderService;
use App\Services\PettyCashService;
use App\Services\ReportService;
use Illuminate\View\View;
use Livewire\Component;

class Dashboard extends Component
{
    public function render(
        ReportService $reports,
        DemandService $demands,
        OrderService $orders,
        PettyCashService $petty,
    ): View {
        $user = auth()->user();
        $spend = $reports->monthlySpend();

        // A real month-over-month comparison, not an invented figure — this
        // system's whole point is that numbers on screen are ones somebody
        // can trust.
        $thisMonth = (float) ($spend->last()['amount'] ?? 0);
        $lastMonth = (float) ($spend->count() > 1 ? $spend[$spend->count() - 2]['amount'] : 0);
        $spendTrend = null;

        if ($lastMonth > 0) {
            $change = round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1);
            $spendTrend = [
                'text' => ($change >= 0 ? '↑ +' : '↓ ').abs($change).'% vs last month',
                'direction' => $change >= 0 ? 'up' : 'down',
            ];
        }

        return view('livewire.dashboard', [
            'stats' => $reports->dashboard(),
            'myQueue' => $demands->myQueue($user),
            'toOrder' => $user->can('place-orders') ? $orders->awaitingOrder() : collect(),
            'toReceive' => $user->can('receive-goods')
                ? $orders->awaitingReceipt($user)
                : collect(),
            'petty' => $user->can('handle-accounts') ? $petty->summary() : null,
            'pettySpend' => $petty->monthlySpend(),
            'spend' => $spend,
            'spendTrend' => $spendTrend,
        ])->title('Dashboard');
    }
}
