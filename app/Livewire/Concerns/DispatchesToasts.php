<?php

namespace App\Livewire\Concerns;

trait DispatchesToasts
{
    protected function toast(string $message, string $tone = 'success', string $title = 'Success'): void
    {
        $this->dispatch('toast', message: $message, tone: $tone, title: $title);
    }
}
