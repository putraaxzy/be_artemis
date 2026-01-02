<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class JurusanSeeder extends Seeder
{

    public function run(): void
    {
        $jurusans = $this->getJurusans();
        $this->command->info('Daftar Jurusan yang tersedia:');
        $this->command->newLine();
        
        foreach ($jurusans as $kelas => $items) {
            $this->command->line("KELAS {$kelas}:");
            foreach ($items as $jurusan) {
                $this->command->line("  - {$jurusan}");
            }
            $this->command->newLine();
        }
    }

    private function getJurusans(): array
    {
        return [
            'X' => [
                'MPLB 1',
                'MPLB 2',
                'MPLB 3',
                'TJKT',
                'PPLG',
                'AKL',
                'PM',
            ],
            'XI' => [
                'RPL',
                'TKJ',
                'MP 1',
                'MP 2',
                'MP 3',
                'AK',
                'PM',
            ],
            'XII' => [
                'RPL',
                'TKJ',
                'BD',
                'MP 1',
                'MP 2',
                'AK 1',
                'AK 2',
            ],
        ];
    }
}
