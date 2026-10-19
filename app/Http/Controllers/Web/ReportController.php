<?php

namespace App\Http\Controllers\Web;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Services\RevenueRecognitionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Owner-only financial reports (role 1). The on-screen report and both
 * export formats (CSV, PDF) are built from one buildReport() so the numbers
 * can never drift between what's shown and what's downloaded.
 */
class ReportController extends Controller
{
    public function __construct(
        private readonly RevenueRecognitionService $revenue,
    ) {
    }

    public function financial()
    {
        return view('admin.reports.financial', $this->buildReport());
    }

    /**
     * @param  string  $format  csv | pdf
     */
    public function export(string $format)
    {
        abort_unless(in_array($format, ['csv', 'pdf'], true), 404);

        $data = $this->buildReport();
        $filename = 'financial-report-'.$data['year'];

        return $format === 'csv'
            ? $this->exportCsv($data, "{$filename}.csv")
            : Pdf::loadView('admin.reports.financial-pdf', $data)->download("{$filename}.pdf");
    }

    private function exportCsv(array $data, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['Perfect Nails — Financial Report', $data['year']]);
            fputcsv($out, ['Generated', now()->toDayDateTimeString()]);
            fputcsv($out, []);

            fputcsv($out, ['Summary']);
            fputcsv($out, ['Recognized Revenue (YTD)', $data['yearRevenue']]);
            fputcsv($out, ['Commissions Accrued (YTD)', $data['yearCommissions']]);
            fputcsv($out, ['Net of Commissions', round($data['yearRevenue'] - $data['yearCommissions'], 2)]);
            fputcsv($out, []);

            fputcsv($out, ['Monthly Revenue (last 12 months)']);
            fputcsv($out, ['Month', 'Recognized Revenue']);
            foreach ($data['months'] as $month) {
                fputcsv($out, [$month['label'], $month['revenue']]);
            }
            fputcsv($out, []);

            fputcsv($out, ['Top Services by Revenue ('.$data['year'].')']);
            fputcsv($out, ['Service', 'Completed Sessions', 'Revenue']);
            foreach ($data['topServices'] as $name => $row) {
                fputcsv($out, [$name, $row['count'], $row['revenue']]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * @return array{months: array, topServices: Collection, yearRevenue: float, yearCommissions: float, year: int}
     */
    private function buildReport(): array
    {
        $months = $this->revenue->monthlyTotals(12);

        // Top services: this calendar year's verified payments, grouped by
        // the appointment's service (Rule A basis, same as the totals).

        $paymentsThisYear = Payment::with('appointment.service:id,name')
            ->where('status', PaymentStatus::Verified)
            ->whereYear('verified_at', now()->year)
            ->whereHas('appointment', fn ($query) => $query
                ->where('status', '!=', AppointmentStatus::Cancelled->value))
            ->get();

        $topServices = $paymentsThisYear
            ->groupBy(fn ($payment) => $payment->appointment->service->name ?? 'Unknown')
            ->map(fn ($group) => [
                'count' => $group->pluck('appointment_id')->unique()->count(),
                'revenue' => round((float) $group->sum('amount'), 2),
            ])
            ->sortByDesc('revenue')
            ->take(10);

        return [
            'months' => $months,
            'topServices' => $topServices,
            'yearRevenue' => $this->revenue->sumForYear((int) now()->year),
            'yearCommissions' => round((float) Commission::whereYear('earned_at', now()->year)
                ->sum('amount'), 2),
            'year' => now()->year,
        ];
    }
}
