<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $branches = [
            // --- MAIN CAMPUS COLLEGES ---
            [
                'code' => 'CEIT',
                'name' => 'CvSU Main - College of Engineering and Information Technology',
                'type' => 'Main',
                'address' => 'Indang, Cavite',
            ],
            [
                'code' => 'CAS',
                'name' => 'CvSU Main - College of Arts and Sciences',
                'type' => 'Main',
                'address' => 'Indang, Cavite',
            ],
            [
                'code' => 'CAFENR',
                'name' => 'CvSU Main - College of Agriculture, Food, Environment and Natural Resources',
                'type' => 'Main',
                'address' => 'Indang, Cavite',
            ],
            [
                'code' => 'CCJ',
                'name' => 'CvSU Main - College of Criminal Justice',
                'type' => 'Main',
                'address' => 'Indang, Cavite',
            ],
            [
                'code' => 'CED',
                'name' => 'CvSU Main - College of Education',
                'type' => 'Main',
                'address' => 'Indang, Cavite',
            ],
            [
                'code' => 'CEMDS',
                'name' => 'CvSU Main - College of Economics, Management and Development Studies',
                'type' => 'Main',
                'address' => 'Indang, Cavite',
            ],
            [
                'code' => 'CON',
                'name' => 'CvSU Main - College of Nursing',
                'type' => 'Main',
                'address' => 'Indang, Cavite',
            ],
            [
                'code' => 'CSPEAR',
                'name' => 'CvSU Main - College of Sports, Physical Education and Recreation',
                'type' => 'Main',
                'address' => 'Indang, Cavite',
            ],
            [
                'code' => 'CVMBS',
                'name' => 'CvSU Main - College of Veterinary Medicine and Biomedical Sciences',
                'type' => 'Main',
                'address' => 'Indang, Cavite',
            ],
            [
                'code' => 'GS',
                'name' => 'CvSU Main - Graduate School',
                'type' => 'Main',
                'address' => 'Indang, Cavite',
            ],

            // --- SATELLITE CAMPUSES ---
            [
                'code' => 'SILANG',
                'name' => 'CvSU Silang Campus',
                'type' => 'Satellite',
                'address' => 'Silang, Cavite',
            ],
            [
                'code' => 'BACOOR',
                'name' => 'CvSU Bacoor City Campus',
                'type' => 'Satellite',
                'address' => 'Bacoor, Cavite',
            ],
            [
                'code' => 'CAVITE',
                'name' => 'CvSU Cavite City Campus',
                'type' => 'Satellite',
                'address' => 'Cavite City, Cavite',
            ],
            [
                'code' => 'CARMONA',
                'name' => 'CvSU Carmona Campus',
                'type' => 'Satellite',
                'address' => 'Carmona, Cavite',
            ],
            [
                'code' => 'GEN-TRIAS',
                'name' => 'CvSU General Trias City Campus',
                'type' => 'Satellite',
                'address' => 'General Trias, Cavite',
            ],
            [
                'code' => 'IMUS',
                'name' => 'CvSU Imus Campus',
                'type' => 'Satellite',
                'address' => 'Imus, Cavite',
            ],
            [
                'code' => 'TRECE',
                'name' => 'CvSU Trece Martires City Campus',
                'type' => 'Satellite',
                'address' => 'Trece Martires, Cavite',
            ],
            [
                'code' => 'NAIC',
                'name' => 'CvSU Naic Campus',
                'type' => 'Satellite',
                'address' => 'Naic, Cavite',
            ],
            [
                'code' => 'ROSARIO',
                'name' => 'CvSU Rosario Campus',
                'type' => 'Satellite',
                'address' => 'Rosario, Cavite',
            ],
            [
                'code' => 'TANZA',
                'name' => 'CvSU Tanza Campus',
                'type' => 'Satellite',
                'address' => 'Tanza, Cavite',
            ],
            [
                'code' => 'MARAGONDON',
                'name' => 'CvSU Maragondon Campus',
                'type' => 'Satellite',
                'address' => 'Maragondon, Cavite',
            ],
        ];

        foreach ($branches as $branch) {
            // Use 'code' to check if the branch exists since branch_id is removed
            Branch::updateOrCreate(
                ['code' => $branch['code']],
                $branch
            );
        }
    }
}
