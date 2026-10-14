{{--
    SOS Details — the same page as Incident Details (incident-details.blade.php),
    shown the SOS way: no priority choice (every SOS is Critical), "Back to
    SOS Alerts", SOS Alerts highlighted in the menu, and the AI's reading of
    the SOS photo under Description & Attachments.

    It used to be a separate copy of the whole page; now one change shows
    on both. Opened by IncidentController::sosShow().
--}}
@include('incident-details', ['incident' => $incident, 'isSos' => true])
