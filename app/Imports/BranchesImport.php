<?php

namespace App\Imports;

use App\Models\Branch;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class BranchesImport implements ToModel, WithHeadingRow
{
    /**
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        // Skip empty rows to prevent errors if the excel file has blank lines at the bottom
        if (! isset($row['code']) || ! isset($row['name'])) {
            return null;
        }

        return new Branch([
            'code' => $row['code'],
            'name' => $row['name'],
            'type' => $row['type'] ?? 'Main',
            'address' => $row['address'] ?? '',
            'is_active' => isset($row['is_active']) ? (bool) $row['is_active'] : true,
        ]);
    }
}
