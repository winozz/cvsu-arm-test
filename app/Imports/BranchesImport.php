<?php

namespace App\Imports;

use LogicException;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * @deprecated Legacy branch imports were retired after the move to campuses
 * and colleges.
 */
class BranchesImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        throw new LogicException('Legacy branch imports are no longer supported.');
    }
}
