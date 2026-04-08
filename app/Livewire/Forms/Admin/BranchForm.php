<?php

namespace App\Livewire\Forms\Admin;

use Livewire\Form;
use LogicException;

/**
 * @deprecated Branch management was retired when the project moved to
 * campuses/colleges/departments. This form remains only as a placeholder.
 */
class BranchForm extends Form
{
    public function store(): void
    {
        throw new LogicException('Branch management has been retired. Use campuses and colleges instead.');
    }
}
