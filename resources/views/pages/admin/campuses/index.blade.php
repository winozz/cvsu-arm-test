<?php

use Livewire\Component;

new class extends Component {
    //
};
?>

<div class="">
    <div class="mb-6 flex justify-between items-center">
        <h1 class="text-xl font-bold dark:text-white">Campuses</h1>
    </div>
    <div class="bg-white p-6 rounded-lg shadow dark:bg-zinc-800">
        <livewire:admin.tables.campuses-table />
    </div>
</div>
