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
    <h1 class="text-xl font-bold dark:text-white">My Schedules</h1>

    <div class="mt-4">
        Faculty schedule content goes here.
    </div>
</div>
