<?php

namespace Database\Seeders;

use App\Models\Jurusan;
use Illuminate\Database\Seeder;

class JurusanSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'X' => ['MPLB 1', 'MPLB 2', 'MPLB 3', 'TJKT', 'PPLG', 'AKL', 'PM'],
            'XI' => ['RPL', 'TKJ', 'MP 1', 'MP 2', 'MP 3', 'AK', 'PM'],
            'XII' => ['RPL', 'TKJ', 'BD', 'MP 1', 'MP 2', 'AK 1', 'AK 2'],
        ];

        $this->command->info('Seeding Jurusan table...');

        Jurusan::truncate();

        foreach ($data as $kelas => $jurusans) {
            foreach ($jurusans as $namaJurusan) {
                Jurusan::create([
                    'kelas' => $kelas,
                    'jurusan' => $namaJurusan,
                ]);
            }
        }

        $this->command->info('Jurusan table seeded successfully.');
    }
}
