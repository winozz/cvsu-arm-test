<?php

use App\Traits\CanManage;
use Livewire\Component;

new class extends Component {
    use CanManage;

    public function mount(): void
    {
        $this->ensureCanManage('faculty_schedules.view');
    }
};
?>

<div>
    <h1 class="text-xl font-bold dark:text-white">Faculty Dashboard</h1>

    <div class="mt-4">
        Main dashboard content goes here. You can create Livewire components and include them here as needed.
    </div>
</div>
