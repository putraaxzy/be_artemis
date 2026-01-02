<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class SiswaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $students = $this->getStudents();
        $defaultPassword = '12345678';
        
        // Array untuk menyimpan data kredensial
        $credentialsList = [];
        
        // Track username yang sudah digenerate dalam batch ini
        $usedUsernames = [];
        
        $studentsWithCredentials = array_map(function ($student) use ($defaultPassword, &$credentialsList, &$usedUsernames) {
            $username = $this->generateUsername($student['nama'], $usedUsernames);
            $usedUsernames[] = $username;
            
            // Simpan kredensial untuk output
            $credentialsList[] = [
                'nama' => $student['nama'],
                'kelas' => $student['kelas'],
                'jurusan' => $student['jurusan'],
                'username' => $username,
                'password' => $defaultPassword,
            ];
            
            return [
                'name' => $student['nama'],
                'kelas' => $student['kelas'],
                'jurusan' => $student['jurusan'],
                'username' => $username,
                'password' => Hash::make($defaultPassword),
                'telepon' => null,
                'role' => 'siswa',
                'avatar' => null,
                'username_changed_at' => null,
                'is_first_login' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }, $students);
        
        $chunked = array_chunk($studentsWithCredentials, 50);
        foreach ($chunked as $chunk) {
            DB::table('users')->insert($chunk);
        }

        // Generate output file
        $this->generateCredentialsFile($credentialsList);
        
        $this->command->info('Siswa berhasil di-seed!');
        $this->command->info('Default password: ' . $defaultPassword);
        $this->command->info('File kredensial: storage/app/siswa_credentials.txt');
    }
    
    /**
     * Generate file kredensial siswa
     */
    private function generateCredentialsFile(array $credentialsList): void
    {
        // Group by kelas dan jurusan
        $grouped = [];
        foreach ($credentialsList as $cred) {
            $key = $cred['kelas'] . ' ' . $cred['jurusan'];
            if (!isset($grouped[$key])) {
                $grouped[$key] = [];
            }
            $grouped[$key][] = $cred;
        }
        
        // Sort keys
        ksort($grouped);
        
        // Build output
        $output = "=================================================================\n";
        $output .= "                    DATA KREDENSIAL SISWA                        \n";
        $output .= "                    Generated: " . now()->format('Y-m-d H:i:s') . "              \n";
        $output .= "=================================================================\n\n";
        
        $output .= "Format: No | Nama | Username | Password\n";
        $output .= "Password default: 12345678\n";
        $output .= "Siswa WAJIB mengubah username & password saat login pertama.\n\n";
        
        $totalSiswa = 0;
        
        foreach ($grouped as $kelasJurusan => $students) {
            $output .= "-----------------------------------------------------------------\n";
            $output .= "KELAS: {$kelasJurusan}\n";
            $output .= "-----------------------------------------------------------------\n";
            
            $no = 1;
            foreach ($students as $student) {
                $output .= sprintf(
                    "%2d | %-40s | %-25s | %s\n",
                    $no,
                    $student['nama'],
                    $student['username'],
                    $student['password']
                );
                $no++;
                $totalSiswa++;
            }
            $output .= "\n";
        }
        
        $output .= "=================================================================\n";
        $output .= "TOTAL SISWA: {$totalSiswa}\n";
        $output .= "=================================================================\n";
        
        // Save to file
        $filePath = storage_path('app/siswa_credentials.txt');
        file_put_contents($filePath, $output);
        
        // Also create CSV version for Excel
        $csvOutput = "No,Nama,Kelas,Jurusan,Username,Password\n";
        $globalNo = 1;
        foreach ($grouped as $kelasJurusan => $students) {
            foreach ($students as $student) {
                $csvOutput .= sprintf(
                    "%d,\"%s\",%s,%s,%s,%s\n",
                    $globalNo,
                    $student['nama'],
                    $student['kelas'],
                    $student['jurusan'],
                    $student['username'],
                    $student['password']
                );
                $globalNo++;
            }
        }
        
        $csvPath = storage_path('app/siswa_credentials.csv');
        file_put_contents($csvPath, $csvOutput);
        
        $this->command->info('File TXT: ' . $filePath);
        $this->command->info('File CSV: ' . $csvPath);
    }

    private function generateUsername(string $nama, array $usedUsernames = []): string
    {
        // Ambil nama pertama dan belakang, konversi ke lowercase
        $parts = explode(' ', $nama);
        $firstName = strtolower($parts[0]);
        $lastName = strtolower(end($parts));
        
        // Kombinasi nama pertama dan belakang
        $baseUsername = $firstName . '.' . $lastName;
        
        // Ganti spasi dengan underscore dan hapus karakter khusus
        $username = preg_replace('/[^a-z0-9._]/', '', $baseUsername);
        
        // Jika sudah ada di database atau batch saat ini, tambahkan number
        $count = 1;
        $originalUsername = $username;
        while (
            in_array($username, $usedUsernames) || 
            DB::table('users')->where('username', $username)->exists()
        ) {
            $username = $originalUsername . $count;
            $count++;
        }
        
        return $username;
    }

    private function getStudents(): array
    {
        return [
            // X MPLB 1
            ['nama' => 'Aini Latifa Maharani', 'kelas' => 'X', 'jurusan' => 'MPLB 1'],
            ['nama' => 'Ajeng Safftri Puspita Ningrum', 'kelas' => 'X', 'jurusan' => 'MPLB 1'],
            ['nama' => 'Andini Eka Anuari', 'kelas' => 'X', 'jurusan' => 'MPLB 1'],
            ['nama' => 'Asifa Tsania Nur Kholiza', 'kelas' => 'X', 'jurusan' => 'MPLB 1'],
            ['nama' => 'Ayyatul Husna', 'kelas' => 'X', 'jurusan' => 'MPLB 1'],
            ['nama' => 'Dian Safftri', 'kelas' => 'X', 'jurusan' => 'MPLB 1'],
            ['nama' => 'Fitt Nur Lailatul Mafiroh', 'kelas' => 'X', 'jurusan' => 'MPLB 1'],
            ['nama' => 'Khaila Rizkia', 'kelas' => 'X', 'jurusan' => 'MPLB 1'],
            ['nama' => 'Khilyatul Milah', 'kelas' => 'X', 'jurusan' => 'MPLB 1'],
            ['nama' => 'Lia Putri Angraeni', 'kelas' => 'X', 'jurusan' => 'MPLB 1'],
            ['nama' => 'Liana Wulandari', 'kelas' => 'X', 'jurusan' => 'MPLB 1'],
            ['nama' => 'Luna Nopita Sari', 'kelas' => 'X', 'jurusan' => 'MPLB 1'],
            ['nama' => 'Nabila Meisyaroh', 'kelas' => 'X', 'jurusan' => 'MPLB 1'],
            ['nama' => 'Naila Nur Jaiz', 'kelas' => 'X', 'jurusan' => 'MPLB 1'],
            ['nama' => 'Nasya Abita Sulistia', 'kelas' => 'X', 'jurusan' => 'MPLB 1'],
            ['nama' => 'Ninda Aulia Faradista', 'kelas' => 'X', 'jurusan' => 'MPLB 1'],
            ['nama' => 'Novita Dewi', 'kelas' => 'X', 'jurusan' => 'MPLB 1'],
            ['nama' => 'Paulin Afriliana Ayu Chika', 'kelas' => 'X', 'jurusan' => 'MPLB 1'],
            ['nama' => 'Salsabila Melany Fidela Shani Rizal', 'kelas' => 'X', 'jurusan' => 'MPLB 1'],
            ['nama' => 'Selvia Ayu Wulandari', 'kelas' => 'X', 'jurusan' => 'MPLB 1'],
            ['nama' => 'Silawati', 'kelas' => 'X', 'jurusan' => 'MPLB 1'],
            ['nama' => 'Vaizatul Alvi Errania', 'kelas' => 'X', 'jurusan' => 'MPLB 1'],
            ['nama' => 'Vita Anggraeni', 'kelas' => 'X', 'jurusan' => 'MPLB 1'],
            ['nama' => 'Zakia Annur Messi', 'kelas' => 'X', 'jurusan' => 'MPLB 1'],
            ['nama' => 'Zhakia Rahmawati', 'kelas' => 'X', 'jurusan' => 'MPLB 1'],

            // X MPLB 2
            ['nama' => 'Aira Febryani', 'kelas' => 'X', 'jurusan' => 'MPLB 2'],
            ['nama' => 'Azizah Adha Aulia', 'kelas' => 'X', 'jurusan' => 'MPLB 2'],
            ['nama' => 'Citra Maulidina', 'kelas' => 'X', 'jurusan' => 'MPLB 2'],
            ['nama' => 'Dinda Ayu Nawang Sari', 'kelas' => 'X', 'jurusan' => 'MPLB 2'],
            ['nama' => 'Eka Ramadhani', 'kelas' => 'X', 'jurusan' => 'MPLB 2'],
            ['nama' => 'Eni Puji Lestari', 'kelas' => 'X', 'jurusan' => 'MPLB 2'],
            ['nama' => 'Febri Antika', 'kelas' => 'X', 'jurusan' => 'MPLB 2'],
            ['nama' => 'Gita Rismayanti', 'kelas' => 'X', 'jurusan' => 'MPLB 2'],
            ['nama' => 'Heni Fitriyanti', 'kelas' => 'X', 'jurusan' => 'MPLB 2'],
            ['nama' => 'Julia Mega Assyifa', 'kelas' => 'X', 'jurusan' => 'MPLB 2'],
            ['nama' => 'Meysha Safira Putri', 'kelas' => 'X', 'jurusan' => 'MPLB 2'],
            ['nama' => 'Naila Jauharotul Ngulum', 'kelas' => 'X', 'jurusan' => 'MPLB 2'],
            ['nama' => 'Nesya Aulia Agustin', 'kelas' => 'X', 'jurusan' => 'MPLB 2'],
            ['nama' => 'Nur Setyowati', 'kelas' => 'X', 'jurusan' => 'MPLB 2'],
            ['nama' => 'Putri Ayu Kinashi', 'kelas' => 'X', 'jurusan' => 'MPLB 2'],
            ['nama' => 'Tia Kariga Agustin', 'kelas' => 'X', 'jurusan' => 'MPLB 2'],
            ['nama' => 'Tiara Rahma Putri', 'kelas' => 'X', 'jurusan' => 'MPLB 2'],
            ['nama' => 'Valda Attalya Livlandini', 'kelas' => 'X', 'jurusan' => 'MPLB 2'],
            ['nama' => 'Vania Angel Laura', 'kelas' => 'X', 'jurusan' => 'MPLB 2'],
            ['nama' => 'Wilda Muna Khasana', 'kelas' => 'X', 'jurusan' => 'MPLB 2'],
            ['nama' => 'Zahra Azifa Putri', 'kelas' => 'X', 'jurusan' => 'MPLB 2'],
            ['nama' => 'Zara Kartika Wibowo', 'kelas' => 'X', 'jurusan' => 'MPLB 2'],

            // X MPLB 3
            ['nama' => 'Ani Aulia Muflihah', 'kelas' => 'X', 'jurusan' => 'MPLB 3'],
            ['nama' => 'Anik Maulina', 'kelas' => 'X', 'jurusan' => 'MPLB 3'],
            ['nama' => 'Aulia Fitriani', 'kelas' => 'X', 'jurusan' => 'MPLB 3'],
            ['nama' => 'Aurel Marta Wahyuni', 'kelas' => 'X', 'jurusan' => 'MPLB 3'],
            ['nama' => 'Choirun Nisa Nurhidayati', 'kelas' => 'X', 'jurusan' => 'MPLB 3'],
            ['nama' => 'Dea Natalia', 'kelas' => 'X', 'jurusan' => 'MPLB 3'],
            ['nama' => 'Devi Wulandari', 'kelas' => 'X', 'jurusan' => 'MPLB 3'],
            ['nama' => 'Dewi Sriyatun', 'kelas' => 'X', 'jurusan' => 'MPLB 3'],
            ['nama' => 'Dinda Ayu Sholekhah', 'kelas' => 'X', 'jurusan' => 'MPLB 3'],
            ['nama' => 'Dita Mulia Sari', 'kelas' => 'X', 'jurusan' => 'MPLB 3'],
            ['nama' => 'Gizca Patria Rahayu', 'kelas' => 'X', 'jurusan' => 'MPLB 3'],
            ['nama' => 'Isfihani Aslammiyah', 'kelas' => 'X', 'jurusan' => 'MPLB 3'],
            ['nama' => 'Keysa Septiana Rahmadhani', 'kelas' => 'X', 'jurusan' => 'MPLB 3'],
            ['nama' => 'Laelatul Rohmah', 'kelas' => 'X', 'jurusan' => 'MPLB 3'],
            ['nama' => 'Lulu Uswatun Khasanah', 'kelas' => 'X', 'jurusan' => 'MPLB 3'],
            ['nama' => 'Miftahqul Jannah', 'kelas' => 'X', 'jurusan' => 'MPLB 3'],
            ['nama' => 'Nurul Azizah', 'kelas' => 'X', 'jurusan' => 'MPLB 3'],
            ['nama' => 'Ratu Maulida Az Zahra', 'kelas' => 'X', 'jurusan' => 'MPLB 3'],
            ['nama' => 'Serly Lisa Febriyanti', 'kelas' => 'X', 'jurusan' => 'MPLB 3'],
            ['nama' => 'Sisca Wulansari', 'kelas' => 'X', 'jurusan' => 'MPLB 3'],
            ['nama' => 'Tyas Anindhita', 'kelas' => 'X', 'jurusan' => 'MPLB 3'],
            ['nama' => 'Yuliana Dita Puspitasari', 'kelas' => 'X', 'jurusan' => 'MPLB 3'],

            // X TJKT
            ['nama' => 'Adelia Oktaviani Putri', 'kelas' => 'X', 'jurusan' => 'TJKT'],
            ['nama' => 'Adhira Dwi Anjani', 'kelas' => 'X', 'jurusan' => 'TJKT'],
            ['nama' => 'Anindya Septiana Hidayah', 'kelas' => 'X', 'jurusan' => 'TJKT'],
            ['nama' => 'Aprilia Cahya Putri', 'kelas' => 'X', 'jurusan' => 'TJKT'],
            ['nama' => 'Aura Desihuta Maharani', 'kelas' => 'X', 'jurusan' => 'TJKT'],
            ['nama' => 'Chacha Dwi Meylani', 'kelas' => 'X', 'jurusan' => 'TJKT'],
            ['nama' => 'Dhea Permata Cristiant', 'kelas' => 'X', 'jurusan' => 'TJKT'],
            ['nama' => 'Dina Amanda', 'kelas' => 'X', 'jurusan' => 'TJKT'],
            ['nama' => 'Dina Setyaningrum', 'kelas' => 'X', 'jurusan' => 'TJKT'],
            ['nama' => 'Grandy Surya Tjahyadi', 'kelas' => 'X', 'jurusan' => 'TJKT'],
            ['nama' => 'Hani Novianti', 'kelas' => 'X', 'jurusan' => 'TJKT'],
            ['nama' => 'Inas Salma', 'kelas' => 'X', 'jurusan' => 'TJKT'],
            ['nama' => 'Luthfia Khoirumisa', 'kelas' => 'X', 'jurusan' => 'TJKT'],
            ['nama' => 'Mochamad Maulana Aditya', 'kelas' => 'X', 'jurusan' => 'TJKT'],
            ['nama' => 'Nabila Hisana Novinta', 'kelas' => 'X', 'jurusan' => 'TJKT'],
            ['nama' => 'Neisa Safa Khotimah', 'kelas' => 'X', 'jurusan' => 'TJKT'],
            ['nama' => 'Niken Yuniati', 'kelas' => 'X', 'jurusan' => 'TJKT'],
            ['nama' => 'Novita Triyani', 'kelas' => 'X', 'jurusan' => 'TJKT'],
            ['nama' => 'Nuryanti Sari', 'kelas' => 'X', 'jurusan' => 'TJKT'],
            ['nama' => 'Riska Nur Hidayah Abdullah', 'kelas' => 'X', 'jurusan' => 'TJKT'],
            ['nama' => 'Vetty Febi Nurmaeta Sari', 'kelas' => 'X', 'jurusan' => 'TJKT'],
            ['nama' => 'Wulan', 'kelas' => 'X', 'jurusan' => 'TJKT'],
            ['nama' => 'Zahratusifa', 'kelas' => 'X', 'jurusan' => 'TJKT'],

            // X PPLG
            ['nama' => 'Aretha Vega Junior', 'kelas' => 'X', 'jurusan' => 'PPLG'],
            ['nama' => 'Bulan Dewy Maharany', 'kelas' => 'X', 'jurusan' => 'PPLG'],
            ['nama' => 'Cyntia Ulfa Muliyaningsih', 'kelas' => 'X', 'jurusan' => 'PPLG'],
            ['nama' => 'Faza Setya Graha', 'kelas' => 'X', 'jurusan' => 'PPLG'],
            ['nama' => 'Handoyo Aji Pangestu', 'kelas' => 'X', 'jurusan' => 'PPLG'],
            ['nama' => 'Lidya Safitri', 'kelas' => 'X', 'jurusan' => 'PPLG'],
            ['nama' => 'Mentari Dewi Kinanthi', 'kelas' => 'X', 'jurusan' => 'PPLG'],
            ['nama' => 'Nabil Akbar Maulana', 'kelas' => 'X', 'jurusan' => 'PPLG'],
            ['nama' => 'Novia Setya Indirani', 'kelas' => 'X', 'jurusan' => 'PPLG'],
            ['nama' => 'Poppy Oktavia', 'kelas' => 'X', 'jurusan' => 'PPLG'],
            ['nama' => 'Shinta Ananda Pertiwi', 'kelas' => 'X', 'jurusan' => 'PPLG'],

            // X AKL
            ['nama' => 'Anisa Avriliani', 'kelas' => 'X', 'jurusan' => 'AKL'],
            ['nama' => 'Arrum Sakina', 'kelas' => 'X', 'jurusan' => 'AKL'],
            ['nama' => 'Dilla Novia Anggraeni', 'kelas' => 'X', 'jurusan' => 'AKL'],
            ['nama' => 'Diska Ayu Safira', 'kelas' => 'X', 'jurusan' => 'AKL'],
            ['nama' => 'Divana Nurhikmah Ramadhani', 'kelas' => 'X', 'jurusan' => 'AKL'],
            ['nama' => 'Dwi Apriliyana', 'kelas' => 'X', 'jurusan' => 'AKL'],
            ['nama' => 'Janita Supriasih', 'kelas' => 'X', 'jurusan' => 'AKL'],
            ['nama' => 'Maulidia Pangestika', 'kelas' => 'X', 'jurusan' => 'AKL'],
            ['nama' => 'Mia Wahdatul Maghfiroh', 'kelas' => 'X', 'jurusan' => 'AKL'],
            ['nama' => 'Nuri Maulia Asykha', 'kelas' => 'X', 'jurusan' => 'AKL'],
            ['nama' => 'Nurul Ngazizah', 'kelas' => 'X', 'jurusan' => 'AKL'],
            ['nama' => 'Olivia Lusiana Tambunan', 'kelas' => 'X', 'jurusan' => 'AKL'],
            ['nama' => 'Sasha Cyhinthia Bella', 'kelas' => 'X', 'jurusan' => 'AKL'],
            ['nama' => 'Vrastika Retno Ningtyas', 'kelas' => 'X', 'jurusan' => 'AKL'],

            // X PM
            ['nama' => 'Abdia Hilwana Ayana Muslim', 'kelas' => 'X', 'jurusan' => 'PM'],
            ['nama' => 'Aisyah Putri Nuraini', 'kelas' => 'X', 'jurusan' => 'PM'],
            ['nama' => 'Anindya Putri Susanto', 'kelas' => 'X', 'jurusan' => 'PM'],
            ['nama' => 'Annisa Hasna Sholehah', 'kelas' => 'X', 'jurusan' => 'PM'],
            ['nama' => 'Aura Rizka Khasanah', 'kelas' => 'X', 'jurusan' => 'PM'],
            ['nama' => 'Danastri Naela Pradipta', 'kelas' => 'X', 'jurusan' => 'PM'],
            ['nama' => 'Desta Elvaliana', 'kelas' => 'X', 'jurusan' => 'PM'],
            ['nama' => 'Dira Septi Romadhoni', 'kelas' => 'X', 'jurusan' => 'PM'],
            ['nama' => 'Firstania Fidelya Celestyn', 'kelas' => 'X', 'jurusan' => 'PM'],
            ['nama' => 'Galuh Wismaya', 'kelas' => 'X', 'jurusan' => 'PM'],
            ['nama' => 'Hanisa Qori Azahra', 'kelas' => 'X', 'jurusan' => 'PM'],
            ['nama' => 'Hanung Natasya Renata', 'kelas' => 'X', 'jurusan' => 'PM'],
            ['nama' => 'Hemi Susanti', 'kelas' => 'X', 'jurusan' => 'PM'],
            ['nama' => 'Ika Nursabrilla', 'kelas' => 'X', 'jurusan' => 'PM'],
            ['nama' => 'Jasmine Annisyarahman', 'kelas' => 'X', 'jurusan' => 'PM'],
            ['nama' => 'Jovita Karina Safitri', 'kelas' => 'X', 'jurusan' => 'PM'],
            ['nama' => 'Kurnia Romadhoni', 'kelas' => 'X', 'jurusan' => 'PM'],
            ['nama' => 'Lutfi Athirah Zahrah', 'kelas' => 'X', 'jurusan' => 'PM'],
            ['nama' => 'Mardiana Ristianti', 'kelas' => 'X', 'jurusan' => 'PM'],
            ['nama' => 'Meissin Vindasari', 'kelas' => 'X', 'jurusan' => 'PM'],
            ['nama' => 'Melani Saputri', 'kelas' => 'X', 'jurusan' => 'PM'],
            ['nama' => 'Mustika Afifatul Azizah', 'kelas' => 'X', 'jurusan' => 'PM'],
            ['nama' => 'Nabilah Kusuma Wardhany', 'kelas' => 'X', 'jurusan' => 'PM'],
            ['nama' => 'Natasya Putri Anggraini', 'kelas' => 'X', 'jurusan' => 'PM'],
            ['nama' => 'Novita Nur Febiyani', 'kelas' => 'X', 'jurusan' => 'PM'],
            ['nama' => 'Prastriani Sinta Dewi', 'kelas' => 'X', 'jurusan' => 'PM'],
            ['nama' => 'Safa Dwi Arini', 'kelas' => 'X', 'jurusan' => 'PM'],
            ['nama' => 'Shofi Ma Rifaturria Doti', 'kelas' => 'X', 'jurusan' => 'PM'],
            ['nama' => 'Silfia Naufa Mufidah', 'kelas' => 'X', 'jurusan' => 'PM'],
            ['nama' => 'Sofiana Putri Anggraeni', 'kelas' => 'X', 'jurusan' => 'PM'],
            ['nama' => 'Sugeng Rizkyadi', 'kelas' => 'X', 'jurusan' => 'PM'],
            ['nama' => 'Umi Mahsunah', 'kelas' => 'X', 'jurusan' => 'PM'],
            ['nama' => 'Utari Dwi Wulan Dari', 'kelas' => 'X', 'jurusan' => 'PM'],
            ['nama' => 'Zahra Navisa', 'kelas' => 'X', 'jurusan' => 'PM'],
            ['nama' => 'Zakiya Maulidya Izatunisa', 'kelas' => 'X', 'jurusan' => 'PM'],

            // XI RPL
            ['nama' => 'Aditya Wisnutama Surya Putra', 'kelas' => 'XI', 'jurusan' => 'RPL'],
            ['nama' => 'Alvina Shila Damayanti', 'kelas' => 'XI', 'jurusan' => 'RPL'],
            ['nama' => 'Annisa Nafisatus Solikhah', 'kelas' => 'XI', 'jurusan' => 'RPL'],
            ['nama' => 'Auxylia Azura Endria', 'kelas' => 'XI', 'jurusan' => 'RPL'],
            ['nama' => 'Chaeza Aziz Fadillah', 'kelas' => 'XI', 'jurusan' => 'RPL'],
            ['nama' => 'Chotimatis Sangadah', 'kelas' => 'XI', 'jurusan' => 'RPL'],
            ['nama' => 'Damar Maulana', 'kelas' => 'XI', 'jurusan' => 'RPL'],
            ['nama' => 'Dea Elsafitri', 'kelas' => 'XI', 'jurusan' => 'RPL'],
            ['nama' => 'Dyta Ramadhani', 'kelas' => 'XI', 'jurusan' => 'RPL'],
            ['nama' => 'Elzania Rahma Nurwida', 'kelas' => 'XI', 'jurusan' => 'RPL'],
            ['nama' => 'Erly Dwi Febrianti', 'kelas' => 'XI', 'jurusan' => 'RPL'],
            ['nama' => 'Galih Aji Nur Aziz', 'kelas' => 'XI', 'jurusan' => 'RPL'],
            ['nama' => 'Galih Razid Witanto', 'kelas' => 'XI', 'jurusan' => 'RPL'],
            ['nama' => 'Keyza Amelia', 'kelas' => 'XI', 'jurusan' => 'RPL'],
            ['nama' => 'Lilian Anatasya', 'kelas' => 'XI', 'jurusan' => 'RPL'],
            ['nama' => 'Muhammad Faris Fitrandanu', 'kelas' => 'XI', 'jurusan' => 'RPL'],
            ['nama' => 'Oktana Hari Syafera', 'kelas' => 'XI', 'jurusan' => 'RPL'],
            ['nama' => 'Rafi Nur Cayadi', 'kelas' => 'XI', 'jurusan' => 'RPL'],
            ['nama' => 'Resmita Junika Dewi', 'kelas' => 'XI', 'jurusan' => 'RPL'],
            ['nama' => 'Syifa Aulia Putri', 'kelas' => 'XI', 'jurusan' => 'RPL'],
            ['nama' => 'Tri Lestari', 'kelas' => 'XI', 'jurusan' => 'RPL'],
            ['nama' => 'Vika Rohmaningrum', 'kelas' => 'XI', 'jurusan' => 'RPL'],

            // XI TKJ
            ['nama' => 'Auliya Nuning Cahya', 'kelas' => 'XI', 'jurusan' => 'TKJ'],
            ['nama' => 'Dela Nofiyanti', 'kelas' => 'XI', 'jurusan' => 'TKJ'],
            ['nama' => 'Desta Refwanto', 'kelas' => 'XI', 'jurusan' => 'TKJ'],
            ['nama' => 'Destri Nur Amalia', 'kelas' => 'XI', 'jurusan' => 'TKJ'],
            ['nama' => 'Elank Adi Pratama', 'kelas' => 'XI', 'jurusan' => 'TKJ'],
            ['nama' => 'Erlina Dwi Cahyani', 'kelas' => 'XI', 'jurusan' => 'TKJ'],
            ['nama' => 'Fatkiroh Fitria Rizky', 'kelas' => 'XI', 'jurusan' => 'TKJ'],
            ['nama' => 'Feby Sri Wahyuni', 'kelas' => 'XI', 'jurusan' => 'TKJ'],
            ['nama' => 'Fika Ramadhani', 'kelas' => 'XI', 'jurusan' => 'TKJ'],
            ['nama' => 'Indah Putri Rahayu', 'kelas' => 'XI', 'jurusan' => 'TKJ'],
            ['nama' => 'Luthfiyah Ulfa', 'kelas' => 'XI', 'jurusan' => 'TKJ'],
            ['nama' => 'Mastinah', 'kelas' => 'XI', 'jurusan' => 'TKJ'],
            ['nama' => 'Muhamad Albar Erlangga', 'kelas' => 'XI', 'jurusan' => 'TKJ'],
            ['nama' => 'Nurrita', 'kelas' => 'XI', 'jurusan' => 'TKJ'],
            ['nama' => 'Nurrohma Ayuwita Syahrani', 'kelas' => 'XI', 'jurusan' => 'TKJ'],
            ['nama' => 'Putri Utami', 'kelas' => 'XI', 'jurusan' => 'TKJ'],
            ['nama' => 'Regita Marsya Lintang Rachmawan', 'kelas' => 'XI', 'jurusan' => 'TKJ'],
            ['nama' => 'Risva Ramadhani', 'kelas' => 'XI', 'jurusan' => 'TKJ'],
            ['nama' => 'Rohmatul Waqidah', 'kelas' => 'XI', 'jurusan' => 'TKJ'],
            ['nama' => 'Sabella Julia Rahma', 'kelas' => 'XI', 'jurusan' => 'TKJ'],
            ['nama' => 'Sahhila Azmy Pangestu', 'kelas' => 'XI', 'jurusan' => 'TKJ'],
            ['nama' => 'Sekar Dwi Munawaroh', 'kelas' => 'XI', 'jurusan' => 'TKJ'],
            ['nama' => 'Septi Ramadani', 'kelas' => 'XI', 'jurusan' => 'TKJ'],
            ['nama' => 'Septianingsih', 'kelas' => 'XI', 'jurusan' => 'TKJ'],
            ['nama' => 'Sukma Melati Ramandani', 'kelas' => 'XI', 'jurusan' => 'TKJ'],
            ['nama' => 'Upik Prastiti', 'kelas' => 'XI', 'jurusan' => 'TKJ'],
            ['nama' => 'Zahrotul Khumaeroh', 'kelas' => 'XI', 'jurusan' => 'TKJ'],
            ['nama' => 'Desti Dwi Haryanti', 'kelas' => 'XI', 'jurusan' => 'TKJ'],

            // XI MP 1
            ['nama' => 'Amanda Novita Sari', 'kelas' => 'XI', 'jurusan' => 'MP 1'],
            ['nama' => 'Anny Wulandhari', 'kelas' => 'XI', 'jurusan' => 'MP 1'],
            ['nama' => 'Auraisa Pasanaila Leilani', 'kelas' => 'XI', 'jurusan' => 'MP 1'],
            ['nama' => 'Bella Nur Indriani', 'kelas' => 'XI', 'jurusan' => 'MP 1'],
            ['nama' => 'Bunga Siti Nur Solekah', 'kelas' => 'XI', 'jurusan' => 'MP 1'],
            ['nama' => 'Cania Fadila Asmarani', 'kelas' => 'XI', 'jurusan' => 'MP 1'],
            ['nama' => 'Cheriana Devi', 'kelas' => 'XI', 'jurusan' => 'MP 1'],
            ['nama' => 'Diyah Ayu Puspita Sari', 'kelas' => 'XI', 'jurusan' => 'MP 1'],
            ['nama' => 'Fania Echa Meilani', 'kelas' => 'XI', 'jurusan' => 'MP 1'],
            ['nama' => 'Fany Maryani', 'kelas' => 'XI', 'jurusan' => 'MP 1'],
            ['nama' => 'Fitri Wulan Dari', 'kelas' => 'XI', 'jurusan' => 'MP 1'],
            ['nama' => 'Kharisa Oktaviani', 'kelas' => 'XI', 'jurusan' => 'MP 1'],
            ['nama' => 'Nadia Putri Rahayu', 'kelas' => 'XI', 'jurusan' => 'MP 1'],
            ['nama' => 'Nasifah Indah Saputri', 'kelas' => 'XI', 'jurusan' => 'MP 1'],
            ['nama' => 'Neysa Sutika Wati', 'kelas' => 'XI', 'jurusan' => 'MP 1'],
            ['nama' => 'Orlin Beryl Callista', 'kelas' => 'XI', 'jurusan' => 'MP 1'],
            ['nama' => 'Puji Rahmawati', 'kelas' => 'XI', 'jurusan' => 'MP 1'],
            ['nama' => 'Rahma Nuraini', 'kelas' => 'XI', 'jurusan' => 'MP 1'],
            ['nama' => 'Sandra Aprilia', 'kelas' => 'XI', 'jurusan' => 'MP 1'],
            ['nama' => 'Valencia Resky Ananda', 'kelas' => 'XI', 'jurusan' => 'MP 1'],
            ['nama' => 'Vina Nafisatul Muna', 'kelas' => 'XI', 'jurusan' => 'MP 1'],
            ['nama' => 'Zunaini Nur Aisyah', 'kelas' => 'XI', 'jurusan' => 'MP 1'],

            // XI MP 2
            ['nama' => 'Anindya Kirana Dewi', 'kelas' => 'XI', 'jurusan' => 'MP 2'],
            ['nama' => 'Arika Yuanita', 'kelas' => 'XI', 'jurusan' => 'MP 2'],
            ['nama' => 'Ayla Az Zahra', 'kelas' => 'XI', 'jurusan' => 'MP 2'],
            ['nama' => 'Dewi Setiyawati', 'kelas' => 'XI', 'jurusan' => 'MP 2'],
            ['nama' => 'Fairuzah Evania Putri Nugraha', 'kelas' => 'XI', 'jurusan' => 'MP 2'],
            ['nama' => 'Feby Az Zahra Putri', 'kelas' => 'XI', 'jurusan' => 'MP 2'],
            ['nama' => 'Ghaliyah Utarida Diniati', 'kelas' => 'XI', 'jurusan' => 'MP 2'],
            ['nama' => 'Karimatus Saadah', 'kelas' => 'XI', 'jurusan' => 'MP 2'],
            ['nama' => 'Kiesya Saffa Sagitta', 'kelas' => 'XI', 'jurusan' => 'MP 2'],
            ['nama' => 'Lutviyaningsih', 'kelas' => 'XI', 'jurusan' => 'MP 2'],
            ['nama' => 'Mayla Putri Iriani', 'kelas' => 'XI', 'jurusan' => 'MP 2'],
            ['nama' => 'Melda Saputri', 'kelas' => 'XI', 'jurusan' => 'MP 2'],
            ['nama' => 'Nagita Aurellia', 'kelas' => 'XI', 'jurusan' => 'MP 2'],
            ['nama' => 'Nia Adinda Aprillia', 'kelas' => 'XI', 'jurusan' => 'MP 2'],
            ['nama' => 'Ninda Erlanda Saputri', 'kelas' => 'XI', 'jurusan' => 'MP 2'],
            ['nama' => 'Risa Apriliyanti Devi', 'kelas' => 'XI', 'jurusan' => 'MP 2'],
            ['nama' => 'Ruli Setyowati', 'kelas' => 'XI', 'jurusan' => 'MP 2'],
            ['nama' => 'Triana Sari', 'kelas' => 'XI', 'jurusan' => 'MP 2'],
            ['nama' => 'Yulia Rachmawati', 'kelas' => 'XI', 'jurusan' => 'MP 2'],
            ['nama' => 'Zuvika Layla Alfiani', 'kelas' => 'XI', 'jurusan' => 'MP 2'],

            // XI MP 3
            ['nama' => 'Anisa Listiqomah', 'kelas' => 'XI', 'jurusan' => 'MP 3'],
            ['nama' => 'Asya Nur Wulan', 'kelas' => 'XI', 'jurusan' => 'MP 3'],
            ['nama' => 'Clarissa Amanda Putri Damayanti', 'kelas' => 'XI', 'jurusan' => 'MP 3'],
            ['nama' => 'Dinda Vita Hapsari', 'kelas' => 'XI', 'jurusan' => 'MP 3'],
            ['nama' => 'Herna Abellyana', 'kelas' => 'XI', 'jurusan' => 'MP 3'],
            ['nama' => 'Kurnia Aulia Putri', 'kelas' => 'XI', 'jurusan' => 'MP 3'],
            ['nama' => 'Kyona Kameswara', 'kelas' => 'XI', 'jurusan' => 'MP 3'],
            ['nama' => 'Laurent Grace Damaenka', 'kelas' => 'XI', 'jurusan' => 'MP 3'],
            ['nama' => 'Mahira Latifah', 'kelas' => 'XI', 'jurusan' => 'MP 3'],
            ['nama' => 'Meta Fernanda', 'kelas' => 'XI', 'jurusan' => 'MP 3'],
            ['nama' => 'Nendy Arum Setyo Murni', 'kelas' => 'XI', 'jurusan' => 'MP 3'],
            ['nama' => 'Niken Ayu Ningrum', 'kelas' => 'XI', 'jurusan' => 'MP 3'],
            ['nama' => 'Novita Dwi Afriani', 'kelas' => 'XI', 'jurusan' => 'MP 3'],
            ['nama' => 'Quiksa Maulana', 'kelas' => 'XI', 'jurusan' => 'MP 3'],
            ['nama' => 'Riska Destiyani', 'kelas' => 'XI', 'jurusan' => 'MP 3'],
            ['nama' => 'Soraya Hayaku', 'kelas' => 'XI', 'jurusan' => 'MP 3'],
            ['nama' => 'Tia Kariga Agustin', 'kelas' => 'XI', 'jurusan' => 'MP 3'],
            ['nama' => 'Ugonna Setyowati', 'kelas' => 'XI', 'jurusan' => 'MP 3'],
            ['nama' => 'Venita Nur Wahayuning', 'kelas' => 'XI', 'jurusan' => 'MP 3'],

            // XI AK
            ['nama' => 'Agustina Permata Sari', 'kelas' => 'XI', 'jurusan' => 'AK'],
            ['nama' => 'Arini Dewi Lestari', 'kelas' => 'XI', 'jurusan' => 'AK'],
            ['nama' => 'Asilah Najwa Salasabil', 'kelas' => 'XI', 'jurusan' => 'AK'],
            ['nama' => 'Assyifa Hidhayati', 'kelas' => 'XI', 'jurusan' => 'AK'],
            ['nama' => 'Auliyana', 'kelas' => 'XI', 'jurusan' => 'AK'],
            ['nama' => 'Cahya Dewi Pragustin', 'kelas' => 'XI', 'jurusan' => 'AK'],
            ['nama' => 'Dewi Rahmatusolehah', 'kelas' => 'XI', 'jurusan' => 'AK'],
            ['nama' => 'Diajeng Cahya Ningrum', 'kelas' => 'XI', 'jurusan' => 'AK'],
            ['nama' => 'Elysia Imelzahrani', 'kelas' => 'XI', 'jurusan' => 'AK'],
            ['nama' => 'Felisha Ardita Rahma', 'kelas' => 'XI', 'jurusan' => 'AK'],
            ['nama' => 'Isnaeni Permatasari', 'kelas' => 'XI', 'jurusan' => 'AK'],
            ['nama' => 'Maharani Rizquna Safitri', 'kelas' => 'XI', 'jurusan' => 'AK'],
            ['nama' => 'Mayla Nurfadillah', 'kelas' => 'XI', 'jurusan' => 'AK'],
            ['nama' => 'Natasya Savitri', 'kelas' => 'XI', 'jurusan' => 'AK'],
            ['nama' => 'Putria Prayogi Kuwat Muktiani', 'kelas' => 'XI', 'jurusan' => 'AK'],
            ['nama' => 'Reisya Putri Herliana', 'kelas' => 'XI', 'jurusan' => 'AK'],
            ['nama' => 'Ria Setiyowati', 'kelas' => 'XI', 'jurusan' => 'AK'],
            ['nama' => 'Sherliana Ramadhani', 'kelas' => 'XI', 'jurusan' => 'AK'],
            ['nama' => 'Siti Raudatul Nurjannah', 'kelas' => 'XI', 'jurusan' => 'AK'],
            ['nama' => 'Siti Soleha', 'kelas' => 'XI', 'jurusan' => 'AK'],
            ['nama' => 'Zalsa Febriana Aulia', 'kelas' => 'XI', 'jurusan' => 'AK'],
            ['nama' => 'Zevita Aniatul Karomah', 'kelas' => 'XI', 'jurusan' => 'AK'],

            // XI PM
            ['nama' => 'Aeni Nur Solikhah', 'kelas' => 'XI', 'jurusan' => 'PM'],
            ['nama' => 'Ayuk Rismawati', 'kelas' => 'XI', 'jurusan' => 'PM'],
            ['nama' => 'Azka Cinta Pralikha', 'kelas' => 'XI', 'jurusan' => 'PM'],
            ['nama' => 'Candra Mayasari', 'kelas' => 'XI', 'jurusan' => 'PM'],
            ['nama' => 'Ceri Arzika', 'kelas' => 'XI', 'jurusan' => 'PM'],
            ['nama' => 'Ema Marwiyah', 'kelas' => 'XI', 'jurusan' => 'PM'],
            ['nama' => 'Estiana Regina Pratiwi', 'kelas' => 'XI', 'jurusan' => 'PM'],
            ['nama' => 'Fitri Hanifah', 'kelas' => 'XI', 'jurusan' => 'PM'],
            ['nama' => 'Galih Wiji Rahayu', 'kelas' => 'XI', 'jurusan' => 'PM'],
            ['nama' => 'Gevara Ibna Febriama', 'kelas' => 'XI', 'jurusan' => 'PM'],
            ['nama' => 'Istiqomah', 'kelas' => 'XI', 'jurusan' => 'PM'],
            ['nama' => 'Lia Choirunisa', 'kelas' => 'XI', 'jurusan' => 'PM'],
            ['nama' => 'Marfelia Widia Putri', 'kelas' => 'XI', 'jurusan' => 'PM'],
            ['nama' => 'Mia Alika Putri', 'kelas' => 'XI', 'jurusan' => 'PM'],
            ['nama' => 'Miftakhul Jannah', 'kelas' => 'XI', 'jurusan' => 'PM'],
            ['nama' => 'Novita Anggraeni', 'kelas' => 'XI', 'jurusan' => 'PM'],
            ['nama' => 'Nurraya Retno Wulan Sari', 'kelas' => 'XI', 'jurusan' => 'PM'],
            ['nama' => 'Qisti Latifatul Istiqomah', 'kelas' => 'XI', 'jurusan' => 'PM'],
            ['nama' => 'Rasty Salsa Silvaninda', 'kelas' => 'XI', 'jurusan' => 'PM'],
            ['nama' => 'Rina Septarani', 'kelas' => 'XI', 'jurusan' => 'PM'],
            ['nama' => 'Rubai\'ah Lintang Atsanayya', 'kelas' => 'XI', 'jurusan' => 'PM'],
            ['nama' => 'Sherine Alifia Yulian Anggraeni', 'kelas' => 'XI', 'jurusan' => 'PM'],
            ['nama' => 'Siwi Wening Tyasti', 'kelas' => 'XI', 'jurusan' => 'PM'],
            ['nama' => 'Zahra Elsa Kayla', 'kelas' => 'XI', 'jurusan' => 'PM'],

            // XII RPL
            ['nama' => 'Ahmad Hidayat', 'kelas' => 'XII', 'jurusan' => 'RPL'],
            ['nama' => 'Anggi Putri Cahyani', 'kelas' => 'XII', 'jurusan' => 'RPL'],
            ['nama' => 'Aprillian Syah Yusuf', 'kelas' => 'XII', 'jurusan' => 'RPL'],
            ['nama' => 'Dina Marsha Isnaeni', 'kelas' => 'XII', 'jurusan' => 'RPL'],
            ['nama' => 'Lilis Suryaningtyas Trihapsari', 'kelas' => 'XII', 'jurusan' => 'RPL'],
            ['nama' => 'Maylaffaiza Gitanjali', 'kelas' => 'XII', 'jurusan' => 'RPL'],
            ['nama' => 'Muhammad Adib Muzakki', 'kelas' => 'XII', 'jurusan' => 'RPL'],
            ['nama' => 'Muthia Nadya Farhana', 'kelas' => 'XII', 'jurusan' => 'RPL'],
            ['nama' => 'Neila Anindya Putri', 'kelas' => 'XII', 'jurusan' => 'RPL'],
            ['nama' => 'Prima Mukti', 'kelas' => 'XII', 'jurusan' => 'RPL'],
            ['nama' => 'Rendra Bagus Anggoro', 'kelas' => 'XII', 'jurusan' => 'RPL'],
            ['nama' => 'Rizki Nuraeni', 'kelas' => 'XII', 'jurusan' => 'RPL'],
            ['nama' => 'Selvia Dela Puspita', 'kelas' => 'XII', 'jurusan' => 'RPL'],
            ['nama' => 'Wibowo Yunanto Sri Saputro', 'kelas' => 'XII', 'jurusan' => 'RPL'],
            ['nama' => 'Winni Lestari', 'kelas' => 'XII', 'jurusan' => 'RPL'],
            ['nama' => 'Fito Noverio Fiantono', 'kelas' => 'XII', 'jurusan' => 'RPL'],

            // XII TKJ
            ['nama' => 'Alisya Permatasari', 'kelas' => 'XII', 'jurusan' => 'TKJ'],
            ['nama' => 'Ardina Rasty', 'kelas' => 'XII', 'jurusan' => 'TKJ'],
            ['nama' => 'Atasari', 'kelas' => 'XII', 'jurusan' => 'TKJ'],
            ['nama' => 'Ayu Rida Alfa Zahra', 'kelas' => 'XII', 'jurusan' => 'TKJ'],
            ['nama' => 'Daniswara Natha Irawan', 'kelas' => 'XII', 'jurusan' => 'TKJ'],
            ['nama' => 'Dwi Andintria', 'kelas' => 'XII', 'jurusan' => 'TKJ'],
            ['nama' => 'Eka Nurhidayah', 'kelas' => 'XII', 'jurusan' => 'TKJ'],
            ['nama' => 'Fatya Andrian Syafira', 'kelas' => 'XII', 'jurusan' => 'TKJ'],
            ['nama' => 'Fitri Tuti Rahayu', 'kelas' => 'XII', 'jurusan' => 'TKJ'],
            ['nama' => 'Hikmah Nur Wahyuni', 'kelas' => 'XII', 'jurusan' => 'TKJ'],
            ['nama' => 'Intan Dianasari', 'kelas' => 'XII', 'jurusan' => 'TKJ'],
            ['nama' => 'Istiqomah', 'kelas' => 'XII', 'jurusan' => 'TKJ'],
            ['nama' => 'Kurniasari', 'kelas' => 'XII', 'jurusan' => 'TKJ'],
            ['nama' => 'Maulani Putri', 'kelas' => 'XII', 'jurusan' => 'TKJ'],
            ['nama' => 'Meyzallia Zaskia', 'kelas' => 'XII', 'jurusan' => 'TKJ'],
            ['nama' => 'Muhamad Risky Maulana', 'kelas' => 'XII', 'jurusan' => 'TKJ'],
            ['nama' => 'Muhammad Yusuf', 'kelas' => 'XII', 'jurusan' => 'TKJ'],
            ['nama' => 'Nayla Rizki Salsabila', 'kelas' => 'XII', 'jurusan' => 'TKJ'],
            ['nama' => 'Nia Putri Ariyanti', 'kelas' => 'XII', 'jurusan' => 'TKJ'],
            ['nama' => 'Nina Muflikha', 'kelas' => 'XII', 'jurusan' => 'TKJ'],
            ['nama' => 'Nuringtias Wulan Sari', 'kelas' => 'XII', 'jurusan' => 'TKJ'],
            ['nama' => 'Oktaviyani Shelli Saputri', 'kelas' => 'XII', 'jurusan' => 'TKJ'],
            ['nama' => 'Pipit Ulfa Asna', 'kelas' => 'XII', 'jurusan' => 'TKJ'],
            ['nama' => 'Ratih Y', 'kelas' => 'XII', 'jurusan' => 'TKJ'],
            ['nama' => 'Salvia Kalica Azura', 'kelas' => 'XII', 'jurusan' => 'TKJ'],
            ['nama' => 'Syafira Fatimah Azzahra', 'kelas' => 'XII', 'jurusan' => 'TKJ'],
            ['nama' => 'Wahidun Abduur Rozak', 'kelas' => 'XII', 'jurusan' => 'TKJ'],
            ['nama' => 'Wahyu Tri Evasari', 'kelas' => 'XII', 'jurusan' => 'TKJ'],
            ['nama' => 'Yatimatus Salamah', 'kelas' => 'XII', 'jurusan' => 'TKJ'],
            ['nama' => 'Yeny Kurnia Anggraini', 'kelas' => 'XII', 'jurusan' => 'TKJ'],
            ['nama' => 'Yulita Putri Anggraeni', 'kelas' => 'XII', 'jurusan' => 'TKJ'],

            // XII BD
            ['nama' => 'Ajeng Ayu Risqi Dewi Sukaesih', 'kelas' => 'XII', 'jurusan' => 'BD'],
            ['nama' => 'Dewi Untari', 'kelas' => 'XII', 'jurusan' => 'BD'],
            ['nama' => 'Dhian Rahma Cahyaningrum', 'kelas' => 'XII', 'jurusan' => 'BD'],
            ['nama' => 'Dwi Puji Astuti', 'kelas' => 'XII', 'jurusan' => 'BD'],
            ['nama' => 'Eka Agustina Setiawati', 'kelas' => 'XII', 'jurusan' => 'BD'],
            ['nama' => 'Iin Anggrainingsih', 'kelas' => 'XII', 'jurusan' => 'BD'],
            ['nama' => 'Istikawati', 'kelas' => 'XII', 'jurusan' => 'BD'],
            ['nama' => 'Miftahul Janah', 'kelas' => 'XII', 'jurusan' => 'BD'],
            ['nama' => 'Nayla Audya Eka Putri', 'kelas' => 'XII', 'jurusan' => 'BD'],
            ['nama' => 'Nisa Ardiani', 'kelas' => 'XII', 'jurusan' => 'BD'],
            ['nama' => 'Nurul Azizah', 'kelas' => 'XII', 'jurusan' => 'BD'],
            ['nama' => 'Restu Ria Pintari', 'kelas' => 'XII', 'jurusan' => 'BD'],
            ['nama' => 'Syaffa Nayla Maharani', 'kelas' => 'XII', 'jurusan' => 'BD'],
            ['nama' => 'Vernanda Rizky Yuanisa', 'kelas' => 'XII', 'jurusan' => 'BD'],
            ['nama' => 'Yasmin Aqila Permata Sany', 'kelas' => 'XII', 'jurusan' => 'BD'],
            ['nama' => 'Yusuf Agus Setyawan Rosyid', 'kelas' => 'XII', 'jurusan' => 'BD'],
            ['nama' => 'Fajriatul Khusni Khusniyati', 'kelas' => 'XII', 'jurusan' => 'BD'],

            // XII MP 1
            ['nama' => 'Anindya Cahya Ramadhani', 'kelas' => 'XII', 'jurusan' => 'MP 1'],
            ['nama' => 'Aprilita Rahmasari', 'kelas' => 'XII', 'jurusan' => 'MP 1'],
            ['nama' => 'Atha Farah Fadhillah', 'kelas' => 'XII', 'jurusan' => 'MP 1'],
            ['nama' => 'Cindy Andhika Pratiwi', 'kelas' => 'XII', 'jurusan' => 'MP 1'],
            ['nama' => 'Dela Ragil Maerani Saputri', 'kelas' => 'XII', 'jurusan' => 'MP 1'],
            ['nama' => 'Denissya Nirvania', 'kelas' => 'XII', 'jurusan' => 'MP 1'],
            ['nama' => 'Desita Nur Sabilla', 'kelas' => 'XII', 'jurusan' => 'MP 1'],
            ['nama' => 'Diah Ayu Noverti', 'kelas' => 'XII', 'jurusan' => 'MP 1'],
            ['nama' => 'Eka Septi Ningtyas', 'kelas' => 'XII', 'jurusan' => 'MP 1'],
            ['nama' => 'Gendhis Ingrid Hadiliana', 'kelas' => 'XII', 'jurusan' => 'MP 1'],
            ['nama' => 'Herlina Apriliya', 'kelas' => 'XII', 'jurusan' => 'MP 1'],
            ['nama' => 'Kurniya Mamdudah', 'kelas' => 'XII', 'jurusan' => 'MP 1'],
            ['nama' => 'Linda Rahmawati', 'kelas' => 'XII', 'jurusan' => 'MP 1'],
            ['nama' => 'Membri Asri Purwati', 'kelas' => 'XII', 'jurusan' => 'MP 1'],
            ['nama' => 'Nadia Emi Evelyna', 'kelas' => 'XII', 'jurusan' => 'MP 1'],
            ['nama' => 'Nur Laela Cahyanti', 'kelas' => 'XII', 'jurusan' => 'MP 1'],
            ['nama' => 'Pipinsari', 'kelas' => 'XII', 'jurusan' => 'MP 1'],
            ['nama' => 'Restu Lintang Nawang Sari', 'kelas' => 'XII', 'jurusan' => 'MP 1'],
            ['nama' => 'Rimas Nurwahiyanti', 'kelas' => 'XII', 'jurusan' => 'MP 1'],
            ['nama' => 'Safira Febriyana', 'kelas' => 'XII', 'jurusan' => 'MP 1'],
            ['nama' => 'Selvia Linda Dwi Fitria', 'kelas' => 'XII', 'jurusan' => 'MP 1'],
            ['nama' => 'Siti Waldaimah', 'kelas' => 'XII', 'jurusan' => 'MP 1'],
            ['nama' => 'Syifa Elviana', 'kelas' => 'XII', 'jurusan' => 'MP 1'],
            ['nama' => 'Trestania Araminta', 'kelas' => 'XII', 'jurusan' => 'MP 1'],
            ['nama' => 'Zumrotun Nafisah', 'kelas' => 'XII', 'jurusan' => 'MP 1'],

            // XII MP 2
            ['nama' => 'Aprilia Cahya Ningsih', 'kelas' => 'XII', 'jurusan' => 'MP 2'],
            ['nama' => 'Asti Zakiyatunnisa', 'kelas' => 'XII', 'jurusan' => 'MP 2'],
            ['nama' => 'Azmi Latifah', 'kelas' => 'XII', 'jurusan' => 'MP 2'],
            ['nama' => 'Cindy Ryawan', 'kelas' => 'XII', 'jurusan' => 'MP 2'],
            ['nama' => 'Della Noviana Nuraini', 'kelas' => 'XII', 'jurusan' => 'MP 2'],
            ['nama' => 'Desi Novianti', 'kelas' => 'XII', 'jurusan' => 'MP 2'],
            ['nama' => 'Devi Nur Asyfia', 'kelas' => 'XII', 'jurusan' => 'MP 2'],
            ['nama' => 'Dysta Tri Anggrayni Purnomo', 'kelas' => 'XII', 'jurusan' => 'MP 2'],
            ['nama' => 'Fitri Nur Azizah', 'kelas' => 'XII', 'jurusan' => 'MP 2'],
            ['nama' => 'Gustriyan Sari Dewi', 'kelas' => 'XII', 'jurusan' => 'MP 2'],
            ['nama' => 'Itsna Chuliatul Zahro', 'kelas' => 'XII', 'jurusan' => 'MP 2'],
            ['nama' => 'Larasati', 'kelas' => 'XII', 'jurusan' => 'MP 2'],
            ['nama' => 'Melisa Rahmawati Dwi Susanti', 'kelas' => 'XII', 'jurusan' => 'MP 2'],
            ['nama' => 'Nadhella Dian Kharisma', 'kelas' => 'XII', 'jurusan' => 'MP 2'],
            ['nama' => 'Navi Ana Sari', 'kelas' => 'XII', 'jurusan' => 'MP 2'],
            ['nama' => 'Nurmawati Parsauran Silaen', 'kelas' => 'XII', 'jurusan' => 'MP 2'],
            ['nama' => 'Purwita Wulandari', 'kelas' => 'XII', 'jurusan' => 'MP 2'],
            ['nama' => 'Revi Cinta Mardiani', 'kelas' => 'XII', 'jurusan' => 'MP 2'],
            ['nama' => 'Riski Sangga Pratiwi', 'kelas' => 'XII', 'jurusan' => 'MP 2'],
            ['nama' => 'Selvi Mutiva Dewi', 'kelas' => 'XII', 'jurusan' => 'MP 2'],
            ['nama' => 'Siti Triana Fajarwati', 'kelas' => 'XII', 'jurusan' => 'MP 2'],
            ['nama' => 'Syarifah Muslih', 'kelas' => 'XII', 'jurusan' => 'MP 2'],
            ['nama' => 'Sylvia Anindya Putri', 'kelas' => 'XII', 'jurusan' => 'MP 2'],
            ['nama' => 'Yuni Rahmawati', 'kelas' => 'XII', 'jurusan' => 'MP 2'],

            // XII AK 1
            ['nama' => 'Citra Maharani', 'kelas' => 'XII', 'jurusan' => 'AK 1'],
            ['nama' => 'Diva Amelia Tejasvini', 'kelas' => 'XII', 'jurusan' => 'AK 1'],
            ['nama' => 'Erlina Juseli', 'kelas' => 'XII', 'jurusan' => 'AK 1'],
            ['nama' => 'Fifi Wulandari', 'kelas' => 'XII', 'jurusan' => 'AK 1'],
            ['nama' => 'Laura Najwa Ranjani', 'kelas' => 'XII', 'jurusan' => 'AK 1'],
            ['nama' => 'Lintang Cahaya Maulidya', 'kelas' => 'XII', 'jurusan' => 'AK 1'],
            ['nama' => 'Nimaz Qarinta', 'kelas' => 'XII', 'jurusan' => 'AK 1'],
            ['nama' => 'Putri Dwi Permatasari', 'kelas' => 'XII', 'jurusan' => 'AK 1'],
            ['nama' => 'Putri Nurlitasari', 'kelas' => 'XII', 'jurusan' => 'AK 1'],
            ['nama' => 'Putri Setiyani', 'kelas' => 'XII', 'jurusan' => 'AK 1'],
            ['nama' => 'Risma Aulia Khasanah', 'kelas' => 'XII', 'jurusan' => 'AK 1'],
            ['nama' => 'Risqita Wahyuning Fitri', 'kelas' => 'XII', 'jurusan' => 'AK 1'],
            ['nama' => 'Siti Nur Aulia Destiyanti', 'kelas' => 'XII', 'jurusan' => 'AK 1'],
            ['nama' => 'Sukma Widiyaningsih', 'kelas' => 'XII', 'jurusan' => 'AK 1'],
            ['nama' => 'Wilda Selvi Listiyana', 'kelas' => 'XII', 'jurusan' => 'AK 1'],
            ['nama' => 'Yunita Winda Sari', 'kelas' => 'XII', 'jurusan' => 'AK 1'],

            // XII AK 2
            ['nama' => 'Arum Pangestuti', 'kelas' => 'XII', 'jurusan' => 'AK 2'],
            ['nama' => 'Aulia Khabibah', 'kelas' => 'XII', 'jurusan' => 'AK 2'],
            ['nama' => 'Devi Tri Rahayu', 'kelas' => 'XII', 'jurusan' => 'AK 2'],
            ['nama' => 'Dwi Anggita Febriyanti', 'kelas' => 'XII', 'jurusan' => 'AK 2'],
            ['nama' => 'Etra Naysila Diyanta', 'kelas' => 'XII', 'jurusan' => 'AK 2'],
            ['nama' => 'Fatimatu Zahra', 'kelas' => 'XII', 'jurusan' => 'AK 2'],
            ['nama' => 'Kamilatul Munaw Waroh', 'kelas' => 'XII', 'jurusan' => 'AK 2'],
            ['nama' => 'Linda Rachmawati', 'kelas' => 'XII', 'jurusan' => 'AK 2'],
            ['nama' => 'Liya Marisah', 'kelas' => 'XII', 'jurusan' => 'AK 2'],
            ['nama' => 'Nairul Laila Nurdiana', 'kelas' => 'XII', 'jurusan' => 'AK 2'],
            ['nama' => 'Nurmayanti', 'kelas' => 'XII', 'jurusan' => 'AK 2'],
            ['nama' => 'Rani Robaniah', 'kelas' => 'XII', 'jurusan' => 'AK 2'],
            ['nama' => 'Risma Rahmawati', 'kelas' => 'XII', 'jurusan' => 'AK 2'],
            ['nama' => 'Shifa Wulan Ramadhani', 'kelas' => 'XII', 'jurusan' => 'AK 2'],
            ['nama' => 'Siti Nurjanah', 'kelas' => 'XII', 'jurusan' => 'AK 2'],
            ['nama' => 'Talitha Nabilatul Lutfiana', 'kelas' => 'XII', 'jurusan' => 'AK 2'],
            ['nama' => 'Triya Okta Viani', 'kelas' => 'XII', 'jurusan' => 'AK 2'],
            ['nama' => 'Yuni Astika Riyani', 'kelas' => 'XII', 'jurusan' => 'AK 2'],
            ['nama' => 'Zeni Agustin', 'kelas' => 'XII', 'jurusan' => 'AK 2'],
        ];
    }
}