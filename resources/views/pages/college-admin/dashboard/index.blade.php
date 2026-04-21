<?php

use App\Traits\CanManage;
use Livewire\Component;

new class extends Component {
    use CanManage;

    public function mount(): void
    {
        $this->ensureCanManage('departments.view');
    }
};
?>

<div>
    {{-- Simplicity is an acquired taste. - Katharine Gerould --}}
    College Admin Dashboard
</div>
