{{-- Cumulative session result, on a Third Term card of a school that has
     turned the setting on. $sessionSummary comes from
     App\Services\SessionResultCalculator (or, on a published card, from what
     was stored when it was published). Each term column is that term's
     percentage, the figure its own grade was read from; the grade here is
     the school's own scale applied to the session average. --}}
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
@endphp

<div class="overflow-hidden rounded-[6px]" style="border: 1.5px solid {{ $brandSecondary }};" data-session-summary>
    <div class="py-1.5 text-center text-[11px] font-extrabold uppercase tracking-wide text-white" style="background-color: {{ $brandSecondary }};">
        Cumulative Session Result &middot; {{ $sessionSummary['session'] }}
    </div>

    <table class="w-full border-collapse text-left text-[9px]">
        <thead>
            <tr class="text-white" style="background-color: {{ $brandSecondary }};">
                <th class="w-[26px] px-1.5 py-1.5 text-center text-[8.5px] font-extrabold uppercase" style="border: 0.5px solid rgba(255,255,255,0.25);">S/N</th>
                <th class="px-1.5 py-1.5 text-[8.5px] font-extrabold uppercase" style="border: 0.5px solid rgba(255,255,255,0.25);">Subject</th>
                @foreach ($termKeys as $termLabel)
                    <th class="w-[58px] px-1.5 py-1.5 text-center text-[8.5px] font-extrabold uppercase leading-tight" style="border: 0.5px solid rgba(255,255,255,0.25);">{{ $termLabel }}<span class="block text-[7px] font-semibold normal-case opacity-80">(%)</span></th>
                @endforeach
                <th class="w-[54px] px-1.5 py-1.5 text-center text-[8.5px] font-extrabold uppercase" style="border: 0.5px solid rgba(255,255,255,0.25);">Total</th>
                <th class="w-[58px] px-1.5 py-1.5 text-center text-[8.5px] font-extrabold uppercase leading-tight" style="border: 0.5px solid rgba(255,255,255,0.25);">Average<span class="block text-[7px] font-semibold normal-case opacity-80">(%)</span></th>
                <th class="w-[46px] px-1.5 py-1.5 text-center text-[8.5px] font-extrabold uppercase" style="border: 0.5px solid rgba(255,255,255,0.25);">Grade</th>
                <th class="w-[86px] px-1.5 py-1.5 text-center text-[8.5px] font-extrabold uppercase" style="border: 0.5px solid rgba(255,255,255,0.25);">Remark</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sessionSummary['subjects'] as $row)
                <tr style="background-color: {{ $loop->even ? $rowStriped : $rowPlain }};">
                    <td class="border border-gray-200 px-1.5 py-[4px] text-center font-semibold text-gray-900">{{ $loop->iteration }}</td>
                    <td class="border border-gray-200 px-2 py-[4px] font-semibold text-gray-900">{{ $row['name'] }}</td>
                    @foreach (array_keys($termKeys) as $termKey)
                        <td class="border border-gray-200 px-1.5 py-[4px] text-center font-semibold text-gray-900">{{ $pct($row['terms'][$termKey] ?? null) }}</td>
                    @endforeach
                    <td class="border border-gray-200 px-1.5 py-[4px] text-center font-extrabold text-gray-900">{{ $pct($row['total']) }}</td>
                    <td class="border border-gray-200 px-1.5 py-[4px] text-center font-semibold text-gray-900">{{ $pct($row['average']) }}</td>
                    <td class="border border-gray-200 px-1.5 py-[4px] text-center text-[11px] font-extrabold" style="color: {{ $colorForPercentage($row['average']) }};">{{ $row['grade'] }}</td>
                    <td class="border border-gray-200 px-1.5 py-[4px] text-center font-semibold text-gray-900">{{ $row['remark'] ?? 'N/A' }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="font-extrabold" style="background-color: {{ $brandSecondary }}12; color: {{ $brandSecondary }};">
                <td colspan="2" class="border border-gray-300 px-2 py-1.5 uppercase">Term Average</td>
                @foreach (array_keys($termKeys) as $termKey)
                    <td class="border border-gray-300 px-1.5 py-1.5 text-center">{{ $pct($sessionSummary['terms'][$termKey]['average'] ?? null) }}</td>
                @endforeach
                <td class="border border-gray-300 px-1.5 py-1.5 text-center"></td>
                <td class="border border-gray-300 px-1.5 py-1.5 text-center">{{ $pct($sessionSummary['average']) }}</td>
                <td class="border border-gray-300 px-1.5 py-1.5 text-center text-[11px]" style="color: {{ $sessionColor }};">{{ $sessionSummary['grade'] }}</td>
                <td class="border border-gray-300 px-1.5 py-1.5"></td>
            </tr>
        </tfoot>
    </table>

    <div class="grid grid-cols-4 text-center text-[9px]" style="border-top: 1.5px solid {{ $brandSecondary }};">
        @foreach ([
            ['Session Total Marks', rtrim(rtrim(number_format((float) $sessionSummary['total'], 2), '0'), '.').' / '.$sessionSummary['max']],
            ['Session Average', $pct($sessionSummary['average']).($sessionSummary['average'] !== null ? '%' : '')],
            ['Session Grade', $sessionSummary['grade'].($sessionSummary['remark'] ? ' ('.strtoupper($sessionSummary['remark']).')' : '')],
            ['Session Position', $sessionPositionLabel.($sessionPosition ? ' of '.$sessionSummary['numberInClass'] : '')],
        ] as [$label, $value])
            <div class="px-2 py-1.5 {{ $loop->last ? '' : 'border-r border-gray-200' }}">
                <span class="block text-[7.5px] font-bold uppercase" style="color: {{ $brandSecondary }};">{{ $label }}</span>
                <span class="block font-extrabold text-gray-900" @if ($label === 'Session Grade') style="color: {{ $sessionColor }};" @endif>{{ $value }}</span>
            </div>
        @endforeach
    </div>
</div>
