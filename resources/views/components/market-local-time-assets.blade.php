<style>
    .market-clock { min-width: 190px; padding: 9px 12px; border: 1px solid #bfdbfe; border-radius: 10px; background: #eff6ff; color: #0f172a; }
    .market-clock-location, .market-clock-time, .market-clock-status { display: block; }
    .market-clock-location { color: #475569; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
    .market-clock-time { margin-top: 3px; font-size: 18px; }
    .market-clock-status { margin-top: 3px; color: #64748b; font-size: 12px; font-weight: 700; }
    .market-clock.market-clock-open { border-color: #86efac; background: #f0fdf4; }
    .market-clock.market-clock-open .market-clock-status { color: #166534; }
    .market-clock.market-clock-soon { border-color: #fde68a; background: #fffbeb; }
    .market-clock.market-clock-soon .market-clock-status { color: #92400e; }
    .market-clock.market-clock-closed, .market-clock-unknown { border-color: #fecaca; background: #fef2f2; }
    .market-clock.market-clock-closed .market-clock-status, .market-clock-unknown .market-clock-status { color: #991b1b; }
</style>
<script>
    (() => {
        function updateMarketClocks() {
            document.querySelectorAll('[data-market-clock]').forEach((clock) => {
                const timezone = clock.dataset.timezone;
                if (!timezone) return;

                try {
                    const parts = new Intl.DateTimeFormat('en-GB', {
                        timeZone: timezone,
                        weekday: 'short',
                        day: '2-digit',
                        month: 'short',
                        hour: '2-digit',
                        minute: '2-digit',
                        hourCycle: 'h23',
                    }).formatToParts(new Date());
                    const value = (type) => parts.find((part) => part.type === type)?.value || '';
                    const hour = Number(value('hour'));
                    const minute = Number(value('minute'));
                    const currentMinute = (hour * 60) + minute;
                    const openingMinute = Number(clock.dataset.openingHour || 9) * 60;
                    const closingMinute = Number(clock.dataset.closingHour || 17) * 60;
                    const minutesUntilOpen = openingMinute - currentMinute;
                    let state = 'closed';
                    let status = 'Outside calling hours';

                    if (currentMinute >= openingMinute && currentMinute < closingMinute) {
                        state = 'open';
                        status = 'Good time to call - closes at 17:00';
                    } else if (minutesUntilOpen > 0 && minutesUntilOpen <= 180) {
                        const hours = Math.floor(minutesUntilOpen / 60);
                        const minutes = minutesUntilOpen % 60;
                        const duration = [hours ? `${hours}h` : '', minutes ? `${minutes}m` : ''].filter(Boolean).join(' ');
                        state = 'soon';
                        status = `Opens in ${duration || 'under a minute'}`;
                    }

                    clock.classList.remove('market-clock-open', 'market-clock-soon', 'market-clock-closed');
                    clock.classList.add(`market-clock-${state}`);
                    clock.querySelector('.market-clock-time').textContent = `${value('weekday')} ${value('day')} ${value('month')} - ${value('hour')}:${value('minute')}`;
                    clock.querySelector('.market-clock-status').textContent = status;
                } catch (error) {
                    clock.classList.add('market-clock-closed');
                    clock.querySelector('.market-clock-time').textContent = 'Local time unavailable';
                    clock.querySelector('.market-clock-status').textContent = 'Invalid time-zone configuration';
                }
            });
        }

        function startMarketClocks() {
            updateMarketClocks();
            window.setInterval(updateMarketClocks, 60000);
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', startMarketClocks, { once: true });
        } else {
            startMarketClocks();
        }
    })();
</script>
