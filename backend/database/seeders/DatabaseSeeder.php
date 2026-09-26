<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::create(['name' => 'Admin Panitia', 'email' => 'admin@silatevent.test', 'password' => Hash::make('password'), 'role' => 'admin']);
        User::create(['name' => 'Official Demo', 'email' => 'official@silatevent.test', 'password' => Hash::make('password'), 'role' => 'official']);
        DB::table('events')->insert(['name' => 'Kejuaraan Antar Cabang Tri Sukma Indonesia 2026', 'description' => 'Kejuaraan pencak silat antar cabang Tri Sukma Indonesia.', 'location' => 'Lapangan Sekte Pinang Merah', 'start_date' => '2026-12-12', 'end_date' => '2026-12-14', 'registration_deadline' => '2026-11-30', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([
            ['name' => 'Pra Remaja Putra - Kelas A', 'gender' => 'male', 'age_min' => 10, 'age_max' => 13, 'weight_min' => 25, 'weight_max' => 30, 'fee' => 150000],
            ['name' => 'Remaja Putri - Kelas B', 'gender' => 'female', 'age_min' => 14, 'age_max' => 17, 'weight_min' => 40, 'weight_max' => 45, 'fee' => 200000],
            ['name' => 'Dewasa Putra - Kelas C', 'gender' => 'male', 'age_min' => 18, 'age_max' => 35, 'weight_min' => 55, 'weight_max' => 60, 'fee' => 250000],
            ['name' => 'Dewasa Putri - Kelas D', 'gender' => 'female', 'age_min' => 18, 'age_max' => 35, 'weight_min' => 50, 'weight_max' => 55, 'fee' => 250000],
        ] as $category) {
            DB::table('categories')->updateOrInsert(
                ['name' => $category['name']],
                [...$category, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
