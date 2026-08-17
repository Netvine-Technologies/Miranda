@props(['location', 'timezone'])

<div
    class="market-clock {{ $timezone ? '' : 'market-clock-unknown' }}"
    data-market-clock
    data-timezone="{{ $timezone }}"
    data-opening-hour="{{ (int) config('lead-markets.calling_hours.start', 9) }}"
    data-closing-hour="{{ (int) config('lead-markets.calling_hours.end', 17) }}"
>
    <span class="market-clock-location">{{ $location }}</span>
    @if ($timezone)
        <strong class="market-clock-time">Checking local time...</strong>
        <span class="market-clock-status">Checking calling hours...</span>
    @else
        <strong class="market-clock-time">Local time unavailable</strong>
        <span class="market-clock-status">This location needs a configured time zone.</span>
    @endif
</div>
