<?php

namespace App\Http\Controllers\Web;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\Payment;
use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Services\RevenueRecognitionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Owner-only financial reports (role 1). The on-screen report and both
 * export formats (CSV, PDF) are built from one buildReport() so the numbers
 * can never drift between what's shown and what's downloaded.
 *
 * Monthly only (sha, 2026-10-29 — the Weekly tab was removed): the page
 * shows the current month. The Services by Revenue table is the plain
 * revenue ranking (all services, no threshold, no limit); the
 * threshold-gated "Top Services" lives on the dashboard, not here.
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

            fputcsv($out, ['Perfect Nails — Financial Report', $data['periodLabel']]);
            fputcsv($out, ['Generated', now()->toDayDateTimeString()]);
            fputcsv($out, []);

            fputcsv($out, ['Summary']);
            fputcsv($out, ['Revenue ('.$data['periodShort'].')', $data['periodRevenue']]);
            fputcsv($out, ['Commissions Accrued ('.$data['periodShort'].')', $data['periodCommissions']]);
            fputcsv($out, ['Net of Commissions', round($data['periodRevenue'] - $data['periodCommissions'], 2)]);
            fputcsv($out, []);

            fputcsv($out, ['Monthly Revenue (last 12 months)']);
            fputcsv($out, ['Month', 'Recognized Revenue']);
            foreach ($data['months'] as $month) {
                fputcsv($out, [$month['label'], $month['revenue']]);
            }
            fputcsv($out, []);

            fputcsv($out, ['Services by Revenue ('.$data['periodLabel'].')']);
            fputcsv($out, ['Service', 'Sessions', 'Revenue']);
            foreach ($data['services'] as $name => $row) {
                fputcsv($out, [$name, $row['count'], $row['revenue']]);
            }
            fputcsv($out, []);

            fputcsv($out, ['In-Demand Staff ('.$data['periodLabel'].')']);
            fputcsv($out, ['Staff', 'Completed Services', 'Revenue']);
            foreach ($data['staff'] as $name => $row) {
                fputcsv($out, [$name, $row['count'], $row['revenue']]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Monthly window: the current calendar month.
     *
     * @return array{0: Carbon, 1: Carbon, 2: string, 3: string}
     */
    private function periodRange(): array
    {
        $now = now();

        return [
            $now->copy()->startOfMonth(),
            $now->copy()->endOfMonth(),
            $now->format('F Y'),
            'Monthly',
        ];
    }

    /**
     * @return array{months: array, services: Collection, staff: Collection, periodRevenue: float, periodCommissions: float, period: string, periodLabel: string, periodShort: string, year: int}
     */
    private function buildReport(): array
    {
        [$start, $end, $label, $short] = $this->periodRange();

        // Money cards stay Rule A: verified money dated by verified_at
        // inside the current month, cancelled appointments excluded.
        $periodRevenue = $this->revenue->sumBetween($start, $end);
        $periodCommissions = round((float) Commission::whereBetween('earned_at', [$start, $end])
            ->sum('amount'), 2);

        // The bar chart is Monthly Revenue — last 12 months (sha,
        // 2026-10-29); the cards and tables show the current month.
        $months = $this->revenue->monthlyTotals(12);

        // Services by Revenue: this period's verified payments, grouped by
        // the appointment's service. Plain ranking — no threshold, no limit.
        $payments = Payment::with('appointment.service:id,name')
            ->where('status', PaymentStatus::Verified)
            ->whereBetween('verified_at', [$start, $end])
            ->whereHas('appointment', fn ($query) => $query
                ->where('status', '!=', AppointmentStatus::Cancelled->value))
            ->get();

        $services = $payments
            ->groupBy(fn ($payment) => $payment->appointment->service->name ?? 'Unknown')
            ->map(fn ($group) => [
                'count' => $group->pluck('appointment_id')->unique()->count(),
                'revenue' => round((float) $group->sum('amount'), 2),
            ])
            ->sortByDesc('revenue');

        // In-Demand Staff: completed appointments performed in the
        // period, grouped by the assigned staff member. Demand is the
        // completed-service count (revenue breaks ties); the period is
        // the service date (appointment_date), not the payment date.
        $performed = Appointment::with(['personnel:id,name', 'payments'])
            ->where('status', AppointmentStatus::Completed)
            ->whereBetween('appointment_date', [$start, $end])
            ->get();

        $staff = $performed
            ->groupBy(fn ($appointment) => $appointment->personnel->name ?? 'Unassigned')
            ->map(fn ($group) => [
                'count' => $group->count(),
                'revenue' => round((float) $group
                    ->flatMap(fn ($appointment) => $appointment->payments)
                    ->filter(fn ($payment) => $payment->status === PaymentStatus::Verified)
                    ->sum('amount'), 2),
            ])
            ->sort(fn ($a, $b) => [$b['count'], $b['revenue']] <=> [$a['count'], $a['revenue']]);

        return [
            'months' => $months,
            'services' => $services,
            'staff' => $staff,
            'periodRevenue' => $periodRevenue,
            'periodCommissions' => $periodCommissions,
            'period' => 'monthly',
            'periodLabel' => $label,
            'periodShort' => $short,
            'year' => now()->year,
        ];
    }
}
