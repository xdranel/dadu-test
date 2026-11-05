<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor Antrian Layanan</title>
    <meta name="robots" content="noindex,nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --red-primary: #DC2626;
            --red-dark: #B91C1C;
            --red-muted: #EF4444;
            --bg-white: #FFFFFF;
            --border-red: #DC2626;
            --text-inactive: #9CA3AF;
        }

        html, body {
            width: 100%;
            height: 100%;
            overflow: hidden;
            background-color: var(--bg-white);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* FULLSCREEN CONTAINER */
        .display-container {
            width: 100vw;
            height: 100vh;
            padding: clamp(24px, 3.5vw, 48px);
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: clamp(24px, 3.5vw, 48px);
            align-items: stretch;
            background-color: var(--bg-white);
        }

        @media (max-width: 768px) {
            .display-container {
                grid-template-columns: 1fr;
                height: auto;
                min-height: 100vh;
                overflow-y: auto;
            }
        }

        /* COUNTER CARD */
        .counter-card {
            background-color: var(--bg-white);
            border: 1.5px solid var(--border-red);
            border-radius: clamp(16px, 1.8vw, 28px);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            padding: clamp(32px, 5vh, 64px) clamp(24px, 3vw, 48px);
            text-align: center;
        }

        /* ── NAMA LOKET ──────────────────────────────────── */
        .counter-name {
            font-size: clamp(40px, 5vw, 110px);
            font-weight: 600;
            color: var(--red-primary);
            letter-spacing: clamp(1px, 0.2vw, 3px);
            text-transform: uppercase;
            line-height: 1.2;
        }

        /* NOMOR ANTRIAN */
        .counter-number {
            font-family: 'Inter', sans-serif;
            font-size: clamp(90px, 16vw, 240px);
            font-weight: 600;
            line-height: 0.95;
            letter-spacing: -0.04em;
            font-variant-numeric: tabular-nums;
            color: var(--red-primary);
            text-shadow: none;
            filter: none;
        }

        .counter-card:not(.active) .counter-number {
            color: var(--text-inactive);
        }

        /* ── STATUS ──────────────────────────────────────── */
        .counter-status {
            font-size: clamp(16px, 1.8vw, 28px);
            font-weight: 700;
            color: var(--red-primary);
            line-height: 1.2;
            letter-spacing: 0.5px;
        }

        .counter-card:not(.active) .counter-status {
            color: var(--text-inactive);
            font-weight: 600;
        }

        /* SUBTLE NUMBER CHANGE ANIMATION*/
        @keyframes number-pop {
            0% {
                transform: scale(0.94);
                opacity: 0.7;
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        .number-changed {
            animation: number-pop 0.25s ease-out forwards;
        }
    </style>
</head>
<body>

    <main class="display-container" id="display-container">
        @foreach($counters as $counter)
            @php $code = $counter->queueState?->currentTicket?->code; @endphp
            <div class="counter-card {{ $code ? 'active' : '' }}"
                 id="counter-card-{{ $counter->id }}"
                 data-counter-id="{{ $counter->id }}">
                <div class="counter-name" id="counter-name-{{ $counter->id }}">
                    {{ $counter->name }}
                </div>
                <div class="counter-number" id="counter-num-{{ $counter->id }}">
                    {{ $code ?? '-' }}
                </div>
                <div class="counter-status" id="counter-status-{{ $counter->id }}">
                    {{ $code ? 'Sedang Dipanggil' : 'Menunggu antrian...' }}
                </div>
            </div>
        @endforeach
    </main>

    <script>
        // State nomor terakhir per loket agar tidak kosong saat offline / reconnecting
        const lastNumbers = {};
        @foreach($counters as $counter)
            lastNumbers[{{ $counter->id }}] = @json($counter->queueState?->currentTicket?->code ?? null);
        @endforeach

        // Polling data antrian ke endpoint JSON tanpa PII
        async function poll() {
            try {
                const res = await fetch('{{ route("queue.display.json") }}', {
                    cache: 'no-store',
                    headers: { 'Accept': 'application/json' }
                });

                if (!res.ok) return;

                const data = await res.json();
                if (!data || !Array.isArray(data.counters)) return;

                data.counters.forEach(counter => {
                    const newCode = counter.current_number ?? null;
                    const card    = document.getElementById('counter-card-' + counter.id);
                    const numEl   = document.getElementById('counter-num-' + counter.id);
                    const stEl    = document.getElementById('counter-status-' + counter.id);

                    if (!card || !numEl || !stEl) return;

                    const prev = lastNumbers[counter.id] ?? null;
                    if (newCode !== prev) {
                        lastNumbers[counter.id] = newCode;

                        numEl.textContent = newCode ?? '-';
                        numEl.classList.remove('number-changed');
                        void numEl.offsetWidth; // trigger reflow
                        numEl.classList.add('number-changed');

                        if (newCode) {
                            card.classList.add('active');
                            stEl.textContent = 'Sedang Dipanggil';
                        } else {
                            card.classList.remove('active');
                            stEl.textContent = 'Menunggu antrian...';
                        }
                    }
                });
            } catch (e) {
                // Pertahankan tampilan angka terakhir jika jaringan terputus
            }
        }

        // Polling setiap 2.5 detik
        setInterval(poll, 2500);
    </script>
</body>
</html>
