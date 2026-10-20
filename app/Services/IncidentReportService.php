<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\Responder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Builds the official "MDRRMC Incident Report" PDF that a responder files
 * from the field (see Api\IncidentController::resolve()). The team sends
 * the Initial report — the only one it sends since item 49 — and it goes
 * to MDRRMO's For Review tab, where it can't be changed by the team unless
 * MDRRMO sends it back. MDRRMO can correct it there (applyEdit()); the PDF
 * is rebuilt each time, and once more with who reviewed it when MDRRMO
 * approves it as resolved.
 *
 * MDRRMO can also add the report of an incident handled outside the app
 * (Add Resolved Report, Admin\IncidentController::storeManual()) — same
 * PDF, with the team typed in by the admin.
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
     *
     * $previous is the report already saved for this incident, when the
     * team sends it again (MDRRMO returned it). Its first filing time is
     * kept and the revision number goes up:
     *   filed_at      first time the report was submitted
     *   updated_at    this submission
     *   revision      1 for the first submission, 2 after one update, ...
     *   finalized_at  when it was submitted as Final (only MDRRMO's own
     *                 reports, Add Resolved Report, and Final reports sent
     *                 before item 49)
     *
     * report_type is "initial" unless "final" is asked for (Add Resolved
     * Report).
     */
    public function normalize(Incident $incident, ?Responder $responder, array $input, ?array $previous = null): array
    {
        $created = $incident->created_at ?? now();
        $now     = now();

        $type = ($input['report_type'] ?? null) === 'final' ? 'final' : 'initial';

        $clean = fn($v, $max = 255) => mb_substr(trim((string) ($v ?? '')), 0, $max);

        $casualties = $this->cleanCasualties($input['casualties'] ?? []);

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
            'filed_at'        => $previous['filed_at'] ?? $now->toIso8601String(),
            'updated_at'      => $now->toIso8601String(),
            // Reports saved before revisions were counted count as 1.
            'revision'        => $previous ? max(1, (int) ($previous['revision'] ?? 1)) + 1 : 1,
            'finalized_at'    => $type === 'final' ? $now->toIso8601String() : null,
        ];
    }

    /**
     * Whether $report (an incident's report_data) is a Final report: one
     * MDRRMO added itself (Add Resolved Report), or one a team sent before
     * item 49. Reports saved before report types existed count as Final.
     */
    public static function isFinal(?array $report): bool
    {
        return $report !== null && ($report['report_type'] ?? 'final') !== 'initial';
    }

    /**
     * The casualty lists (Dead / Injured / Missing) as the report keeps
     * them: every text trimmed and cut to length, blank rows dropped, at
     * most 30 people per list.
     *
     * @return array{dead: list<array>, injured: list<array>, missing: list<array>}
     */
    public function cleanCasualties($input): array
    {
        $clean = fn($v, $max) => mb_substr(trim((string) ($v ?? '')), 0, $max);

        $casualties = [];
        foreach (['dead', 'injured', 'missing'] as $key) {
            $casualties[$key] = [];
            foreach ((array) (is_array($input) ? ($input[$key] ?? []) : []) as $row) {
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

        return $casualties;
    }

    /** The parts of the report MDRRMO can correct (applyEdit()), as the Audit Log names them. */
    public const EDITABLE_PARTS = [
        'location'      => 'Location',
        'narrative'     => 'Narrative',
        'casualties'    => 'Casualties',
        'effects'       => 'Effects',
        'actions_taken' => 'Actions taken',
        'released_by'   => 'Released by',
    ];

    /**
     * MDRRMO's corrections to the team's report while it's on For Review
     * (item 49, Admin\IncidentController::updateReport()). Only the parts
     * in EDITABLE_PARTS are taken from $input, cleaned the same way as
     * normalize(); everything else stays as the team sent it — the type,
     * the incident's date and time, when it was sent, the revision and the
     * team. A blank location keeps the one the report had.
     *
     * Returns the report and the names of the parts that changed. When
     * something changed, the report also says who corrected it and when
     * (edited_by, edited_at) and every part corrected so far (edited_parts)
     * — shown on Incident Details and to the team in the app. Sending the
     * report again (after MDRRMO returned it) starts a fresh one without
     * them.
     *
     * @return array{0: array, 1: list<string>}
     */
    public function applyEdit(array $report, array $input, string $editor): array
    {
        $clean = fn($v, $max = 255) => mb_substr(trim((string) ($v ?? '')), 0, $max);

        $new = [
            'location'      => $clean($input['location'] ?? ''),
            'narrative'     => $clean($input['narrative'] ?? '', 2000),
            'casualties'    => $this->cleanCasualties($input['casualties'] ?? []),
            'effects'       => $clean($input['effects'] ?? '', 1000),
            'actions_taken' => $clean($input['actions_taken'] ?? '', 1500),
            'released_by'   => $clean($input['released_by'] ?? ''),
        ];
        if ($new['location'] === '') {
            $new['location'] = (string) ($report['location'] ?? '');
        }

        $changed = [];
        foreach ($new as $key => $value) {
            $old = $key === 'casualties'
                ? $this->cleanCasualties($report['casualties'] ?? [])
                : (string) ($report[$key] ?? '');
            if ($old !== $value) {
                $changed[] = self::EDITABLE_PARTS[$key];
            }
            $report[$key] = $value;
        }

        if ($changed) {
            $report['edited_by']    = $editor;
            $report['edited_at']    = now()->toIso8601String();
            $report['edited_parts'] = array_values(array_unique(array_merge(
                array_values(array_filter((array) ($report['edited_parts'] ?? []), 'is_string')),
                $changed
            )));
        }

        return [$report, $changed];
    }

    /**
     * The responding team as the admin typed it on Add Resolved Report
     * (incidents handled outside the app have no responder accounts on
     * them). Blank rows are dropped; the first person is the lead.
     */
    public function teamFromInput(array $rows): array
    {
        $clean = fn($v, $max) => mb_substr(trim((string) ($v ?? '')), 0, $max);

        $team = [];
        foreach ($rows as $row) {
            if (! is_array($row)) continue;
            $name   = $clean($row['name'] ?? '', 120);
            $agency = $clean($row['agency'] ?? '', 160);
            if ($name === '' && $agency === '') continue;

            $team[] = [
                'name'   => $name,
                'agency' => $agency,
                'role'   => $team === [] ? 'Lead' : 'Backup',
                'joined' => '',
            ];
        }

        return array_slice($team, 0, 20);
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
    /**
     * The responding team as the report shows it: read live, so reports
     * filed before the field existed (and late backups) still list everyone
     * on the incident. An incident MDRRMO added by hand has no responder
     * accounts on it, so the team typed on Add Resolved Report is kept.
     */
    public function currentTeam(Incident $incident): array
    {
        return $this->team($incident)
            ?: (is_array($incident->report_data) ? ($incident->report_data['team'] ?? []) : []);
    }

    /**
     * The report PDF as a response — built first when it's missing (e.g.
     * dompdf wasn't installed yet when the report was filed). Opens in the
     * browser, or downloads with $download. Sent this way to the admin
     * (Admin\IncidentController::reportPdf()) and to the team on the
     * incident (Api\IncidentController::reportPdfForResponder()).
     */
    public function pdfResponse(Incident $incident, bool $download = false)
    {
        $disk = Storage::disk(self::DISK);
        $path = $incident->report_pdf_path;

        if (! $path || ! $disk->exists($path)) {
            $path = $this->generate($incident);
            abort_unless($path, 503, 'PDF generator is not installed (composer require barryvdh/laravel-dompdf).');
            $incident->update(['report_pdf_path' => $path]);
        }

        // Named by type and date/time, not the incident's ID number.
        $filename = 'MDRRMC-Incident-Report_'
            . Str::slug($incident->emergency_type ?: 'incident')
            . '_' . $incident->created_at->format('Y-m-d_Hi') . '.pdf';

        return $download
            ? $disk->download($path, $filename)
            : response($disk->get($path), 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => "inline; filename=\"{$filename}\"",
            ]);
    }

    public function generate(Incident $incident): ?string
    {
        if (! self::available() || ! $incident->report_data) {
            return null;
        }

        // The team is read live, so reports filed before this field existed
        // (and late backups) still print the full responding team. An
        // incident MDRRMO added by hand has no responder accounts on it,
        // so the team typed on Add Resolved Report is kept.
        $report = $incident->report_data;
        $report['team'] = $this->currentTeam($incident);

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