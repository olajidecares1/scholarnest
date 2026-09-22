{{--
    The printable poster, rendered by dompdf at A3.

    Everything on it is an image or a word already on the page: dompdf cannot
    fetch anything remote, so the logo and the QR code arrive as data URIs from
    App\Services\Attendance\CheckInPoster.

    The address is printed underneath in plain text on purpose. A phone whose
    camera will not read a code in bad light can still be typed into, and a
    poster that offers no way through when the code fails is a poster somebody
    walks past.
--}}
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>{{ $school->name }} check-in poster</title>
        <style>
            @page { margin: 0; }
            body {
                margin: 0;
                padding: 46px 40px 40px;
                font-family: DejaVu Sans, sans-serif;
                color: #0f172a;
                text-align: center;
            }
            .logo { margin-bottom: 14px; }
            .school { font-size: 40px; font-weight: bold; margin: 0 0 6px; line-height: 1.15; }
            .motto { font-size: 15px; color: #475569; margin: 0 0 22px; font-style: italic; }
            .title { font-size: 26px; font-weight: bold; margin: 0 0 4px; letter-spacing: 1px; text-transform: uppercase; }
            .lede { font-size: 16px; color: #334155; margin: 0 0 22px; }
            .qr { width: 470px; height: 470px; }
            .frame { border: 3px solid #0f172a; border-radius: 18px; display: inline-block; padding: 16px; }
            .steps { margin: 26px auto 0; width: 560px; text-align: left; }
            .step { font-size: 16px; color: #0f172a; margin: 0 0 9px; }
            .step b { display: inline-block; width: 26px; }
            .address { margin-top: 24px; font-size: 13px; color: #475569; word-break: break-all; }
            .foot { margin-top: 10px; font-size: 11px; color: #94a3b8; }
        </style>
    </head>
    <body>
        @if ($logo)
            <div class="logo"><img src="{{ $logo['uri'] }}" width="{{ $logo['width'] }}" height="{{ $logo['height'] }}" alt=""></div>
        @endif

        <p class="school">{{ $school->name }}</p>

        @if ($school->motto)
            <p class="motto">{{ $school->motto }}</p>
        @endif

        <p class="title">Scan to check in</p>
        <p class="lede">Staff and students &mdash; scan when you arrive, and again when you leave.</p>

        <div class="frame"><img class="qr" src="{{ $qrCode }}" alt=""></div>

        <div class="steps">
            <p class="step"><b>1.</b> Point your phone's camera at the code and open the link.</p>
            <p class="step"><b>2.</b> Sign in to your portal if it asks you to. It only asks once.</p>
            <p class="step"><b>3.</b> Allow location, then wait for the tick.</p>
            <p class="step"><b>4.</b> Scan again on your way home to record the time you left.</p>
            <p class="step">No signal at the gate? Scan anyway. Your phone keeps the exact time and sends it once you are back online.</p>
        </div>

        <p class="address">{{ $url }}</p>
        <p class="foot">This code belongs to {{ $school->name }}. Scans are only counted within {{ $school->check_in_radius_metres }}m of the school.</p>
    </body>
</html>
