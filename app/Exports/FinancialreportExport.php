<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Font;

class FinancialReportExport implements
    FromArray,
    WithHeadings,
    WithTitle,
    WithStyles,
    WithColumnWidths,
    WithEvents
{
    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    // ── Sheet title (nama tab) ────────────────────────────────────────────────
    public function title(): string
    {
        return "Laporan {$this->data['monthName']} {$this->data['year']}";
    }

    // ── Data baris ───────────────────────────────────────────────────────────
    public function array(): array
    {
        $rows = [];

        // Baris judul utama (di-merge via events)
        $rows[] = ["LAPORAN KEUANGAN WASHUP", '', '', '', ''];
        $rows[] = ["{$this->data['monthName']} {$this->data['year']}", '', '', '', ''];
        $rows[] = ['', '', '', '', '']; // spacer

        // Ringkasan statistik
        $rows[] = ['Total Pendapatan', $this->fmtRupiah($this->data['totalPendapatan']), '', '', ''];
        $rows[] = ['Jumlah Pesanan',   $this->data['jumlahPesanan'] . ' Order',          '', '', ''];
        $rows[] = ['Rata-Rata',        $this->fmtRupiah($this->data['rataRata']),         '', '', ''];
        $rows[] = ['', '', '', '', '']; // spacer

        // Data transaksi (header di-handle withHeadings — tapi kita inject manual)
        $rows[] = ['No', 'Tanggal', 'No Nota', 'Pelanggan', 'Nominal'];

        foreach ($this->data['transactions'] as $i => $t) {
            $rows[] = [
                $i + 1,
                $t['tanggal'],
                $t['nota'],
                $t['pelanggan'],
                $t['nominal'], // angka murni agar Excel bisa format currency
            ];
        }

        // Baris total
        $rows[] = ['', '', '', 'TOTAL', $this->data['totalPendapatan']];

        return $rows;
    }

    // ── Headings (tidak dipakai karena inject manual di array()) ─────────────
    public function headings(): array
    {
        return [];
    }

    // ── Lebar kolom ──────────────────────────────────────────────────────────
    public function columnWidths(): array
    {
        return [
            'A' => 6,
            'B' => 22,
            'C' => 14,
            'D' => 28,
            'E' => 20,
        ];
    }

    // ── Style per worksheet ──────────────────────────────────────────────────
    public function styles(Worksheet $sheet): array
    {
        // Judul utama
        $sheet->mergeCells('A1:E1');
        $sheet->mergeCells('A2:E2');

        // Merge ringkasan
        foreach ([4, 5, 6] as $r) {
            $sheet->mergeCells("B{$r}:E{$r}");
        }

        $lastRow = 8 + count($this->data['transactions']) + 1;

        return [
            // Judul
            1 => [
                'font'      => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0077B6']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            2 => [
                'font'      => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0096C7']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            // Ringkasan
            4 => ['font' => ['bold' => true, 'size' => 11]],
            5 => ['font' => ['bold' => true, 'size' => 11]],
            6 => ['font' => ['bold' => true, 'size' => 11]],
            // Header tabel transaksi (baris ke-8)
            8 => [
                'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0077B6']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            // Baris total
            $lastRow => [
                'font' => ['bold' => true, 'color' => ['rgb' => '0077B6']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EAF6FB']],
            ],
        ];
    }

    // ── Events: zebra stripe + border + format currency ───────────────────────
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet     = $event->sheet->getDelegate();
                $startRow  = 9; // baris pertama data transaksi
                $total     = count($this->data['transactions']);
                $lastRow   = $startRow + $total;

                // Zebra stripe pada baris data
                for ($r = $startRow; $r < $lastRow; $r++) {
                    if ($r % 2 === 0) {
                        $sheet->getStyle("A{$r}:E{$r}")->applyFromArray([
                            'fill' => [
                                'fillType'   => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => 'EAF6FB'],
                            ],
                        ]);
                    }
                }

                // Border seluruh tabel transaksi
                $sheet->getStyle("A8:E{$lastRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color'       => ['rgb' => '000000'],
                        ],
                    ],
                ]);

                // Format kolom Nominal sebagai Rupiah
                $nominalRange = "E{$startRow}:E{$lastRow}";
                $sheet->getStyle($nominalRange)->getNumberFormat()
                    ->setFormatCode('"Rp"#,##0');

                // Center alignment kolom No, Tanggal, Nota
                $sheet->getStyle("A8:C{$lastRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Right alignment Nominal
                $sheet->getStyle("E8:E{$lastRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                // Freeze header row
                $sheet->freezePane('A9');
            },
        ];
    }

    // ── Helper format Rupiah ──────────────────────────────────────────────────
    private function fmtRupiah(int $n): string
    {
        return 'Rp' . number_format($n, 0, ',', '.') . ',00';
    }
}