<?php

namespace App\Http\Controllers\Web;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Commission;
use App\Models\Payment;
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
            fputcsv($out, ['Verified Revenue (YTD)', $data['yearRevenue']]);
            fputcsv($out, ['Commissions Accrued (YTD)', $data['yearCommissions']]);
            fputcsv($out, ['Net of Commissions', round($data['yearRevenue'] - $data['yearCommissions'], 2)]);
            fputcsv($out, []);

            fputcsv($out, ['Monthly Revenue (last 12 months)']);
            fputcsv($out, ['Month', 'Verified Revenue']);
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
        // Twelve months of verified revenue, grouped in PHP for portability.
        $start = now()->startOfMonth()->subMonths(11);
        $payments = Payment::where('status', PaymentStatus::Verified)
            ->where('verified_at', '>=', $start)
            ->get(['amount', 'verified_at']);

        $months = [];
        for ($i = 0; $i < 12; $i++) {
            $monthStart = $start->copy()->addMonths($i);
            $monthEnd = $monthStart->copy()->endOfMonth();
            $months[] = [
                'label' => $monthStart->format('M Y'),
                'revenue' => round((float) $payments
                    ->filter(fn ($payment) => $payment->verified_at !== null
                        && $payment->verified_at->between($monthStart, $monthEnd))
                    ->sum('amount'), 2),
            ];
        }

        $completedThisYear = Appointment::with('service:id,name,price')
            ->where('status', AppointmentStatus::Completed)
            ->whereYear('appointment_date', now()->year)
            ->get();

        $topServices = $completedThisYear
            ->groupBy(fn ($appointment) => $appointment->service->name ?? 'Unknown')
            ->map(fn ($group) => [
                'count' => $group->count(),
                'revenue' => round((float) $group->sum(fn ($a) => (float) ($a->service->price ?? 0)), 2),
            ])
            ->sortByDesc('revenue')
            ->take(10);

        return [
            'months' => $months,
            'topServices' => $topServices,
            'yearRevenue' => round((float) Payment::where('status', PaymentStatus::Verified)
                ->whereYear('verified_at', now()->year)
                ->sum('amount'), 2),
            'yearCommissions' => round((float) Commission::whereYear('earned_at', now()->year)
                ->sum('amount'), 2),
            'year' => now()->year,
        ];
    }
}
