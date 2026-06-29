<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\MatchPrediction;
use App\Models\RegisteredInvoice;
use App\Models\TournamentPhase;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use RuntimeException;

class EmployeeRosterService
{
    private const BOM = "\xEF\xBB\xBF";
    private const APPROVED_INVOICE_STATUSES = ['approved', 'manual_approved'];

    public function __construct(
        private readonly PromotionRankingService $rankingService,
    ) {
    }

    public function importFromCsv(UploadedFile $file): array
    {
        $path = $file->getRealPath();

        $probe = fopen($path, 'r');
        $firstLine = $probe ? (fgets($probe) ?: '') : '';
        if ($probe) {
            fclose($probe);
        }
        $firstLine = str_starts_with($firstLine, self::BOM) ? substr($firstLine, strlen(self::BOM)) : $firstLine;
        $delimiter = $this->detectDelimiter($firstLine);

        $handle = fopen($path, 'r');

        if (! $handle) {
            throw new RuntimeException('El archivo CSV esta vacio o no se pudo leer.');
        }

        // Excel suele anteponer un BOM UTF-8 invisible al primer encabezado.
        if (fread($handle, strlen(self::BOM)) !== self::BOM) {
            rewind($handle);
        }

        $header = fgetcsv($handle, 0, $delimiter);

        if ($header === false) {
            fclose($handle);
            throw new RuntimeException('El archivo CSV esta vacio o no se pudo leer.');
        }

        $header = array_map(fn ($value) => mb_strtolower(trim(str_replace(self::BOM, '', (string) $value))), $header);
        $cedulaIndex = array_search('cedula', $header, true);
        $nameIndex = array_search('name', $header, true);
        if ($nameIndex === false) {
            $nameIndex = array_search('nombre', $header, true);
        }

        if ($cedulaIndex === false || $nameIndex === false) {
            fclose($handle);
            throw new RuntimeException('El CSV debe tener columnas: cedula, name. Encabezados encontrados: '.implode(', ', $header));
        }

        $imported = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rawCedula = trim((string) ($row[$cedulaIndex] ?? ''));
            $rawName = trim((string) ($row[$nameIndex] ?? ''));
            $normalized = $this->normalizeCedula($rawCedula);

            if ($normalized === '') {
                $skipped++;
                continue;
            }

            Employee::query()->updateOrCreate(
                ['normalized_cedula' => $normalized],
                ['cedula' => $rawCedula, 'name' => $rawName !== '' ? $rawName : null]
            );

            $imported++;
        }

        fclose($handle);

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    public function report(): Collection
    {
        $phase = $this->rankingService->activeRankingPhase();

        $usersByCedula = User::query()
            ->whereNotNull('cedula')
            ->get(['id', 'cedula', 'disqualified_at', 'created_at'])
            ->mapWithKeys(fn (User $user) => [$this->normalizeCedula((string) $user->cedula) => $user]);

        $predictionPoints = $phase ? $this->predictionPointsByUser($phase) : collect();
        $invoiceStats = $phase ? $this->invoiceStatsByUser($phase) : collect();
        $originalLeaderboard = $phase ? $this->rankingService->fullRankedLeaderboard($phase->id) : collect();
        $rankingByUserId = $originalLeaderboard->keyBy('user_id');
        $winnerSlots = $phase ? $this->rankingService->winnerSlotsForPhase($phase->id) : 0;

        $employeeUserIds = $usersByCedula->values()->pluck('id')->filter()->values();
        $displacedClientByUserId = $this->displacedClientsByEmployeeUserId(
            $originalLeaderboard,
            $employeeUserIds,
            $winnerSlots,
        );

        return Employee::query()
            ->orderBy('name')
            ->get()
            ->map(function (Employee $employee) use ($usersByCedula, $predictionPoints, $invoiceStats, $rankingByUserId, $winnerSlots, $displacedClientByUserId) {
                $user = $usersByCedula->get($employee->normalized_cedula);
                $userId = $user?->id;

                $predictionPts = $userId ? (int) $predictionPoints->get($userId, 0) : 0;
                $invoiceRow = $userId ? $invoiceStats->get($userId) : null;
                $invoicePts = $invoiceRow ? (int) $invoiceRow->pts : 0;
                $invoiceCount = $invoiceRow ? (int) $invoiceRow->cnt : 0;
                $rankingRow = $userId ? $rankingByUserId->get($userId) : null;
                $isWinner = $rankingRow !== null && $winnerSlots > 0 && $rankingRow['position'] <= $winnerSlots;

                return [
                    'id' => $employee->id,
                    'user_id' => $userId,
                    'cedula' => $employee->cedula,
                    'name' => $employee->name,
                    'is_participating' => $userId !== null,
                    'registered_at' => $user?->created_at,
                    'invoice_count' => $invoiceCount,
                    'invoice_points' => $invoicePts,
                    'prediction_points' => $predictionPts,
                    'total_points' => $predictionPts + $invoicePts,
                    'position' => $rankingRow['position'] ?? null,
                    'is_winner' => $isWinner,
                    'displaced_client' => $isWinner ? ($displacedClientByUserId->get($userId)) : null,
                    'is_disqualified' => $user !== null && $user->disqualified_at !== null,
                ];
            });
    }

