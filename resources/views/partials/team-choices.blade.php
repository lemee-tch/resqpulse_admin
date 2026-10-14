{{--
    Response team checkboxes (BFP / PNP / SARS / HCU) for Incident Details:
    Verify & Assign, and Response Assignment while the incident is Active.

        @include('partials.team-choices', ['checked' => [...], 'previous' => [...] or null])

    $checked   the codes ticked when the page opens
    $previous  the teams already notified (Response Assignment). A team
               ticked that isn't in it shows "will be notified". Null on
               Verify & Assign, where every ticked team is notified.

    The styles (.team-grid, .team-choice, .choice-input) are on the page.
--}}
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
        </label>
    @endforeach
</div>
