<?php

namespace App\Livewire\Setup;

use App\Models\Category;
use App\Models\ItemType;
use App\Models\Location;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\View\View;
use Livewire\Component;

class Index extends Component
{
    public function render(SettingService $settings): View
    {
        return view('livewire.setup.index', [
            'counts' => [
                'categories' => Category::active()->count(),
                'locations' => Location::active()->count(),
                'items' => ItemType::active()->count(),
                'staff' => User::where('is_active', true)->count(),
                'tiers' => $settings->tiers()->count(),
            ],
        ])->title('Setup');
    }
}
