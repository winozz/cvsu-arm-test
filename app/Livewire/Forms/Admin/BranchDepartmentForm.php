<?php

namespace App\Livewire\Forms\Admin;

use Livewire\Form;
use LogicException;

/**
 * @deprecated Branch-scoped department management was retired with the move to
 * campuses/colleges/departments. This form remains only as a placeholder.
 */
class BranchDepartmentForm extends Form
{
    public function store(): void
    {
        throw new LogicException('Legacy branch department management is no longer supported.');
    }

    public function update(): void
    {
        throw new LogicException('Legacy branch department management is no longer supported.');
    }
}
