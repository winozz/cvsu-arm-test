<?php

namespace App\Livewire\Forms\Admin;

use App\Models\Branch;
use Illuminate\Validation\Rule;
use Livewire\Form;

class BranchForm extends Form
{
    public ?Branch $branch = null;

    public $code = '';

    public $name = '';

    public $type = 'Main';

    public $address = '';

    public $is_active = true;

    public function rules()
    {
        return [
            'code' => [
                'required',
                'string',
                Rule::unique('branches', 'code')->ignore($this->branch?->id)->whereNull('deleted_at'),
            ],
            'name' => 'required|string',
            'type' => 'required|in:Main,Satellite',
            'address' => 'required|string',
            'is_active' => 'boolean',
        ];
    }

    public function setBranch(Branch $branch)
    {
        $this->branch = $branch;
        $this->code = $branch->code;
        $this->name = $branch->name;
        $this->type = $branch->type;
        $this->address = $branch->address;
        $this->is_active = $branch->is_active;
    }

    public function store()
    {
        $this->validate();

        if (empty($this->branch)) {
            Branch::create($this->all());
        } else {
            $this->branch->update($this->all());
        }

        $this->reset(['code', 'name', 'type', 'address', 'is_active', 'branch']);
    }
}
