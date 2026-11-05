<div x-data="toastNotifications()"
     x-init="initToasts()"
     class="fixed top-5 right-5 z-[9999] flex flex-col gap-3 max-w-sm w-full pointer-events-none px-4 sm:px-0">
    <template x-for="toast in toasts" :key="toast.id">
        <div x-show="toast.visible"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-x-8 scale-95"
             x-transition:enter-end="opacity-100 translate-x-0 scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-x-0 scale-100"
             x-transition:leave-end="opacity-0 translate-x-8 scale-95"
             class="pointer-events-auto relative overflow-hidden flex items-start gap-3 p-4 rounded-xl shadow-xl border bg-white/95 backdrop-blur-md transition-all duration-200"
             :class="{
                 'border-emerald-200 text-emerald-950': toast.type === 'success',
                 'border-rose-200 text-rose-950': toast.type === 'error' || toast.type === 'danger',
                 'border-amber-200 text-amber-950': toast.type === 'warning',
                 'border-sky-200 text-sky-950': toast.type === 'info'
             }">

            <!-- Icon -->
            <div class="flex-shrink-0 w-8 h-8 rounded-lg flex items-center justify-center font-bold"
                 :class="{
                     'bg-emerald-100 text-emerald-600': toast.type === 'success',
                     'bg-rose-100 text-rose-600': toast.type === 'error' || toast.type === 'danger',
                     'bg-amber-100 text-amber-600': toast.type === 'warning',
                     'bg-sky-100 text-sky-600': toast.type === 'info'
                 }">
                <template x-if="toast.type === 'success'">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </template>
                <template x-if="toast.type === 'error' || toast.type === 'danger'">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </template>
                <template x-if="toast.type === 'warning'">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </template>
                <template x-if="toast.type === 'info'">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </template>
            </div>

            <!-- Message Content -->
            <div class="flex-1 min-w-0 pt-0.5">
                <p class="text-xs font-bold uppercase tracking-wider mb-0.5"
                   :class="{
                       'text-emerald-700': toast.type === 'success',
                       'text-rose-700': toast.type === 'error' || toast.type === 'danger',
                       'text-amber-700': toast.type === 'warning',
                       'text-sky-700': toast.type === 'info'
                   }"
                   x-text="toast.title"></p>
                <p class="text-xs font-medium text-slate-700 leading-snug break-words" x-text="toast.message"></p>
            </div>

            <!-- Close Button -->
            <button type="button" @click="dismiss(toast.id)"
                    class="flex-shrink-0 text-slate-400 hover:text-slate-600 p-1 rounded-lg hover:bg-slate-100 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>

            <!-- Subtle 3s Progress Line -->
            <div class="absolute bottom-0 left-0 right-0 h-1 bg-slate-100">
                <div class="h-full transition-all linear duration-3000"
                     :style="{ width: toast.progress + '%' }"
                     :class="{
                         'bg-emerald-500': toast.type === 'success',
                         'bg-rose-500': toast.type === 'error' || toast.type === 'danger',
                         'bg-amber-500': toast.type === 'warning',
                         'bg-sky-500': toast.type === 'info'
                     }"></div>
            </div>
        </div>
    </template>
</div>

<script>
    function toastNotifications() {
        return {
            toasts: [],
            initToasts() {
                // Read session flash messages
                @if(session('success'))
                    this.add('success', 'Berhasil', @json(session('success')));
                @endif
                @if(session('error'))
                    this.add('error', 'Gagal', @json(session('error')));
                @endif
                @if(session('warning'))
                    this.add('warning', 'Peringatan', @json(session('warning')));
                @endif
                @if(session('info'))
                    this.add('info', 'Informasi', @json(session('info')));
                @endif
                @if(session('status'))
                    this.add('info', 'Informasi', @json(session('status')));
                @endif

                // Custom window event listener for dynamic toast triggers
                window.addEventListener('toast', (e) => {
                    if (e.detail && e.detail.message) {
                        this.add(
                            e.detail.type || 'success',
                            e.detail.title || null,
                            e.detail.message
                        );
                    }
                });

                // Global helper function: window.notify('Pesan notifikasi', 'success', 'Judul')
                window.notify = (message, type = 'success', title = null) => {
                    this.add(type, title, message);
                };
            },
            add(type = 'success', title = null, message = '') {
                const id = Date.now() + Math.random().toString(36).substring(2, 6);
                const defaultTitles = {
                    success: 'Berhasil',
                    error: 'Gagal',
                    danger: 'Gagal',
                    warning: 'Peringatan',
                    info: 'Informasi'
                };
                
                const toast = {
                    id: id,
                    type: type,
                    title: title || defaultTitles[type] || 'Notifikasi',
                    message: message,
                    visible: true,
                    progress: 100
                };

                this.toasts.push(toast);

                // Progress animation step
                setTimeout(() => {
                    const found = this.toasts.find(t => t.id === id);
                    if (found) found.progress = 0;
                }, 50);

                // Auto-dismiss after 3 seconds (3000ms)
                setTimeout(() => {
                    this.dismiss(id);
                }, 3000);
            },
            dismiss(id) {
                const toast = this.toasts.find(t => t.id === id);
                if (toast) {
                    toast.visible = false;
                    setTimeout(() => {
                        this.toasts = this.toasts.filter(t => t.id !== id);
                    }, 300);
                }
            }
        }
    }
</script>
