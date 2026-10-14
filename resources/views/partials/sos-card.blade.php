{{--
    One SOS alert on the SOS Alerts page (sos-alerts.blade.php). Every tab
    uses this same card:

        @include('partials.sos-card', ['sos' => $sos, 'tab' => 'pending'])

    $tab  pending | active | for-review | resolved | declined. It decides
          the status chip, which time is shown on the right, and the
          buttons along the bottom:
            pending     Approve & Dispatch · Decline (its dialog is on the page)
            for-review  Report (PDF) · Mark as Resolved · Return (dialog on the page)
            declined    the reason · Delete

    Left: the first photo (with the number of photos when there are more).
    Middle: the type and status, who sent it (Call), where (a pin outside
    Rosales is flagged), the team, the AI's reading of the photo. Right:
    the time that matters on this tab, and View, which opens SOS Details.
    Clicking anywhere else on the card opens it too (the script on the
    page skips the card's own buttons and links).

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

    // Photos: a JSON list (very old rows hold one plain path).
    $photos = collect();
    if ($sos->photo_path) {
        $decoded = json_decode($sos->photo_path, true);
        $photos = is_array($decoded) ? collect($decoded) : collect([$sos->photo_path]);
    }
    $photos = $photos->filter(fn ($p) => is_string($p) && $p !== '')->values();

    // The team: where MDRRMO sent it, and who accepted (first one leads).
    $teams = $sos->assignedTeamCodes();
    $onIt  = $sos->responders->sortBy(fn ($r) => (string) $r->pivot->accepted_at)->values();
    $lead  = $onIt->first();
    $leadText = $lead
        ? $lead->full_name . ($lead->agency ? " ({$lead->agency})" : '') . ($onIt->count() > 1 ? ' +' . ($onIt->count() - 1) . ' more' : '')
        : null;
    $crewText = $leadText ? match ($tab) {
        'active'     => $leadText . ' — responding',
        'for-review' => 'Final report from ' . $leadText,
        default      => 'Handled by ' . $leadText,
    } : null;

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
    // Nobody has looked at it for over 10 minutes.
    $late = $tab === 'pending' && $whenAt->lt(now()->subMinutes(10));

    $hasFoot  = in_array($tab, ['pending', 'for-review', 'declined'], true);
    $rowClass = match ($tab) { 'resolved' => 'history-row', 'declined' => 'declined-row', default => '' };
@endphp
<article class="sos-card sos-card--{{ $variant }}{{ $hasFoot ? ' has-foot' : '' }}{{ $rowClass ? ' ' . $rowClass : '' }}"
         data-id="{{ $sos->id }}" data-href="{{ $url }}"
         @if($tab === 'resolved')
             data-date="{{ $sos->created_at->format('Y-m-d') }}"
             data-ai="{{ $sos->ai_detected_type }}"
             data-search="{{ strtolower($reporter . ' ' . $mobile . ' ' . $sos->location . ' ' . $sos->ai_detected_type . ' ' . $sos->ai_analysis) }}"
         @elseif($tab === 'declined')
             data-date="{{ $sos->created_at->format('Y-m-d') }}"
             data-reason="{{ $sos->decline_reason }}"
             data-search="{{ strtolower($reporter . ' ' . $mobile . ' ' . $sos->location . ' ' . $sos->decline_reason . ' ' . $sos->ai_detected_type) }}"
         @endif>

    {{-- Photo --}}
    <a href="{{ $url }}" class="sos-thumb{{ $photos->isEmpty() ? ' is-empty' : '' }}" aria-label="Open this SOS alert">
        @if($photos->isNotEmpty())
            <img src="{{ Storage::url($photos->first()) }}" alt="Photo sent with the SOS" loading="lazy"
                 onerror="this.parentElement.classList.add('is-broken'); this.remove();">
            @if($photos->count() > 1)
                <span class="photo-count"><i class="bi bi-images"></i> {{ $photos->count() }}</span>
            @endif
        @endif
        <span class="no-photo">
            <i class="bi bi-image"></i>
            {{ $photos->isEmpty() ? 'No photo' : "Photo didn't load" }}
        </span>
    </a>

    {{-- What, who, where, the team --}}
    <div class="sos-main">
        <div class="sos-head">
            <h3 class="sos-title"><i class="bi bi-exclamation-octagon-fill"></i>{{ $sos->display_type }}</h3>
            <span class="badge-status {{ $chipClass }}">{{ $chipText }}</span>
            @if($sos->ai_detected_type)
                <span class="badge-ai" title="What the AI saw in the photo"><i class="bi bi-stars"></i> AI: {{ $sos->ai_detected_type }}</span>
            @endif
            @if($tab === 'active' && $sos->report_returned_at && $sos->report_return_reason)
                <span class="tag-returned" title="{{ $sos->report_return_reason }}"><i class="bi bi-arrow-return-left"></i> Report sent back</span>
            @elseif($tab === 'for-review' && $sos->report_returned_at)
                <span class="tag-resent" title="Sent again after you returned it"><i class="bi bi-arrow-repeat"></i> Sent again</span>
            @endif
        </div>

        <ul class="sos-facts">
            <li>
                <i class="bi bi-person-fill" title="Sent by"></i>
                <span>
                    <strong class="sos-reporter">{{ $reporter }}</strong>
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

            @if(in_array($tab, ['active', 'for-review', 'resolved'], true))
                <li>
                    <i class="bi bi-people-fill" title="Response team"></i>
                    <span>{{ $teams ? 'Sent to ' . implode(' · ', $teams) : 'Sent to the responders' }}</span>
                </li>
                <li>
                    <i class="bi bi-shield-fill-check" title="Responders"></i>
                    <span>
                        @if($crewText)
                            {{ $crewText }}
                        @elseif($tab === 'active')
                            <span class="warn-text">No responder has accepted yet</span>
                        @else
                            <span class="muted">No responder recorded</span>
                        @endif
                    </span>
                </li>
            @endif

            @if($tab === 'active' && $sos->report_returned_at && $sos->report_return_reason)
                <li class="wide">
                    <i class="bi bi-arrow-return-left" title="Report sent back"></i>
                    <span>Report sent back: {{ rtrim($sos->report_return_reason, '. ') }}. Waiting for the team to send it again.</span>
                </li>
            @endif

            @if(in_array($tab, ['for-review', 'resolved', 'declined'], true))
                <li>
                    <i class="bi bi-clock" title="When the SOS was sent"></i>
                    <span>SOS sent {{ $sos->created_at->format('M d, g:i A') }}</span>
                </li>
            @endif

            @if($tab === 'resolved' && $sos->resolvedBy)
                <li>
                    <i class="bi bi-person-check-fill" title="Reviewed by"></i>
                    <span>Reviewed by {{ $sos->resolvedBy->name }}</span>
                </li>
            @endif

            @if($sos->ai_analysis)
                <li class="wide">
                    <i class="bi bi-stars" title="What the AI saw in the photo"></i>
                    <span class="ai-quote">“{{ \Illuminate\Support\Str::limit($sos->ai_analysis, 140) }}”</span>
                </li>
            @endif
        </ul>
    </div>

    {{-- When + View --}}
    <div class="sos-side">
        <div class="sos-when">
            <div class="ago{{ $tab === 'pending' ? ($late ? ' late' : ' waiting') : '' }}">
                @if($tab === 'pending')<i class="bi bi-stopwatch"></i> @endif{{ $whenText }}
            </div>
            <div class="at">{{ $whenAt->format('M d, g:i A') }}</div>
        </div>
        <a href="{{ $url }}" class="btn-view" title="Open the SOS details: photos, map, team and timeline">
            <i class="bi bi-eye"></i> View
        </a>
    </div>

    {{-- The buttons for this tab --}}
    @if($tab === 'pending')
        @php
            $usualTeams = $sos->defaultTeams();
            $usualTeamText = count($usualTeams) > 1
                ? implode(', ', array_slice($usualTeams, 0, -1)) . ' and ' . end($usualTeams)
                : ($usualTeams[0] ?? 'SARS');
        @endphp
        <div class="sos-foot">
            <div class="foot-actions">
                <form action="{{ route('incident.approve', $sos->id) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn-approve">
                        <i class="bi bi-check-lg"></i> Approve &amp; Dispatch to Responders
                    </button>
                </form>
                <button type="button" class="btn-decline" data-bs-toggle="modal" data-bs-target="#declineModal{{ $sos->id }}">
                    <i class="bi bi-x-lg"></i> Decline
                </button>
            </div>
            <div class="dispatch-hint">
                <i class="bi bi-people"></i>
                Approve sends it to <strong>{{ $usualTeamText }}</strong>.
                <a href="{{ $url }}">Choose other teams or add notes for the team</a>
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