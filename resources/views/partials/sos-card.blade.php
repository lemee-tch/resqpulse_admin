{{--
    One SOS alert on the SOS Alerts page (sos-alerts.blade.php). Every tab
    uses this same card:

        @include('partials.sos-card', ['sos' => $sos, 'tab' => 'pending'])

    $tab  pending | active | for-review | resolved | declined. It decides
          the status chip, which time is shown on the right, and the
          buttons along the bottom:
            pending     Approve & Send to <teams> · Decline (its dialog is on the page)
            for-review  Report (PDF) · Mark as Resolved · Return (dialog on the page)
            declined    the reason · Delete

    Kept simple: the type and status, who sent it (Call), where (a pin
    outside Rosales is flagged) and, once it's dispatched, the team. The
    photo and the AI's reading of it are on SOS Details only — the card
    used to show them too. Right: the time that matters on this tab, and
    View, which opens SOS Details. Clicking anywhere else on the card opens
    it too (the script on the page skips the card's own buttons and links).

    The styles (.sos-card …) are on the page.
--}}
@php
    $url      = route('sos.detail', $sos->id);
    $reporter = $sos->reporter_name;
    $mobile   = $sos->reporter_mobile;
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
    $teams = $sos->assignedTeamCodes();
    $onIt  = $sos->responders->sortBy(fn ($r) => (string) $r->pivot->accepted_at)->values();
    $lead  = $onIt->first();
    $leadText = $lead
        ? $lead->full_name . ($lead->agency ? " ({$lead->agency})" : '') . ($onIt->count() > 1 ? ' +' . ($onIt->count() - 1) . ' more' : '')
        : null;

    // Status chip, colour of the left edge, and the time that matters here.
    [$chipClass, $chipText] = match ($tab) {
        'pending'    => ['badge-review', 'Needs Review'],
        'for-review' => ['badge-forreview', 'For Review'],
        'resolved'   => ['badge-resolved', 'Resolved'],
        'declined'   => ['badge-declined', 'Declined'],
        default      => ['badge-' . $sos->status, $sos->status_label], // Awaiting Responder / Responding
    };
    $variant = match ($tab) {
        'pending'    => 'review',
        'for-review' => 'for_review',
        'resolved'   => 'resolved',
        'declined'   => 'declined',
        default      => $sos->status, // pending = no responder yet (red), responding (blue)
    };
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
    // Under it: when the SOS was sent (on Pending and Active that's the
    // same moment, so just the date and time).
    $sentText = in_array($tab, ['pending', 'active'], true)
        ? $sos->created_at->format('M d, g:i A')
        : 'SOS sent ' . $sos->created_at->format('M d, g:i A');
    // Nobody has looked at it for over 10 minutes.
    $late = $tab === 'pending' && $whenAt->lt(now()->subMinutes(10));

    $hasFoot  = in_array($tab, ['pending', 'for-review', 'declined'], true);
    $rowClass = match ($tab) { 'resolved' => 'history-row', 'declined' => 'declined-row', default => '' };
@endphp
<article class="sos-card sos-card--{{ $variant }}{{ $hasFoot ? ' has-foot' : '' }}{{ $rowClass ? ' ' . $rowClass : '' }}"
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

    {{-- What, who, where, the team --}}
    <div class="sos-main">
        <div class="sos-head">
            <h3 class="sos-title"><i class="bi bi-exclamation-octagon-fill"></i>{{ $sos->display_type }}</h3>
            <span class="badge-status {{ $chipClass }}">{{ $chipText }}</span>
            @if($tab === 'active' && $sos->report_returned_at && $sos->report_return_reason)
                <span class="tag-returned" title="Sent back: {{ $sos->report_return_reason }}"><i class="bi bi-arrow-return-left"></i> Report sent back</span>
            @elseif($tab === 'for-review' && $sos->report_returned_at)
                <span class="tag-resent" title="Sent again after you returned it"><i class="bi bi-arrow-repeat"></i> Sent again</span>
            @endif
        </div>

        <ul class="sos-facts">
            <li>
                <i class="bi bi-person-fill" title="Sent by"></i>
                <span>
                    @if(! $sos->citizen_id && blank($sos->guest_name))
                        {{-- An older app sent no name: say so, instead of "Guest Guest". --}}
                        <span class="muted">Name not given</span>
                    @else
                        <strong class="sos-reporter">{{ $reporter }}</strong>
                    @endif
                    @if($sos->citizen_id)
                        <span class="tag-resident">Resident</span>
                    @else
                        <span class="tag-guest">Guest</span>
                    @endif
                    @if($mobile)
                        @if($tab === 'pending')
                            <a href="tel:{{ $mobile }}" class="call-pill" title="Call to confirm the emergency"><i class="bi bi-telephone-fill"></i> Call {{ $mobile }}</a>
                        @else
                            <a href="tel:{{ $mobile }}" class="call-link">{{ $mobile }}</a>
                        @endif
                    @endif
                </span>
            </li>

            <li>
                <i class="bi bi-geo-alt-fill" title="Location"></i>
                <span>
                    @if($outside)
                        <span class="tag-outside" title="The GPS pin is outside Rosales"><i class="bi bi-exclamation-triangle-fill"></i> Outside Rosales</span>
                    @endif
                    @if($mapsUrl)
                        <a href="{{ $mapsUrl }}" target="_blank" rel="noopener" class="place-link" title="Open the pin in Google Maps">{{ $place !== '' ? $place : 'Open the pin in Google Maps' }}</a>
                    @else
                        <span class="muted">{{ $place !== '' ? $place : 'No location' }}</span>
                    @endif
                </span>
            </li>

            {{-- One line for the team once it's dispatched. --}}
            @if($tab === 'active')
                <li>
                    <i class="bi bi-people-fill" title="Response team"></i>
                    <span>
                        <strong>{{ $teams ? implode(' · ', $teams) : 'Responders' }}</strong> —
                        @if($leadText)
                            {{ $leadText }} responding
                        @else
                            <span class="warn-text">no one has accepted yet</span>
                        @endif
                    </span>
                </li>
            @elseif($tab === 'for-review')
                <li>
                    <i class="bi bi-people-fill" title="Response team"></i>
                    <span>{{ $leadText ? 'Final report from ' . $leadText : 'Final report sent' }}</span>
                </li>
            @elseif($tab === 'resolved')
                <li>
                    <i class="bi bi-people-fill" title="Response team"></i>
                    <span>
                        {{ $leadText ? 'Handled by ' . $leadText : ($teams ? 'Sent to ' . implode(' · ', $teams) : 'Sent to the responders') }}
                        @if($sos->resolvedBy)
                            · reviewed by {{ $sos->resolvedBy->name }}
                        @endif
                    </span>
                </li>
            @endif
        </ul>
    </div>

    {{-- When + View --}}
    <div class="sos-side">
        <div class="sos-when">
            <div class="ago{{ $tab === 'pending' ? ($late ? ' late' : ' waiting') : '' }}"
                 title="{{ $whenLabel }} {{ $whenAt->format('M d, g:i A') }}">
                @if($tab === 'pending')<i class="bi bi-stopwatch"></i> @endif{{ $whenText }}
            </div>
            <div class="at">{{ $sentText }}</div>
        </div>
        <a href="{{ $url }}" class="btn-view" title="Open the SOS details: photo, map, team and timeline">
            <i class="bi bi-eye"></i> View
        </a>
    </div>

    {{-- The buttons for this tab --}}
    @if($tab === 'pending')
        @php
            // Quick Approve sends it to the usual team(s) for the type. To
            // pick others or add notes, the admin opens View.
            $usualTeams = $sos->defaultTeams() ?: ['SARS'];
            $usualTeamText = count($usualTeams) > 1
                ? implode(', ', array_slice($usualTeams, 0, -1)) . ' and ' . end($usualTeams)
                : $usualTeams[0];
        @endphp
        <div class="sos-foot">
            <div class="foot-actions">
                <form action="{{ route('incident.approve', $sos->id) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn-approve"
                            title="Sends it to {{ $usualTeamText }}. To choose other teams or add notes, click View.">
                        <i class="bi bi-check-lg"></i> Approve &amp; Send to {{ implode(' + ', $usualTeams) }}
                    </button>
                </form>
                <button type="button" class="btn-decline" data-bs-toggle="modal" data-bs-target="#declineModal{{ $sos->id }}">
                    <i class="bi bi-x-lg"></i> Decline
                </button>
            </div>
        </div>
    @elseif($tab === 'for-review')
        <div class="sos-foot">
            <div class="foot-actions">
                @if($sos->report_data)
                    <a href="{{ route('incident.report-pdf', $sos) }}" target="_blank" class="btn-pdf">
                        <i class="bi bi-file-earmark-pdf"></i> Report
                    </a>
                @endif
                <form action="{{ route('incident.confirm-resolved', $sos->id) }}" method="POST" class="resolve-form"
                      data-type="SOS"
                      data-notifies-citizen="{{ $sos->citizen_id ? '1' : '0' }}">
                    @csrf
                    <button type="submit" class="btn-approve">
                        <i class="bi bi-check2-circle"></i> Mark as Resolved
                    </button>
                </form>
                <button type="button" class="btn-return" data-bs-toggle="modal" data-bs-target="#returnModal{{ $sos->id }}">
                    <i class="bi bi-arrow-return-left"></i> Return
                </button>
            </div>
        </div>
    @elseif($tab === 'declined')
        <div class="sos-foot">
            <div class="declined-reason-note">
                <i class="bi bi-x-octagon"></i>
                <span>{{ $sos->reviewedBy ? 'Declined by ' . $sos->reviewedBy->name : 'Declined' }}: “{{ \Illuminate\Support\Str::limit($sos->decline_reason ?: 'No reason given', 120) }}”</span>
            </div>
            {{-- Permanently removes this declined alert (IncidentController::destroy()). --}}
            <form method="POST" action="{{ route('incident.destroy', $sos->id) }}" class="delete-form"
                  data-what="SOS alert from {{ $reporter }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-delete" title="Delete this declined SOS alert">
                    <i class="bi bi-trash3"></i> Delete
                </button>
            </form>
        </div>
    @endif
</article>
