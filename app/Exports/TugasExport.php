<?php

namespace App\Exports;

use App\Models\Tugas;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class TugasExport implements 
    FromCollection, 
    WithHeadings, 
    WithStyles, 
    WithColumnWidths,
    WithTitle,
    ShouldAutoSize
{
    protected $tugasId;
    protected $userId;

    public function __construct($tugasId, $userId)
    {
        $this->tugasId = $tugasId;
        $this->userId = $userId;
    }

    public function collection()
    {
        $tugas = Tugas::where('id', $this->tugasId)
            ->where('id_guru', $this->userId)
            ->with(['penugasan.siswa:id,name,username,telepon,kelas,jurusan'])
            ->first();

        if (!$tugas) {
            return collect([]);
        }

        return $tugas->penugasan->map(function ($penugasan, $index) {
            return [
                'no' => $index + 1,
                'username' => $penugasan->siswa->username,
                'nama' => $penugasan->siswa->name,
                'telepon' => $penugasan->siswa->telepon,
                'kelas' => $penugasan->siswa->kelas,
                'jurusan' => $penugasan->siswa->jurusan,
                'status' => ucfirst($penugasan->status),
                'link_drive' => $penugasan->link_drive ?? '-',
                'tanggal' => $penugasan->tanggal_pengumpulan 
                    ? date('d/m/Y H:i', strtotime($penugasan->tanggal_pengumpulan)) 
                    : '-',
                'nilai' => $penugasan->nilai ?? '-',
                'catatan' => $penugasan->catatan_guru ?? '-',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'No',
            'Username',
            'Nama Siswa',
            'Telepon',
            'Kelas',
            'Jurusan',
            'Status',
            'Link Drive',
            'Tanggal Pengumpulan',
            'Nilai',
            'Catatan Guru',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();
        $lastColumn = $sheet->getHighestColumn();

        // Style header
        $sheet->getStyle('A1:' . $lastColumn . '1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);

        // Style untuk semua data
        if ($lastRow > 1) {
            $sheet->getStyle('A2:' . $lastColumn . $lastRow)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'CCCCCC'],
                    ],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ]);

            // Zebra striping untuk baris genap
            for ($i = 2; $i <= $lastRow; $i++) {
                if ($i % 2 == 0) {
                    $sheet->getStyle('A' . $i . ':' . $lastColumn . $i)->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'F2F2F2'],
                        ],
                    ]);
                }
            }

            // Center alignment untuk kolom tertentu
            // A = No
            // B = Username
            // C = Nama
            // D = Telepon
            // E = Kelas
            // F = Jurusan
            // G = Status
            // H = Link Drive
            // I = Tanggal
            // J = Nilai
            // K = Catatan

            $sheet->getStyle('A2:A' . $lastRow)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            // $sheet->getStyle('B2:B' . $lastRow)->getAlignment()
            //     ->setHorizontal(Alignment::HORIZONTAL_CENTER); // Username usually aligned left or center, let's keep default (left) or center if it was ID
            $sheet->getStyle('E2:E' . $lastRow)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER); // Kelas
            $sheet->getStyle('F2:F' . $lastRow)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER); // Jurusan
            $sheet->getStyle('G2:G' . $lastRow)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER); // Status
            $sheet->getStyle('J2:J' . $lastRow)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER); // Nilai
        }

        // Set tinggi baris header
        $sheet->getRowDimension(1)->setRowHeight(25);

        // Wrap text untuk kolom catatan (K) dan link (H)
        $sheet->getStyle('H2:H' . $lastRow)->getAlignment()->setWrapText(true);
        $sheet->getStyle('K2:K' . $lastRow)->getAlignment()->setWrapText(true);

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,   // No
            'B' => 15,  // Username (Shifted from C)
            'C' => 25,  // Nama (Shifted from D)
            'D' => 15,  // Telepon (Shifted from E)
            'E' => 10,  // Kelas (Shifted from F)
            'F' => 15,  // Jurusan (Shifted from G)
            'G' => 12,  // Status (Shifted from H)
            'H' => 35,  // Link Drive (Shifted from I)
            'I' => 20,  // Tanggal (Shifted from J)
            'J' => 8,   // Nilai (Shifted from K)
            'K' => 30,  // Catatan (Shifted from L)
        ];
    }

    public function title(): string
    {
        return 'Data Tugas Siswa';
    }
}