    /**
     * Para cada empleado que actualmente ocupa una posicion premiada, determina que
     * cliente real (no empleado) pasaria a ocupar esa posicion si los empleados se
     * excluyeran por completo del ranking.
     */
    private function displacedClientsByEmployeeUserId(Collection $originalLeaderboard, Collection $employeeUserIds, int $winnerSlots): Collection
    {
        if ($winnerSlots <= 0 || $originalLeaderboard->isEmpty() || $employeeUserIds->isEmpty()) {
            return collect();
        }

        $employeesInZone = $originalLeaderboard
            ->filter(fn (array $row) => $row['position'] <= $winnerSlots && $employeeUserIds->contains($row['user_id']))
            ->sortBy('position')
            ->values();

        if ($employeesInZone->isEmpty()) {
            return collect();
        }

        $cleanLeaderboard = $originalLeaderboard
            ->reject(fn (array $row) => $employeeUserIds->contains($row['user_id']))
            ->values()
            ->map(function (array $row, int $index) {
                $row['clean_position'] = $index + 1;

                return $row;
            });

        $newEntrants = $cleanLeaderboard
            ->filter(fn (array $row) => $row['clean_position'] <= $winnerSlots && $row['position'] > $winnerSlots)
            ->sortBy('clean_position')
            ->values();

        $mapping = collect();

        foreach ($employeesInZone as $index => $employeeRow) {
            $entrant = $newEntrants->get($index);

            if ($entrant) {
                $mapping->put($employeeRow['user_id'], [
                    'name' => $entrant['full_name'],
                    'position' => $entrant['clean_position'],
                ]);
            }
        }

        return $mapping;
    }

    public function findMatchedUser(Employee $employee): ?User
    {
        return User::query()
            ->whereNotNull('cedula')
            ->get(['id', 'name', 'cedula', 'disqualified_at', 'disqualification_reason'])
            ->first(fn (User $user) => $this->normalizeCedula((string) $user->cedula) === $employee->normalized_cedula);
    }

    public function summary(Collection $report): array
    {
        $participating = $report->where('is_participating', true)->count();

        return [
            'total' => $report->count(),
            'participating' => $participating,
            'not_participating' => $report->count() - $participating,
            'winners' => $report->where('is_winner', true)->count(),
            'disqualified' => $report->where('is_disqualified', true)->count(),
        ];
    }

    private function predictionPointsByUser(TournamentPhase $phase): Collection
    {
        return MatchPrediction::query()
            ->where('phase_id', $phase->id)
            ->selectRaw('user_id, SUM(points_awarded) as pts')
            ->groupBy('user_id')
            ->pluck('pts', 'user_id');
    }

    private function invoiceStatsByUser(TournamentPhase $phase): Collection
    {
        return RegisteredInvoice::query()
            ->whereIn('validation_status', self::APPROVED_INVOICE_STATUSES)
            ->whereBetween('issued_at', [$phase->starts_at, $phase->ends_at])
            ->selectRaw('user_id, SUM(points_awarded) as pts, COUNT(*) as cnt')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');
    }

    private function normalizeCedula(string $value): string
    {
        return preg_replace('/[^A-Z0-9]/', '', mb_strtoupper(trim($value))) ?? '';
    }

    private function detectDelimiter(string $headerLine): string
    {
        $best = ',';
        $bestCount = 0;

        foreach ([',', ';', "\t"] as $candidate) {
            $count = substr_count($headerLine, $candidate);
            if ($count > $bestCount) {
                $bestCount = $count;
                $best = $candidate;
            }
        }

        return $best;
    }
}
