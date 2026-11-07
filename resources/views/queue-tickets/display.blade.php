<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor Antrian Layanan</title>
    <meta name="robots" content="noindex,nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <style>
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

        #stage {
            position: absolute;
            top: 50%;
            left: 50%;
            width: 1920px;
            height: 1080px;
            transform-origin: center center;
            transform: translate(-50%, -50%) scale(var(--s, 1));
        }
        /* FULLSCREEN CONTAINER */
        .display-container {
            width: 1920px;
            height: 1036px; 
            padding: 44px;
            display: grid;
            grid-template-columns: repeat(var(--cols, 2), 1fr);
            gap: 44px;
            align-items: stretch;
            background-color: var(--bg-white);
        }

        /* COUNTER CARD */
        .counter-card {
            background-color: var(--bg-white);
            border: 2px solid var(--border-red);
            border-radius: 32px;
            padding: 48px 30px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            text-align: center;
        }

        /* NAMA LOKET */
        .counter-name {
            font-size: 100px;
            font-weight: 600;
            letter-spacing: 0.08em;
            color: var(--red-primary);
            text-transform: uppercase;
            line-height: 1.2;
        }

        /* NOMOR ANTRIAN */
        .counter-number {
            font-family: 'Inter', sans-serif;
            font-size: 440px;
            font-weight: 600;
            line-height: 1;
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
            font-size: 44px;
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

        /* FOOTER STATUS & AUDIO UNLOCK */
        .display-footer {
            height: 44px;
            width: 100vw;
            position: fixed;
            bottom: 0;
            left: 0;
            background-color: #FAFAFA;
            border-top: 1px solid #E5E7EB;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 clamp(16px, 2.5vw, 32px);
            font-size: 12px;
            color: #6B7280;
            z-index: 50;
        }

        .audio-status-container {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: #F59E0B; /* amber: belum aktif */
            display: inline-block;
            transition: background-color 0.2s ease;
        }

        .status-dot.active {
            background-color: #10B981; /* emerald: aktif */
        }

        .btn-enable-audio {
            margin-left: 8px;
            padding: 4px 10px;
            background-color: #DC2626;
            color: #FFFFFF;
            border: none;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: background-color 0.15s ease;
        }

        .btn-enable-audio:hover {
            background-color: #B91C1C;
        }

        /* DEBUG PANEL */
        .debug-panel {
            position: fixed;
            bottom: 50px;
            right: 16px;
            background: rgba(17, 24, 39, 0.95);
            color: #FFFFFF;
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 100;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .debug-panel input {
            width: 52px;
            padding: 2px 6px;
            border-radius: 4px;
            border: 1px solid #4B5563;
            background: #374151;
            color: #FFFFFF;
            font-size: 12px;
        }

        .debug-panel button {
            padding: 4px 10px;
            background: #2563EB;
            color: #FFFFFF;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }

        .debug-panel button:hover {
            background: #1D4ED8;
        }
    </style>
</head>
<body>


    <div id="stage">
        <main class="display-container" id="display-container"
              style="--cols: {{ max(1, count($counters)) }}">
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
    </div>

    @if(request()->query('debug') == '1')
        <aside class="debug-panel" id="debug-panel">
            <strong>DEBUG TES SUARA:</strong>
            <label>No: <input type="number" id="debug-ticket-num" value="15" min="1" max="999"></label>
            <label>Loket: <input type="number" id="debug-counter-num" value="1" min="1" max="20"></label>
            <button type="button" id="btn-debug-test">Tes Suara</button>
        </aside>
    @endif

    <script>
        // ==========================================
        // 1. AUDIO CLIPS CONFIGURATION & PRELOAD
        // ==========================================
        const BASE_AUDIO_URL = "{{ asset('audio/queue') }}";
        const REPEAT_COUNT = 2;
        const REPEAT_PAUSE_MS = 1000;
        const CLIP_TIMEOUT_MS = 5000;

        // Pemetaan klip audio statis
        // Catatan: Loket.mp3 memiliki huruf L kapital pada disk
        const clipFiles = {
            '1': '1.mp3',
            '2': '2.mp3',
            '3': '3.mp3',
            '4': '4.mp3',
            '5': '5.mp3',
            '6': '6.mp3',
            '7': '7.mp3',
            '8': '8.mp3',
            '9': '9.mp3',
            '10': '10.mp3',
            '11': '11.mp3',
            'belas': 'belas.mp3',
            'puluh': 'puluh.mp3',
            'seratus': 'seratus.mp3',
            'ratus': 'ratus.mp3',
            'nomor_antrian': 'nomor_antrian.mp3',
            'silakan_menuju': 'silakan_menuju.mp3',
            'loket': 'Loket.mp3'
        };

        const audioClips = {};
        Object.entries(clipFiles).forEach(([name, file]) => {
            const audio = new Audio(BASE_AUDIO_URL + '/' + file);
            audio.preload = 'auto';
            audio.addEventListener('error', () => {
                console.error('Audio clip not found or failed to load: ' + name + ' (' + file + ')');
            });
            audioClips[name] = audio;
        });

        // ==========================================
        // 2. BUILD CLIPS FUNCTION
        // ==========================================
        function buildClips(n) {
            n = parseInt(n, 10);
            if (isNaN(n) || n < 1 || n > 999) {
                return null;
            }

            if (n >= 1 && n <= 11) {
                return [n.toString()];
            }

            if (n >= 12 && n <= 19) {
                return [(n - 10).toString(), 'belas'];
            }

            if (n >= 20 && n <= 99) {
                const tens = Math.floor(n / 10);
                const ones = n % 10;
                const res = [tens.toString(), 'puluh'];
                if (ones > 0) {
                    res.push(ones.toString());
                }
                return res;
            }

            if (n === 100) {
                return ['seratus'];
            }

            if (n >= 101 && n <= 199) {
                const sub = buildClips(n - 100);
                return sub ? ['seratus', ...sub] : ['seratus'];
            }

            if (n >= 200 && n <= 999) {
                const hundreds = Math.floor(n / 100);
                const rem = n % 100;
                const res = [hundreds.toString(), 'ratus'];
                if (rem > 0) {
                    const sub = buildClips(rem);
                    if (sub) {
                        res.push(...sub);
                    }
                }
                return res;
            }

            return null;
        }

        // ==========================================
        // 3. TERBILANG & TTS FALLBACK
        // ==========================================
        function terbilang(n) {
            n = parseInt(n, 10);
            if (isNaN(n) || n < 0) return '';
            if (n === 0) return 'nol';

            const satuan = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
            if (n < 12) return satuan[n];
            if (n < 20) return terbilang(n - 10) + ' belas';
            if (n < 100) return terbilang(Math.floor(n / 10)) + ' puluh' + (n % 10 > 0 ? ' ' + terbilang(n % 10) : '');
            if (n < 200) return 'seratus' + (n - 100 > 0 ? ' ' + terbilang(n - 100) : '');
            if (n < 1000) return terbilang(Math.floor(n / 100)) + ' ratus' + (n % 100 > 0 ? ' ' + terbilang(n % 100) : '');
            if (n < 2000) return 'seribu' + (n - 1000 > 0 ? ' ' + terbilang(n - 1000) : '');
            if (n < 1000000) return terbilang(Math.floor(n / 1000)) + ' ribu' + (n % 1000 > 0 ? ' ' + terbilang(n % 1000) : '');
            return n.toString();
        }

        function speakFallback(text) {
            return new Promise((resolve) => {
                if (!('speechSynthesis' in window)) {
                    resolve();
                    return;
                }
                try {
                    window.speechSynthesis.cancel();
                    const utterance = new SpeechSynthesisUtterance(text);
                    utterance.lang = 'id-ID';
                    utterance.rate = 0.9;
                    utterance.onend = () => resolve();
                    utterance.onerror = () => resolve();
                    window.speechSynthesis.speak(utterance);
                } catch (e) {
                    resolve();
                }
            });
        }

        // ==========================================
        // 4. WEB AUDIO API CHIME (TING-TONG)
        // ==========================================
        let audioCtx = null;
        function getAudioContext() {
            if (!audioCtx) {
                const AudioContextClass = window.AudioContext || window.webkitAudioContext;
                if (AudioContextClass) {
                    audioCtx = new AudioContextClass();
                }
            }
            return audioCtx;
        }

        function playTingTong() {
            return new Promise((resolve) => {
                try {
                    const ctx = getAudioContext();
                    if (!ctx) {
                        resolve();
                        return;
                    }

                    if (ctx.state === 'suspended') {
                        ctx.resume().catch(() => {});
                    }

                    const now = ctx.currentTime;
                    
                    // Nada 1: "Ting" (E5 / ~659 Hz)
                    const osc1 = ctx.createOscillator();
                    const gain1 = ctx.createGain();
                    osc1.type = 'sine';
                    osc1.frequency.setValueAtTime(659.25, now);
                    gain1.gain.setValueAtTime(0.25, now);
                    gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.35);
                    osc1.connect(gain1);
                    gain1.connect(ctx.destination);
                    osc1.start(now);
                    osc1.stop(now + 0.35);

                    // Nada 2: "Tong" (C5 / ~523 Hz)
                    const osc2 = ctx.createOscillator();
                    const gain2 = ctx.createGain();
                    osc2.type = 'sine';
                    osc2.frequency.setValueAtTime(523.25, now + 0.32);
                    gain2.gain.setValueAtTime(0.001, now);
                    gain2.gain.setValueAtTime(0.28, now + 0.32);
                    gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.85);
                    osc2.connect(gain2);
                    gain2.connect(ctx.destination);
                    osc2.start(now + 0.32);
                    osc2.stop(now + 0.85);

                    setTimeout(resolve, 850);
                } catch (e) {
                    resolve();
                }
            });
        }

        // ==========================================
        // 5. CLIP PLAYER WITH 5s SAFETY TIMEOUT
        // ==========================================
        function playSingleClip(name) {
            return new Promise((resolve) => {
                const audio = audioClips[name];
                if (!audio) {
                    console.warn('Audio clip not registered: ' + name);
                    resolve();
                    return;
                }

                let finished = false;
                let timer = null;

                const cleanup = () => {
                    if (finished) return;
                    finished = true;
                    if (timer) clearTimeout(timer);
                    audio.removeEventListener('ended', onEnded);
                    audio.removeEventListener('error', onError);
                    resolve();
                };

                const onEnded = () => cleanup();
                const onError = () => {
                    console.warn('Clip playback error on ' + name);
                    cleanup();
                };

                timer = setTimeout(() => {
                    console.warn('Safety timeout reached for clip: ' + name);
                    cleanup();
                }, CLIP_TIMEOUT_MS);

                audio.addEventListener('ended', onEnded, { once: true });
                audio.addEventListener('error', onError, { once: true });

                audio.currentTime = 0;
                const playPromise = audio.play();
                if (playPromise !== undefined) {
                    playPromise.catch((err) => {
                        console.warn('Autoplay prevented or failed for clip ' + name, err);
                        cleanup();
                    });
                }
            });
        }

        async function playClipSequence(clipNames) {
            for (const name of clipNames) {
                await playSingleClip(name);
            }
        }

        // ==========================================
        // 6. FIFO QUEUE WORKER
        // ==========================================
        const announcementQueue = [];
        let isQueueBusy = false;

        function enqueueAnnouncement(item) {
            if (announcementQueue.length >= 5) {
                announcementQueue.shift(); // Buang pengumuman terlama jika menumpuk > 5
            }
            announcementQueue.push(item);
            processAnnouncementQueue();
        }

        async function processAnnouncementQueue() {
            if (isQueueBusy || announcementQueue.length === 0) return;
            isQueueBusy = true;

            try {
                while (announcementQueue.length > 0) {
                    const item = announcementQueue.shift();
                    await performAnnouncement(item);
                }
            } finally {
                isQueueBusy = false;
            }
        }

        async function performAnnouncement(item) {
            const ticketClips = buildClips(item.ticketNumber);
            const counterClips = buildClips(item.counterNumber);

            for (let rep = 0; rep < REPEAT_COUNT; rep++) {
                await playTingTong();

                if (ticketClips && counterClips) {
                    const fullClips = [
                        'nomor_antrian',
                        ...ticketClips,
                        'silakan_menuju',
                        'loket',
                        ...counterClips
                    ];
                    await playClipSequence(fullClips);
                } else {
                    // Fallback ucapan via speechSynthesis
                    const spokenText = `Nomor antrian ${terbilang(item.ticketNumber)}, silakan menuju ${item.counterName || ('Loket ' + item.counterNumber)}.`;
                    await speakFallback(spokenText);
                }

                if (rep < REPEAT_COUNT - 1) {
                    await new Promise((r) => setTimeout(r, REPEAT_PAUSE_MS));
                }
            }
        }

        function extractCounterNumber(counter) {
            if (!counter) return 1;
            if (typeof counter.name === 'string') {
                const m = counter.name.match(/\d+/);
                if (m) return parseInt(m[0], 10);
            }
            return counter.id || 1;
        }

        // ==========================================
        // 7. AUTOPLAY & STATUS INDICATOR
        // ==========================================
        const statusDot = document.getElementById('status-dot');
        const statusText = document.getElementById('audio-status-text');
        const btnEnableAudio = document.getElementById('btn-enable-audio');

        function updateAudioStatus(active) {
            if (active) {
                if (statusDot) statusDot.classList.add('active');
                if (statusText) statusText.textContent = 'Suara aktif';
                if (btnEnableAudio) btnEnableAudio.style.display = 'none';
            } else {
                if (statusDot) statusDot.classList.remove('active');
                if (statusText) statusText.textContent = 'Suara belum aktif';
                if (btnEnableAudio) btnEnableAudio.style.display = 'inline-flex';
            }
        }

        function tryUnlockAudio() {
            const ctx = getAudioContext();
            if (!ctx) return;

            if (ctx.state === 'running') {
                updateAudioStatus(true);
            } else if (ctx.state === 'suspended') {
                ctx.resume().then(() => {
                    if (ctx.state === 'running') {
                        updateAudioStatus(true);
                    }
                }).catch(() => {});
            }
        }

        if (btnEnableAudio) {
            btnEnableAudio.addEventListener('click', () => {
                const ctx = getAudioContext();
                if (ctx) {
                    ctx.resume().then(() => {
                        updateAudioStatus(true);
                        playTingTong();
                    });
                } else {
                    updateAudioStatus(true);
                }
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            tryUnlockAudio();
        });
        window.addEventListener('click', () => {
            tryUnlockAudio();
        }, { once: true });

        // Debug panel handler
        const btnDebugTest = document.getElementById('btn-debug-test');
        if (btnDebugTest) {
            btnDebugTest.addEventListener('click', () => {
                const ticketNum = parseInt(document.getElementById('debug-ticket-num')?.value || 15, 10);
                const counterNum = parseInt(document.getElementById('debug-counter-num')?.value || 1, 10);
                enqueueAnnouncement({
                    ticketNumber: ticketNum,
                    counterName: 'Loket ' + counterNum,
                    counterNumber: counterNum
                });
            });
        }

        // ==========================================
        // 8. DATA POLLING & CALL_SEQ DETECTION
        // ==========================================
        const lastNumbers = {};
        const lastCallSeqs = {};
        let isFirstPoll = true;

        @foreach($counters as $counter)
            lastNumbers[{{ $counter->id }}] = @json($counter->queueState?->currentTicket?->code ?? null);
            lastCallSeqs[{{ $counter->id }}] = @json($counter->queueState?->call_seq ?? 0);
        @endforeach

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

                    // Perbarui visual nomor jika berubah
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

                    // Deteksi panggilan suara berdasarkan kenaikan call_seq
                    const currentSeq = counter.call_seq ?? 0;
                    const prevSeq = lastCallSeqs[counter.id];

                    if (isFirstPoll) {
                        // Pada polling pertama, hanya simpan call_seq tanpa memutar suara
                        lastCallSeqs[counter.id] = currentSeq;
                    } else {
                        if (prevSeq !== undefined && currentSeq > prevSeq && counter.number !== null && counter.number !== undefined) {
                            // Update call_seq terakhir dan masukkan ke antrian suara
                            lastCallSeqs[counter.id] = currentSeq;
                            enqueueAnnouncement({
                                ticketNumber: counter.number,
                                counterName: counter.name,
                                counterNumber: extractCounterNumber(counter)
                            });
                        } else {
                            lastCallSeqs[counter.id] = currentSeq;
                        }
                    }
                });

                if (isFirstPoll) {
                    isFirstPoll = false;
                }
            } catch (e) {
                // Pertahankan tampilan nomor terakhir jika koneksi terputus
            }
        }

        // Polling setiap 2.5 detik
        setInterval(poll, 2500);
    </script>
</body>
</html>
