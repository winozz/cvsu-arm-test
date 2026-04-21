<?php

use App\Traits\CanManage;
use Livewire\Component;

new class extends Component {
    use CanManage;

    public function mount(): void
    {
        $this->ensureCanManage('schedules.assign');
    }
};
?>

<div>
    {{-- Smile, breathe, and go slowly. - Thich Nhat Hanh --}}
    Department Admin Dashboard
</div>
