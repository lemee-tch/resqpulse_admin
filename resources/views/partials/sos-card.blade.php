{{--
    One SOS alert on the SOS Alerts page (sos-alerts.blade.php). Every tab
    uses this same row:

        @include('partials.sos-card', ['sos' => $sos, 'tab' => 'pending'])

    $tab  pending | active | for-review | resolved | declined.

    Kept minimal, on two lines (three when there's a team or a decline
    reason to show):
      1. a coloured dot for where it stands, the type (plus the state on
         Active, and "Initial report" / "Report sent back" / "Sent again" /
         "Corrected") and the time that matters on this tab;
      2. who sent it, their number (tap to call) and where;
      3. the team (Active, For Review, Resolved) or the decline reason.

    All the buttons are together on the right — on phones, under the text:
      pending     View (approve or decline on SOS Details)
      active      View (an approved SOS with no team yet says so; the team
                  is assigned on SOS Details)
      for-review  View · Report · Edit · Return · Approve (item 49: the
                  team's Initial report — correct it, approve it as
                  resolved, or send it back)
      resolved    View
      declined    View · Delete
    Clicking anywhere else on the row opens SOS Details too (the script on
    the page skips the row's own buttons and links). The photo and the AI's
    reading of it are on SOS Details only.

    The styles (.sos-card …) are on the page.
--}}
@php
    $url      = route('sos.detail', $sos->id);
    $reporter = $sos->reporter_name;
    $mobile   = $sos->reporter_mobile;
    $isGuest  = ! $sos->citizen_id;
    $noName   = $isGuest && blank($sos->guest_name);
    $hasPin   = $sos->latitude && $sos->longitude;
    $mapsUrl  = $hasPin ? "https://www.google.com/maps?q={$sos->latitude},{$sos->longitude}" : null;

    // "SOS Alert — Outside Rosales: Amphitheatre Parkway, …" → the place,
    // with "Outside Rosales" shown as a warning instead.
    $place   = trim((string) preg_replace('/^(SOS Alert|Guest Report)\s*[—,]\s*/u', '', (string) $sos->location));
    $outside = (bool) preg_match('/^Outside Rosales\b/u', $place);
    if ($outside) {
        $place = trim((string) preg_replace('/^Outside Rosales:?\s*/u', '', $place));
    }
    if ($place === '' && $hasPin) {
        $place = number_format((float) $sos->latitude, 5) . ', ' . number_format((float) $sos->longitude, 5);
    }

    // The team: where MDRRMO sent it, and who accepted (first one leads).
    $teams     = $sos->assignedTeamCodes();
    $teamsText = $sos->sentToAllTeams() ? 'All teams' : implode(' · ', $teams);
    $onIt  = $sos->responders->sortBy(fn ($r) => (string) $r->pivot->accepted_at)->values();
    $lead  = $onIt->first();
    $leadText = $lead
        ? $lead->full_name . ($lead->agency ? " ({$lead->agency})" : '') . ($onIt->count() > 1 ? ' +' . ($onIt->count() - 1) . ' more' : '')
        : null;

    // Approved, but not sent to the responders yet (item 36).
    $needsTeam = $tab === 'active' && $sos->awaitingTeam();

    // Active: an Initial report the team saved before item 49 (it reaches
    // For Review when they send it from the app).
    $initialReport = $tab === 'active' ? $sos->teamReport() : null;
    // For Review: "Initial report" — or "Final report" for one sent before item 49.
    $reportKind = is_array($sos->report_data) && ($sos->report_data['report_type'] ?? 'final') !== 'initial'
        ? 'Final report' : 'Initial report';

    // Where it stands: the dot's colour.
    $variant = match (true) {
        $tab === 'pending'    => 'review',
        $tab === 'for-review' => 'for_review',
        $tab === 'resolved'   => 'resolved',
        $tab === 'declined'   => 'declined',
        $needsTeam            => 'needs_team',
        default               => $sos->status, // pending = nobody accepted yet, responding
    };

    // The time that matters on this tab.
    [$whenLabel, $whenAt] = match ($tab) {
        'pending'    => ['Waiting', $sos->created_at],
        'for-review' => ['Report sent', $sos->report_submitted_at ?? $sos->updated_at],
        'resolved'   => ['Resolved', $sos->resolved_at ?? $sos->updated_at],
        'declined'   => ['Declined', $sos->declined_at ?? $sos->updated_at],
        default      => ['Sent', $sos->created_at],
    };
    $whenText = $tab === 'pending'
        ? 'Waiting ' . $whenAt->diffForHumans(['syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE])
        : $whenLabel . ' ' . $whenAt->diffForHumans();
    $whenTitle = $whenLabel . ' ' . $whenAt->format('M d, g:i A') . ($tab === 'pending' ? '' : ' · SOS sent ' . $sos->created_at->format('M d, g:i A'));
    // Nobody has looked at it for over 10 minutes.
    $late = $tab === 'pending' && $whenAt->lt(now()->subMinutes(10));

    // Third line: the team, or why it was declined.
    [$detail, $detailWarn] = match (true) {
        $needsTeam => ['Not sent yet — open View to send it to the responders', true],
        $tab === 'active' => [
            ($teams ? $teamsText : 'Responders') . ' — '
                . ($leadText ? $leadText . ' responding' : 'no one has accepted yet'),
            ! $leadText,
        ],
        $tab === 'for-review' => [$leadText ? $reportKind . ' from ' . $leadText : $reportKind . ' sent', false],
        $tab === 'resolved'   => [
            ($leadText ? 'Handled by ' . $leadText : ($teams ? 'Sent to ' . ($sos->sentToAllTeams() ? 'all teams' : $teamsText) : 'Sent to the responders'))
                . ($sos->resolvedBy ? ' · approved by ' . $sos->resolvedBy->name : ''),
            false,
        ],
        $tab === 'declined'   => [
            ($sos->reviewedBy ? 'Declined by ' . $sos->reviewedBy->name : 'Declined') . ': '
                . \Illuminate\Support\Str::limit($sos->decline_reason ?: 'No reason given', 120),
            false,
        ],
        default      => [null, false],
    };

    $rowClass = match ($tab) { 'resolved' => 'history-row', 'declined' => 'declined-row', default => '' };
@endphp
<article class="sos-card sos-card--{{ $variant }}{{ $rowClass ? ' ' . $rowClass : '' }}"
         data-id="{{ $sos->id }}" data-href="{{ $url }}"
         @if($tab === 'resolved')
             data-date="{{ $sos->created_at->format('Y-m-d') }}"
             data-type="{{ $sos->sos_emergency_type }}"
             data-search="{{ strtolower($reporter . ' ' . $mobile . ' ' . $sos->location . ' ' . $sos->display_type) }}"
         @elseif($tab === 'declined')
             data-date="{{ $sos->created_at->format('Y-m-d') }}"
             data-reason="{{ $sos->decline_reason }}"
             data-search="{{ strtolower($reporter . ' ' . $mobile . ' ' . $sos->location . ' ' . $sos->decline_reason . ' ' . $sos->display_type) }}"
         @endif>

    <div class="sos-info">
        <div class="sos-line1">
            <span class="sos-dot" aria-hidden="true"></span>
            <h3 class="sos-title">{{ $sos->display_type }}</h3>
            @if($tab === 'active')
                <span class="sos-state">{{ $sos->status_label }}</span>
            @endif
            @if($tab === 'active' && $sos->report_returned_at && $sos->report_return_reason)
                <span class="sos-flag" title="Sent back: {{ $sos->report_return_reason }}">Report sent back</span>
            @endif
            @if($initialReport)
                <span class="sos-flag sos-flag--initial" title="{{ $initialReport['revision'] > 1 ? 'Initial report updated' : 'Initial report saved' }} {{ $initialReport['at']->format('M d, g:i A') }} — not sent to MDRRMO yet. Open View to read it.">
                    <i class="bi bi-file-earmark-text"></i>Initial report · {{ $initialReport['at']->gt(now()->subMinute()) ? 'just now' : $initialReport['at']->diffForHumans() }}
                </span>
            @elseif($tab === 'for-review' && $sos->report_returned_at)
                <span class="sos-flag" title="Sent again after you returned it">Sent again</span>
            @endif
            @if($tab === 'for-review' && ! empty($sos->report_data['edited_by']))
                <span class="sos-flag sos-flag--corrected" title="Corrected by {{ $sos->report_data['edited_by'] }}"><i class="bi bi-pencil"></i>Corrected</span>
            @endif
            <span class="sos-time{{ $late ? ' late' : ($tab === 'pending' ? ' waiting' : '') }}" title="{{ $whenTitle }}">{{ $whenText }}</span>
        </div>

        <div class="sos-line2">
            <span class="sos-who">{{ $noName ? 'Name not given' : $reporter }}</span>@if($isGuest)<span class="sos-guest">Guest</span>@endif
            @if($mobile)
                <span class="sos-sep">·</span>
                <a href="tel:{{ $mobile }}" class="sos-tel" title="Call to confirm the emergency"><i class="bi bi-telephone"></i>{{ $mobile }}</a>
            @endif
            <span class="sos-sep">·</span>
            @if($outside)<span class="sos-outside" title="The GPS pin is outside Rosales">Outside Rosales:</span>@endif
            @if($mapsUrl)
                <a href="{{ $mapsUrl }}" target="_blank" rel="noopener" class="sos-place" title="Open the pin in Google Maps">{{ $place !== '' ? $place : 'Open the pin in Google Maps' }}</a>
            @else
                <span class="sos-place">{{ $place !== '' ? $place : 'No location' }}</span>
            @endif
        </div>

        @if($detail)
            <div class="sos-line3{{ $detailWarn ? ' warn' : '' }}">{{ $detail }}</div>
        @endif
    </div>

    {{-- Every button for this alert, together. --}}
    <div class="sos-actions">
        <a href="{{ $url }}" class="act" title="Open the SOS details: photo, map, team and timeline">
            <i class="bi bi-eye"></i> View
        </a>

        @if($tab === 'for-review')
            @if($sos->report_data)
                <a href="{{ route('incident.report-pdf', $sos) }}" target="_blank" class="act" title="Open the report (PDF)">
                    <i class="bi bi-file-earmark-pdf"></i> Report
                </a>
                <a href="{{ route('incident.report.edit', $sos) }}" class="act act--edit" title="Read the report and correct it before approving">
                    <i class="bi bi-pencil-square"></i> Edit
                </a>
            @endif
            <button type="button" class="act act--warn" data-bs-toggle="modal" data-bs-target="#returnModal{{ $sos->id }}">
                <i class="bi bi-arrow-return-left"></i> Return
            </button>
            <form action="{{ route('incident.confirm-resolved', $sos->id) }}" method="POST" class="resolve-form"
                  data-type="SOS"
                  data-notifies-citizen="{{ $sos->citizen_id ? '1' : '0' }}">
                @csrf
                <button type="submit" class="act act--primary" title="Approve the report and mark the SOS resolved">
                    <i class="bi bi-check2-circle"></i> Approve
                </button>
            </form>
        @elseif($tab === 'declined')
            {{-- Permanently removes this declined alert (IncidentController::destroy()). --}}
            <form method="POST" action="{{ route('incident.destroy', $sos->id) }}" class="delete-form"
                  data-what="SOS alert from {{ $reporter }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="act act--danger" title="Delete this declined SOS alert">
                    <i class="bi bi-trash3"></i> Delete
                </button>
            </form>
        @endif
    </div>
</article>
