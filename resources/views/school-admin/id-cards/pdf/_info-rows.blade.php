{{-- Shared label:colon:value rows for the PDF front card, used by both the
landscape and portrait layouts in _front.blade.php. Colon is its own column
(not glued to the value's text) so the gap on both sides of it is a fixed,
deliberate `$colonGap`, not whatever happens to fall out of the label's own
width - keeps every row's colon aligned and the label-to-value gap
consistent regardless of how long any individual label or value is. --}}
@php
    $colonGap = $colonGap ?? 4;
@endphp
<tr>
    <td style="font-size: {{ $fontSize }}; font-weight: bold; text-transform: uppercase; color: {{ $secondaryColor }}; padding: 1px 0;">{{ $idLabel }}</td>
    <td style="font-size: {{ $fontSize }}; font-weight: bold; color: {{ $secondaryColor }}; padding: 1px {{ $colonGap }}px;">:</td>
    <td style="font-size: {{ $fontSize }}; font-weight: 500; color: {{ $secondaryColor }}; padding: 1px 0;">{{ $identifier }}</td>
</tr>
@if ($secondaryLine)
    <tr>
        <td style="font-size: {{ $fontSize }}; font-weight: bold; text-transform: uppercase; color: {{ $secondaryColor }}; padding: 1px 0;">{{ $fieldLabel }}</td>
        <td style="font-size: {{ $fontSize }}; font-weight: bold; color: {{ $secondaryColor }}; padding: 1px {{ $colonGap }}px;">:</td>
        <td style="font-size: {{ $fontSize }}; font-weight: 500; color: {{ $secondaryColor }}; padding: 1px 0;">{{ $secondaryLine }}</td>
    </tr>
@endif
@if ($holder->date_of_birth)
    <tr>
        <td style="font-size: {{ $fontSize }}; font-weight: bold; text-transform: uppercase; color: {{ $secondaryColor }}; padding: 1px 0;">D.O.B</td>
        <td style="font-size: {{ $fontSize }}; font-weight: bold; color: {{ $secondaryColor }}; padding: 1px {{ $colonGap }}px;">:</td>
        <td style="font-size: {{ $fontSize }}; font-weight: 500; color: {{ $secondaryColor }}; padding: 1px 0;">{{ $holder->date_of_birth->format('jS F, Y') }}</td>
    </tr>
@endif
@if ($isStudent && $holder->house)
    <tr>
        <td style="font-size: {{ $fontSize }}; font-weight: bold; text-transform: uppercase; color: {{ $secondaryColor }}; padding: 1px 0;">House</td>
        <td style="font-size: {{ $fontSize }}; font-weight: bold; color: {{ $secondaryColor }}; padding: 1px {{ $colonGap }}px;">:</td>
        <td style="font-size: {{ $fontSize }}; font-weight: 500; color: {{ $secondaryColor }}; padding: 1px 0;">{{ $holder->house }}</td>
    </tr>
@endif
@if ($school->current_session)
    <tr>
        <td style="font-size: {{ $fontSize }}; font-weight: bold; text-transform: uppercase; color: {{ $secondaryColor }}; padding: 1px 0;">Session</td>
        <td style="font-size: {{ $fontSize }}; font-weight: bold; color: {{ $secondaryColor }}; padding: 1px {{ $colonGap }}px;">:</td>
        <td style="font-size: {{ $fontSize }}; font-weight: 500; color: {{ $secondaryColor }}; padding: 1px 0;">{{ $school->current_session }}</td>
    </tr>
@endif
@if ($template?->show_blood_group && $holder->blood_group)
    <tr>
        <td style="font-size: {{ $fontSize }}; font-weight: bold; text-transform: uppercase; color: {{ $secondaryColor }}; padding: 1px 0;">Blood Group</td>
        <td style="font-size: {{ $fontSize }}; font-weight: bold; color: {{ $secondaryColor }}; padding: 1px {{ $colonGap }}px;">:</td>
        <td style="font-size: {{ $fontSize }}; font-weight: 500; color: {{ $secondaryColor }}; padding: 1px 0;">{{ $holder->blood_group }}</td>
    </tr>
@endif
@if ($card->expiry_date)
    <tr>
        <td style="font-size: {{ $fontSize }}; font-weight: bold; text-transform: uppercase; color: {{ $secondaryColor }}; padding: 1px 0;">Expires</td>
        <td style="font-size: {{ $fontSize }}; font-weight: bold; color: {{ $secondaryColor }}; padding: 1px {{ $colonGap }}px;">:</td>
        <td style="font-size: {{ $fontSize }}; font-weight: 500; color: {{ $secondaryColor }}; padding: 1px 0;">{{ $card->expiry_date->format('jS F, Y') }}</td>
    </tr>
@endif
