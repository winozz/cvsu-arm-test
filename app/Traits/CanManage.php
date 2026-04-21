<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;

trait CanManage
{
    public function canManage(string $ability): bool
    {
        return (bool) Auth::user()?->can($ability);
    }

    public function ensureCanManage(string $ability): void
    {
        abort_unless($this->canManage($ability), 403);
    }
}
