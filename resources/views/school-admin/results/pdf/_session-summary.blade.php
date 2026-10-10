{{-- The PDF twin of results/_session-summary.blade.php: same figures, inline
     styles for DomPDF. Keep the two in step. --}}
@php
    $termKeys = ['first' => '1st Term', 'second' => '2nd Term', 'third' => '3rd Term'];
    $pct = fn ($value) => $value !== null ? rtrim(rtrim(number_format((float) $value, 1), '0'), '.') : 'N/A';
    $sessionPosition = $sessionSummary['position'] ?? null;
    $sessionPositionLabel = $sessionPosition ? $sessionPosition.match (true) {
        in_array($sessionPosition % 100, [11, 12, 13]) => 'th',
        $sessionPosition % 10 === 1 => 'st',
        $sessionPosition % 10 === 2 => 'nd',
        $sessionPosition % 10 === 3 => 'rd',
        default => 'th',
    } : 'N/A';
    $sessionColor = $colorForPercentage($sessionSummary['average'] ?? null);
    $th = 'border: 0.5px solid #ffffff40; padding: 5px 4px; font-size: 8.5px; font-weight: bold; text-transform: uppercase; color: #ffffff; text-align: center;';
    $td = 'border: 1px solid #e5e7eb; padding: 4px; text-align: center; color: #111827;';
    $tf = 'border: 1px solid #d1d5db; padding: 5px 4px; text-align: center; font-weight: bold; color: '.$brandSecondary.';';
@endphp

<table style="border: 1.5px solid {{ $brandSecondary }}; border-radius: 6px; margin-bottom: 10px;">
    <tr>
        <td style="background-color: {{ $brandSecondary }}; color: #ffffff; font-size: 11px; font-weight: bold; text-transform: uppercase; text-align: center; padding: 5px; letter-spacing: 0.5px;">Cumulative Session Result &middot; {{ $sessionSummary['session'] }}</td>
    </tr>
    <tr>
        <td style="padding: 0;">
            <table>
                <thead>
                    <tr style="background-color: {{ $brandSecondary }};">
                        <th style="width: 26px; {{ $th }}">S/N</th>
                        <th style="{{ $th }} text-align: left;">Subject</th>
                        @foreach ($termKeys as $termLabel)
                            <th style="width: 58px; {{ $th }}">{{ $termLabel }}<div style="font-size: 7px; font-weight: normal; text-transform: none;">(%)</div></th>
                        @endforeach
                        <th style="width: 54px; {{ $th }}">Total</th>
                        <th style="width: 58px; {{ $th }}">Average<div style="font-size: 7px; font-weight: normal; text-transform: none;">(%)</div></th>
                        <th style="width: 46px; {{ $th }}">Grade</th>
                        <th style="width: 86px; {{ $th }}">Remark</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sessionSummary['subjects'] as $row)
                        <tr style="background-color: {{ $loop->even ? $rowStriped : $rowPlain }};">
                            <td style="{{ $td }}">{{ $loop->iteration }}</td>
                            <td style="{{ $td }} text-align: left; font-weight: bold; padding: 4px 6px;">{{ $row['name'] }}</td>
                            @foreach (array_keys($termKeys) as $termKey)
                                <td style="{{ $td }}">{{ $pct($row['terms'][$termKey] ?? null) }}</td>
                            @endforeach
                            <td style="{{ $td }} font-weight: bold;">{{ $pct($row['total']) }}</td>
                            <td style="{{ $td }}">{{ $pct($row['average']) }}</td>
                            <td style="{{ $td }} font-size: 11px; font-weight: bold; color: {{ $colorForPercentage($row['average']) }};">{{ $row['grade'] }}</td>
                            <td style="{{ $td }}">{{ $row['remark'] ?? 'N/A' }}</td>
                        </tr>
                    @endforeach
                    <tr style="background-color: {{ $brandSecondary }}12;">
                        <td colspan="2" style="{{ $tf }} text-align: left; text-transform: uppercase; padding: 5px 6px;">Term Average</td>
                        @foreach (array_keys($termKeys) as $termKey)
                            <td style="{{ $tf }}">{{ $pct($sessionSummary['terms'][$termKey]['average'] ?? null) }}</td>
                        @endforeach
                        <td style="{{ $tf }}"></td>
                        <td style="{{ $tf }}">{{ $pct($sessionSummary['average']) }}</td>
                        <td style="{{ $tf }} font-size: 11px; color: {{ $sessionColor }};">{{ $sessionSummary['grade'] }}</td>
                        <td style="{{ $tf }}"></td>
                    </tr>
                </tbody>
            </table>
        </td>
    </tr>
    <tr>
        <td style="padding: 0; border-top: 1.5px solid {{ $brandSecondary }};">
            <table>
                <tr>
                    @foreach ([
                        ['Session Total Marks', rtrim(rtrim(number_format((float) $sessionSummary['total'], 2), '0'), '.').' / '.$sessionSummary['max'], null],
                        ['Session Average', $pct($sessionSummary['average']).($sessionSummary['average'] !== null ? '%' : ''), null],
                        ['Session Grade', $sessionSummary['grade'].($sessionSummary['remark'] ? ' ('.strtoupper($sessionSummary['remark']).')' : ''), $sessionColor],
                        ['Session Position', $sessionPositionLabel.($sessionPosition ? ' of '.$sessionSummary['numberInClass'] : ''), null],
                    ] as [$label, $value, $color])
                        <td style="width: 25%; padding: 5px 6px; text-align: center; font-size: 9px; {{ $loop->last ? '' : 'border-right: 1px solid #e5e7eb;' }}">
                            <div style="font-size: 7.5px; font-weight: bold; text-transform: uppercase; color: {{ $brandSecondary }};">{{ $label }}</div>
                            <div style="font-weight: bold; color: {{ $color ?? '#111827' }};">{{ $value }}</div>
                        </td>
                    @endforeach
                </tr>
            </table>
        </td>
    </tr>
</table>
