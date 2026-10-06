<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\Responder;
use Illuminate\Support\Facades\Storage;

/**
 * Builds the official "MDRRMC Incident Report" PDF that a responder files
 * when resolving an incident (see Api\IncidentController::resolve()).
 *
 * Needs barryvdh/laravel-dompdf (`composer require barryvdh/laravel-dompdf`).
 * If the package isn't installed the resolution itself still succeeds — the
 * report data is saved and the PDF can be generated later, once it is.
 */
class IncidentReportService
{
    public const DISK = 'local'; // private — never exposed through /storage

    public static function available(): bool
    {
        return class_exists(\Barryvdh\DomPDF\Facade\Pdf::class);
    }

    /**
     * Cleans whatever the app sent and fills the blanks from the incident
     * itself, so the responder only has to type what the system can't know.
     */
    public function normalize(Incident $incident, ?Responder $responder, array $input): array
    {
        $created = $incident->created_at ?? now();

        $type = in_array($input['report_type'] ?? null, ['initial', 'final'], true)
            ? $input['report_type'] : 'final';

        $clean = fn($v, $max = 255) => mb_substr(trim((string) ($v ?? '')), 0, $max);

        $casualties = [];
        foreach (['dead', 'injured', 'missing'] as $key) {
            $casualties[$key] = [];
            foreach ((array) ($input['casualties'][$key] ?? []) as $row) {
                if (! is_array($row)) continue;
                $entry = [
                    'name'    => $clean($row['name'] ?? '', 120),
                    'age'     => $clean($row['age'] ?? '', 10),
                    'sex'     => $clean($row['sex'] ?? '', 10),
                    'address' => $clean($row['address'] ?? '', 160),
                    'cause'   => $clean($row['cause'] ?? '', 120),
                    'remarks' => $clean($row['remarks'] ?? '', 200),
                ];
                if (implode('', $entry) !== '') {
                    $casualties[$key][] = $entry;
                }
            }
            $casualties[$key] = array_slice($casualties[$key], 0, 30);
        }

        $releasedBy = $clean($input['released_by'] ?? '');
        if ($releasedBy === '' && $responder) {
            $releasedBy = trim(implode(', ', array_filter([
                strtoupper((string) $responder->full_name),
                $responder->badge_number ? 'Badge ' . $responder->badge_number : null,
                $responder->unit_station,
                $responder->agency,
            ])));
        }

        return [
            'report_source'   => config('services.report.source', 'MDRRMO Rosales, Pangasinan'),
            'report_datetime' => now()->format('F j, Y') . '/' . now()->format('Hi') . 'H',
            'report_type'     => $type,
            'incident_type'   => $incident->display_type ?? $incident->emergency_type,
            'location'        => $clean($input['location'] ?? '') ?: (string) $incident->location,
            'incident_date'   => $created->format('F j, Y'),
            'incident_day'    => $created->format('l'),
            'incident_time'   => $created->format('Hi') . 'H',
            'narrative'       => $clean($input['narrative'] ?? '', 2000),
            'casualties'      => $casualties,
            'effects'         => $clean($input['effects'] ?? '', 1000),
            'actions_taken'   => $clean($input['actions_taken'] ?? '', 1500),
            'released_by'     => $releasedBy,
            'team'            => $this->team($incident),
        ];
    }

    /**
     * Everyone who responded: the first to accept is the lead, anyone who
     * joined after is listed as backup (incident_responder pivot order).
     */
    private function team(Incident $incident): array
    {
        $members = $incident->responders()
            ->orderBy('incident_responder.accepted_at')
            ->orderBy('incident_responder.id')
            ->get();

        $team = [];
        foreach ($members as $i => $r) {
            $joined = $r->pivot->accepted_at ? \Carbon\Carbon::parse($r->pivot->accepted_at)->format('g:i A') : '';
            $team[] = [
                'name'   => (string) $r->full_name,
                'agency' => trim(implode(' — ', array_filter([(string) $r->agency, (string) $r->unit_station]))),
                'role'   => $i === 0 ? 'Lead' : 'Backup',
                'joined' => $joined,
            ];
        }
        return $team;
    }

    /**
     * Renders the PDF for an incident that already has report_data and
     * stores it on the private disk. Returns the stored path, or null when
     * dompdf isn't installed.
     */
    public function generate(Incident $incident): ?string
    {
        if (! self::available() || ! $incident->report_data) {
            return null;
        }

        // The team is always read live, so reports filed before this field
        // existed (and late backups) still print the full responding team.
        $report = $incident->report_data;
        $report['team'] = $this->team($incident);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('incident-report-pdf', [
            'incident' => $incident,
            'report'   => $report,
            'photoSrc' => $this->photoDataUri($incident->resolution_photo_path),
            'logoLeft' => $this->imageDataUri(public_path('images/logo_mdrrmo.png')),
            'logoRight' => $this->imageDataUri(public_path('images/logo_rosales.png')),
        ])->setPaper('a4');

        $path = "incident_reports/incident-{$incident->id}-report.pdf";
        Storage::disk(self::DISK)->put($path, $pdf->output());

        return $path;
    }

    private function imageDataUri(string $file): ?string
    {
        if (! is_file($file)) return null;
        $mime = str_ends_with(strtolower($file), '.png') ? 'image/png' : 'image/jpeg';
        return "data:{$mime};base64," . base64_encode(file_get_contents($file));
    }

    /** Resolution photo → data URI, downscaled so big phone photos don't exhaust PDF memory. */
    private function photoDataUri(?string $relativePath): ?string
    {
        if (! $relativePath || ! Storage::disk('public')->exists($relativePath)) return null;

        $file = Storage::disk('public')->path($relativePath);
        $bytes = file_get_contents($file);

        if (function_exists('imagecreatefromstring') && ($img = @imagecreatefromstring($bytes))) {
            $w = imagesx($img); $h = imagesy($img);
            $max = 1000;
            if ($w > $max || $h > $max) {
                $scale = $max / max($w, $h);
                $resized = imagescale($img, (int) ($w * $scale), (int) ($h * $scale));
                if ($resized) { imagedestroy($img); $img = $resized; }
            }
            ob_start();
            imagejpeg($img, null, 80);
            $bytes = ob_get_clean();
            imagedestroy($img);
        }

        return 'data:image/jpeg;base64,' . base64_encode($bytes);
    }
}