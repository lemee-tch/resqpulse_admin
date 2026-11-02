{{--
    Response team checkboxes (BFP / PNP / SARS / HCU) for Incident Details:
    Pending → Send to (approving sends it to the ticked teams; the usual
    teams for the type start ticked), and Active → Notify specific
    responders, or Send Backup once a team is responding (item 55).

        @include('partials.team-choices', ['checked' => [...], 'previous' => [...] or null])

    $checked   the codes ticked when the page opens
    $previous  the teams already notified. A team ticked that isn't in it
               shows "will be notified". Null when every ticked team is
               notified (Notify specific responders, Send Backup).
    $onScene   optional: teams with someone on scene — tagged "on scene"
               (Send Backup, item 55)

    The styles (.team-grid, .team-choice, .choice-input) are on the page.
--}}
@php $onScene = $onScene ?? []; @endphp
<div class="team-grid" role="group" aria-label="Response team">
    @foreach(\App\Models\Incident::TEAMS as $code => $name)
        <input class="choice-input" type="checkbox" name="teams[]" id="team-{{ $code }}" value="{{ $code }}"
               {{ in_array($code, $checked, true) ? 'checked' : '' }}>
        <label class="team-choice" for="team-{{ $code }}"
               @if(is_array($previous)) data-previous="{{ in_array($code, $previous, true) ? '1' : '0' }}" @endif>
            <span class="tick"><i class="bi bi-check-lg"></i></span>
            <span>
                <span class="code">{{ $code }}</span>
                <span class="full">{{ $name }}</span>
            </span>
            <span class="new-tag">will be notified</span>
            @if(in_array($code, $onScene, true))
                <span class="scene-tag">on scene</span>
            @endif
        </label>
    @endforeach
</div>
