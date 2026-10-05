<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Money;
use App\Support\Reports\Reports;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function __invoke(Request $request, string $report): StreamedResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'interval' => ['nullable', 'in:'.implode(',', Reports::INTERVALS)],
        ]);

        $reports = Reports::make($data['from'] ?? null, $data['to'] ?? null);
        [$header, $rows] = $this->build($report, $reports, $data['interval'] ?? 'day');

        $filename = sprintf('laporan-%s-%s-%s.csv', $report, $reports->range->from->toDateString(), $reports->range->to->toDateString());

        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w') ?: throw new RuntimeException('Unable to open output stream.');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header);

            foreach ($rows as $row) {
                fputcsv($out, array_map($this->safe(...), $row));
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{0: list<string>, 1: iterable<list<string|int|float>>}
     */
    private function build(string $report, Reports $reports, string $interval): array
    {
        return match ($report) {
            'sales' => [
                ['Tempoh', 'Pesanan', 'Hasil (RM)', 'Purata nilai pesanan (RM)'],
                $reports->salesByPeriod($interval)->map(fn (array $r) => [$r['label'], $r['orders'], Money::toInput($r['revenue']), Money::toInput($r['average'])]),
            ],
            'products' => [
                ['Produk', 'Kategori', 'Kuantiti', 'Hasil (RM)'],
                $reports->products()->map(fn (array $r) => [$r['name'], $r['category'], $r['units'], Money::toInput($r['revenue'])]),
            ],
            'coupons' => [
                ['Kod kupon', 'Penggunaan', 'Diskaun diberi (RM)', 'Hasil (RM)'],
                $reports->coupons()->map(fn (array $r) => [$r['code'], $r['uses'], Money::toInput($r['discount']), Money::toInput($r['revenue'])]),
            ],
            'customers' => [
                ['Emel', 'Nama', 'Pesanan', 'Perbelanjaan (RM)'],
                $reports->topCustomers(null)->map(fn (array $r) => [$r['email'], $r['name'], $r['orders'], Money::toInput($r['spend'])]),
            ],
            default => [
                ['Status', 'Pesanan', 'Nilai (RM)'],
                $reports->statuses()->map(fn (array $r) => [$r['status']->label(), $r['orders'], Money::toInput($r['value'])]),
            ],
        };
    }

    /**
     * Neutralise spreadsheet formula injection from customer-supplied text.
     */
    private function safe(string|int|float $value): string|int|float
    {
        return is_string($value) && preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
