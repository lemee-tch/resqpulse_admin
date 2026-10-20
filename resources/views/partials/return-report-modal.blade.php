{{--
    For Review → "Return" dialog, shared by the Incidents page, Incident
    Details, SOS Alerts and SOS Details:

        @include('partials.return-report-modal', ['incident' => $inc, 'modalId' => 'returnModal' . $inc->id])

    Sends the responders' report back (Admin\IncidentController::
    returnReport()) with what needs fixing. The script at the bottom is
    printed once per page and also asks before "Approve as Resolved"
    (any form with class="resolve-form").
--}}
<div class="modal fade" id="{{ $modalId }}" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:14px;border:none;">
            <form action="{{ route('incident.return-report', $incident->id) }}" method="POST" class="return-report-form">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <h5 style="font-family:'Barlow',sans-serif;font-weight:800;font-size:1.1rem;">Return the report to the responders?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">
                    <p class="rr-intro">
                        The <strong>{{ $incident->display_type }}</strong> incident goes back to <strong>Active</strong>, so the responding
                        team can fix the report and send it to you again. They get a notification with what you choose here.
                        @if(is_array($incident->report_data)) For something you can fix yourself, use <strong>Review &amp; Edit</strong> instead. @endif
                    </p>
                    <label class="rr-label" for="{{ $modalId }}Reason">What needs fixing?</label>
                    <select name="return_reason" id="{{ $modalId }}Reason" class="rr-input" required>
                        <option value="" disabled selected>Select a reason...</option>
                        @foreach(\App\Models\Incident::RETURN_REASONS as $reason)
                            <option value="{{ $reason }}">{{ $reason === 'Other' ? 'Something else (write it below)' : $reason }}</option>
                        @endforeach
                    </select>

                    <label class="rr-label" for="{{ $modalId }}Note" style="margin-top:12px;">
                        Note to the responders <span class="rr-optional">(optional)</span>
                    </label>
                    <textarea name="return_note" id="{{ $modalId }}Note" rows="3" maxlength="500" class="rr-input"
                              placeholder="e.g. Add the ages and addresses of the injured."></textarea>
                    <div class="rr-error" hidden></div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="submit" class="rr-submit"><i class="bi bi-arrow-return-left"></i> Return Report</button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius:8px;font-size:.85rem;">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

@once
<style>
    .rr-intro { font-size: .82rem; color: #6b7280; margin-bottom: 14px; line-height: 1.55; }
    .rr-label { font-size: .82rem; font-weight: 600; color: #374151; margin-bottom: 5px; display: block; }
    .rr-optional { font-weight: 500; color: #9ca3af; }
    .rr-input {
        width: 100%; border: 1.5px solid #d1d5db; border-radius: 8px;
        padding: 9px 13px; font-size: .85rem; font-family: 'Inter', sans-serif;
        color: #374151; background: #fafafa; transition: border-color .2s; resize: vertical;
    }
    .rr-input:focus { outline: none; border-color: #1a3c8f; background: #fff; }
    .rr-input.is-invalid { border-color: #dc2626; background: #fef2f2; }
    .rr-error { margin-top: 10px; font-size: .8rem; font-weight: 600; color: #b91c1c; }
    .rr-submit {
        display: inline-flex; align-items: center; gap: 6px;
        background: #b45309; color: #fff; border: none; border-radius: 8px;
        padding: 9px 20px; font-family: 'Barlow', sans-serif; font-weight: 700;
        font-size: .88rem; cursor: pointer; transition: background .2s;
    }
    .rr-submit:hover { background: #92400e; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Approve as Resolved: asks first.
    document.querySelectorAll('.resolve-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            var type = document.createElement('span');
            type.textContent = form.dataset.type || 'this';
            var notify = form.dataset.notifiesCitizen === '1'
                ? 'The resident who reported it and the responding team are notified.'
                : 'The responding team is notified.';

            if (window.Swal) {
                Swal.fire({
                    icon: 'question',
                    title: 'Approve as resolved?',
                    html: '<div style="font-size:.9em;color:#4b5563;line-height:1.5;">You checked the responders\' report for the <strong>'
                        + type.innerHTML + '</strong> incident and it is complete. The incident moves to Resolved and your name goes on the PDF as the reviewer. ' + notify + '</div>',
                    showCancelButton: true,
                    confirmButtonText: 'Approve as Resolved',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#10b981',
                }).then(function (result) { if (result.isConfirmed) form.submit(); });
            } else if (window.confirm('Approve the report and mark this incident as resolved? ' + notify)) {
                form.submit();
            }
        });
    });

    // Return: a reason is required; "Something else" needs the note.
    // The fields are read through form.elements, which works wherever the
    // dialog sits on the page.
    document.querySelectorAll('.return-report-form').forEach(function (form) {
        var reason = form.elements['return_reason'];
        var note   = form.elements['return_note'];
        var box    = form.closest('.modal') || form;
        var error  = box.querySelector('.rr-error');
        var noteLabel = box.querySelector('.rr-optional');
        if (!reason || !note || !error) return;

        function showError(text, field) {
            error.textContent = text;
            error.hidden = false;
            field.classList.add('is-invalid');
            field.focus();
        }
        function clearError() {
            error.hidden = true;
            reason.classList.remove('is-invalid');
            note.classList.remove('is-invalid');
        }

        reason.addEventListener('change', function () {
            clearError();
            if (noteLabel) noteLabel.textContent = reason.value === 'Other' ? '(required)' : '(optional)';
        });
        note.addEventListener('input', clearError);

        form.addEventListener('submit', function (e) {
            if (!reason.value) {
                e.preventDefault();
                showError('Choose what needs to be fixed.', reason);
                return;
            }
            if (reason.value === 'Other' && !note.value.trim()) {
                e.preventDefault();
                showError('Write what the responders need to fix.', note);
                return;
            }
            var button = box.querySelector('.rr-submit');
            if (button) button.disabled = true;
        });
    });
});
</script>
@endonce
