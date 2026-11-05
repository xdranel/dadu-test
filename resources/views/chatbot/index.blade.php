<div x-data="chatbot()" x-init="initListeners()" id="chatbot-widget">

    <!-- CHAT WINDOW (fixed, draggable, resizable) -->
    <div x-show="isOpen" x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 scale-90"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-90"
         class="fixed flex flex-col bg-white rounded-2xl overflow-hidden z-[60] border border-slate-200/60"
         :class="{ 'select-none': isDragging || isResizing }"
         :style="`left:${chatX}px; top:${chatY}px; width:${chatWidth}px; height:${chatHeight}px;
                  box-shadow: 0 8px 40px rgba(0,0,0,0.08), 0 2px 8px rgba(0,0,0,0.04);`">

        <!-- HEADER (Drag Handle) -->
        <div class="flex items-center justify-between px-4 py-3 flex-shrink-0 cursor-move bg-[#FAFAFA] border-b border-slate-100"
             @mousedown.prevent="startDrag($event)"
             @touchstart.prevent="startDrag($event)">

            <div class="flex items-center gap-2.5 pointer-events-none">
                <!-- Grip drag indicator -->
                <div class="text-slate-300 flex items-center justify-center pointer-events-none select-none">
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M7 2a2 2 0 10.001 4.001A2 2 0 007 2zm0 6a2 2 0 10.001 4.001A2 2 0 007 8zm0 6a2 2 0 10.001 4.001A2 2 0 007 14zm6-12a2 2 0 10.001 4.001A2 2 0 0013 2zm0 6a2 2 0 10.001 4.001A2 2 0 0013 8zm0 6a2 2 0 10.001 4.001A2 2 0 0013 14z"/>
                    </svg>
                </div>

                <!-- Bot Avatar -->
                <div class="relative">
                    <div class="w-8 h-8 rounded-full bg-[#9F1239]/10 flex items-center justify-center overflow-hidden">
                        <img src="{{ asset('assets/daduLogo.svg') }}" alt="Logo" class="w-5 h-5 object-contain">
                    </div>
                    <!-- Online dot -->
                    <span class="absolute -bottom-0.5 -right-0.5 w-2 h-2 bg-emerald-400 border-[1.5px] border-[#FAFAFA] rounded-full"></span>
                </div>

                <div>
                    <h2 class="text-[#1C1917] font-semibold text-[13px] tracking-tight leading-tight">Asisten Direktori Akademik</h2>
                </div>
            </div>

            <!-- Tombol Bersihkan Chat -->
            <button @mousedown.stop @click="clearHistory()"
                    id="btn-clear-chat"
                    class="w-7 h-7 rounded-lg bg-transparent hover:bg-slate-100 flex items-center justify-center transition-all duration-200 group cursor-pointer pointer-events-auto"
                    title="Bersihkan percakapan">
                <svg class="w-3.5 h-3.5 text-slate-400 group-hover:text-slate-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
            </button>

            <!-- Close Button -->
            <button @mousedown.stop @click="closeChat()"
                    id="btn-close-chat"
                    class="w-7 h-7 rounded-lg bg-transparent hover:bg-slate-100 flex items-center justify-center transition-all duration-200 group cursor-pointer pointer-events-auto"
                    title="Tutup Chat">
                <svg class="w-3.5 h-3.5 text-slate-400 group-hover:text-slate-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <!-- MESSAGES AREA -->
        <div class="flex-1 overflow-y-auto px-5 py-5 space-y-4 scroll-smooth" id="chat-messages-container"
             style="background: #FFFFFF;">

            <!-- Empty State / Welcome -->
            <template x-if="messages.length === 0 && !isTyping">
                <div class="flex flex-col items-center justify-center h-full py-8">
                    <!-- Avatar with glow -->
                    <div class="relative mb-5">
                        <div class="absolute inset-0 w-16 h-16 rounded-full bg-[#9F1239]/15 blur-xl animate-glow-pulse"></div>
                        <div class="relative w-16 h-16 rounded-full bg-white border border-slate-100 flex items-center justify-center shadow-sm">
                            <svg class="w-7 h-7 text-[#9F1239]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                            </svg>
                        </div>
                    </div>
                    <p class="text-[#1C1917] font-medium text-[18px] text-center leading-snug">
                        Hai, saya <span class="font-semibold">Aida</span>
                    </p>
                    <p class="text-slate-400 text-[13px] mt-1 text-center">Asisten akademik kamu</p>
                </div>
            </template>

            <!-- Date Separator -->
            <template x-if="messages.length > 0">
                <div class="flex items-center justify-center">
                    <span class="px-3 py-0.5 bg-slate-100 text-slate-400 text-[9px] font-semibold uppercase tracking-wider rounded-full">
                        Hari ini
                    </span>
                </div>
            </template>

            <!-- Messages -->
            <template x-for="(message, index) in messages" :key="index">
                <div class="flex gap-2.5 animate-fade-in"
                     :class="message.sender === 'bot' ? 'justify-start' : 'justify-end'">

                    <!-- Bot Avatar -->
                    <template x-if="message.sender === 'bot'">
                        <div class="flex-shrink-0 mt-1">
                            <div class="w-7 h-7 rounded-full bg-[#9F1239]/10 flex items-center justify-center">
                                <svg class="w-3.5 h-3.5 text-[#9F1239]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                            </div>
                        </div>
                    </template>

                    <!-- Bubble -->
                    <div class="max-w-[80%]">
                        <div class="px-3.5 py-2.5 rounded-2xl text-[13px] leading-relaxed"
                             :class="message.sender === 'bot'
                                ? 'bg-[#F8F8F7] text-[#1C1917] rounded-tl-md'
                                : 'bg-[#FDECEF] text-[#1C1917] rounded-tr-md'">
                            <p x-html="message.text"></p>

                            <!-- Clickable Source Chips / Badges -->
                            <template x-if="message.sources && message.sources.length > 0">
                                <div class="flex flex-wrap gap-1.5 mt-2.5 pt-2 border-t border-slate-100">
                                    <span class="text-[9px] font-semibold text-slate-400 uppercase tracking-wider block w-full">Rujukan:</span>
                                    <template x-for="src in message.sources" :key="src.filename">
                                        <a :href="src.id ? `/dashboard/documents/${src.id}` : '#'"
                                           :target="src.id ? '_blank' : '_self'"
                                           class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-medium bg-slate-50 hover:bg-[#9F1239]/5 border border-slate-200 rounded-full text-[#9F1239] transition-all duration-150 cursor-pointer hover:no-underline hover:border-[#9F1239]/20"
                                           :title="src.id ? 'Buka detail dokumen' : 'Dokumen tidak ditemukan di sistem'">
                                            <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                            </svg>
                                            <span x-text="src.title"></span>
                                        </a>
                                    </template>
                                </div>
                            </template>

                            <!-- Action Button on Error -->
                            <template x-if="message.isError">
                                <button @click="retryLastMessage()"
                                        class="mt-2.5 inline-flex items-center gap-1.5 px-3 py-1.5 text-[11.5px] font-semibold text-[#9F1239] bg-[#9F1239]/5 hover:bg-[#9F1239]/10 border border-[#9F1239]/15 rounded-lg transition-all duration-150 cursor-pointer hover:shadow-sm">
                                    Coba Lagi
                                </button>
                            </template>
                        </div>
                        <span class="block mt-1 text-[9px] text-slate-400 font-medium"
                              :class="message.sender === 'bot' ? 'text-left pl-1' : 'text-right pr-1'"
                              x-text="message.time"></span>
                    </div>

                    <!-- User Avatar -->
                    <template x-if="message.sender === 'user'">
                        <div class="flex-shrink-0 mt-1">
                            <div class="w-7 h-7 rounded-full bg-slate-200 flex items-center justify-center">
                                <span class="text-slate-600 text-[10px] font-bold">
                                    {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                                </span>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            <!-- Typing Indicator -->
            <div x-show="isTyping" x-transition class="flex gap-2.5 justify-start">
                <div class="flex-shrink-0 mt-1">
                    <div class="w-7 h-7 rounded-full bg-[#9F1239]/10 flex items-center justify-center animate-pulse">
                        <svg class="w-3.5 h-3.5 text-[#9F1239]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </div>
                </div>
                <div class="bg-[#F8F8F7] rounded-2xl rounded-tl-md px-4 py-3">
                    <div class="flex items-center gap-2">
                        <div class="flex items-center gap-1">
                            <span class="w-1.5 h-1.5 bg-slate-300 rounded-full animate-bounce" style="animation-delay: 0ms"></span>
                            <span class="w-1.5 h-1.5 bg-slate-300 rounded-full animate-bounce" style="animation-delay: 150ms"></span>
                            <span class="w-1.5 h-1.5 bg-slate-300 rounded-full animate-bounce" style="animation-delay: 300ms"></span>
                        </div>
                        <span class="text-[11px] text-slate-400 font-medium">Mencari di dokumen akademik...</span>
                    </div>
                </div>
            </div>

        </div>

        <!-- QUICK REPLIES -->
        <div x-show="quickReplies.length > 0 && !isTyping"
             x-transition
             class="px-4 py-2.5 border-t border-slate-100 bg-white flex-shrink-0">
            <div class="flex gap-2 overflow-x-auto chatbot-scrollbar-hide pb-0.5">
                <template x-for="(reply, idx) in quickReplies" :key="idx">
                    <button @click="sendQuickReply(reply)"
                            class="flex-shrink-0 px-3 py-1.5 text-[13px] font-medium text-slate-600 bg-[#F3F4F6] hover:bg-[#9F1239]/5 hover:text-[#9F1239] rounded-full transition-all duration-200"
                            x-text="reply">
                    </button>
                </template>
            </div>
        </div>

        <!-- INPUT FIELD -->
        <div class="px-4 py-3 border-t border-slate-100 bg-white flex-shrink-0">
            <form @submit.prevent="sendMessage()" class="flex items-center gap-2">
                <!-- Text Input -->
                <div class="flex-1 relative">
                    <input type="text"
                           id="chat-input"
                           x-model="userInput"
                           @keydown.enter.prevent="sendMessage()"
                           placeholder="Tanyakan tentang akademik..."
                           autocomplete="off"
                           class="w-full px-4 py-2.5 bg-[#F5F5F5] border-0 rounded-full text-[13px] text-[#1C1917] placeholder-slate-400
                                  focus:outline-none focus:ring-2 focus:ring-[#9F1239]/15 focus:bg-white transition-all duration-200 shadow-sm"
                           :disabled="isTyping">
                </div>

                <!-- Send Button -->
                <button type="submit"
                        id="btn-send-message"
                        :disabled="!userInput.trim() || isTyping"
                        class="flex-shrink-0 w-9 h-9 rounded-full flex items-center justify-center transition-all duration-300 transform hover:scale-105 active:scale-95 disabled:opacity-40 disabled:hover:scale-100"
                        :class="userInput.trim() && !isTyping
                            ? 'bg-[#9F1239] shadow-sm hover:shadow-md cursor-pointer'
                            : 'bg-slate-200 cursor-not-allowed'"
                        title="Kirim Pesan">
                    <svg class="w-4 h-4 transition-colors" :class="userInput.trim() && !isTyping ? 'text-white' : 'text-slate-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                              d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                    </svg>
                </button>
            </form>
        </div>

        <!-- RESIZE HANDLES -->
        <!-- Right edge -->
        <div class="absolute top-0 right-0 w-1.5 h-full cursor-ew-resize z-10 hover:bg-slate-200/40 transition-colors"
             @mousedown.prevent="startResize($event, 'right')"
             @touchstart.prevent="startResize($event, 'right')"></div>
        <!-- Left edge -->
        <div class="absolute top-0 left-0 w-1.5 h-full cursor-ew-resize z-10 hover:bg-slate-200/40 transition-colors"
             @mousedown.prevent="startResize($event, 'left')"
             @touchstart.prevent="startResize($event, 'left')"></div>
        <!-- Bottom edge -->
        <div class="absolute bottom-0 left-0 w-full h-1.5 cursor-ns-resize z-10 hover:bg-slate-200/40 transition-colors"
             @mousedown.prevent="startResize($event, 'bottom')"
             @touchstart.prevent="startResize($event, 'bottom')"></div>
        <!-- Top edge -->
        <div class="absolute top-0 left-0 w-full h-1.5 cursor-ns-resize z-10 hover:bg-slate-200/40 transition-colors"
             @mousedown.prevent="startResize($event, 'top')"
             @touchstart.prevent="startResize($event, 'top')"></div>
        <!-- Bottom-right corner -->
        <div class="absolute bottom-0 right-0 w-4 h-4 cursor-nwse-resize z-20 group"
             @mousedown.prevent="startResize($event, 'bottom-right')"
             @touchstart.prevent="startResize($event, 'bottom-right')">
            <svg class="w-3 h-3 text-slate-200 group-hover:text-slate-400 absolute bottom-0.5 right-0.5 transition-colors" viewBox="0 0 24 24" fill="none">
                <path d="M22 22L16 22M22 22L22 16M22 22L12 22M22 22L22 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
        </div>
        <!-- Bottom-left corner -->
        <div class="absolute bottom-0 left-0 w-4 h-4 cursor-nesw-resize z-20"
             @mousedown.prevent="startResize($event, 'bottom-left')"
             @touchstart.prevent="startResize($event, 'bottom-left')"></div>
        <!-- Top-right corner -->
        <div class="absolute top-0 right-0 w-4 h-4 cursor-nesw-resize z-20"
             @mousedown.prevent="startResize($event, 'top-right')"
             @touchstart.prevent="startResize($event, 'top-right')"></div>
        <!-- Top-left corner -->
        <div class="absolute top-0 left-0 w-4 h-4 cursor-nwse-resize z-20"
             @mousedown.prevent="startResize($event, 'top-left')"
             @touchstart.prevent="startResize($event, 'top-left')"></div>
    </div>

    <!-- FLOATING ACTION BUTTON (FAB) -->
    <button @click="toggleChat()"
            id="btn-toggle-chat"
            class="fixed bottom-6 right-6 z-50 w-14 h-14 rounded-full flex items-center justify-center transition-all duration-300 shadow-lg hover:shadow-xl transform hover:scale-110 active:scale-95 group"
            :class="isOpen
                ? 'bg-slate-700 hover:bg-slate-800'
                : 'bg-[#9F1239] hover:bg-[#881131]'"
            :style="!isOpen ? 'box-shadow: 0 4px 20px rgba(159,18,57,0.25);' : ''"
            title="Asisten Akademik">

        <!-- Chat Icon -->
        <svg x-show="!isOpen" x-transition class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
        </svg>

        <!-- Chevron down Icon -->
        <svg x-show="isOpen" x-transition class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>

        <!-- Notification Badge -->
        <span x-show="!isOpen && unreadCount > 0"
              x-transition
              class="absolute -top-1 -right-1 w-5 h-5 bg-emerald-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center border-2 border-white animate-pulse"
              x-text="unreadCount"></span>
    </button>

</div>

<!-- STYLES -->
<style>
    [x-cloak] { display: none !important; }

    @keyframes fade-in {
        from { opacity: 0; transform: translateY(6px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-in {
        animation: fade-in 0.3s ease-out;
    }

    /* Glow pulse for avatar */
    @keyframes glow-pulse {
        0%, 100% { opacity: 0.4; transform: scale(1); }
        50%      { opacity: 0.7; transform: scale(1.1); }
    }
    .animate-glow-pulse {
        animation: glow-pulse 2.5s ease-in-out infinite;
    }

    /* Custom scrollbar for chat */
    #chat-messages-container::-webkit-scrollbar {
        width: 3px;
    }
    #chat-messages-container::-webkit-scrollbar-track {
        background: transparent;
    }
    #chat-messages-container::-webkit-scrollbar-thumb {
        background: #e2e8f0;
        border-radius: 999px;
    }
    #chat-messages-container::-webkit-scrollbar-thumb:hover {
        background: #cbd5e1;
    }

    /* Hide scrollbar for quick replies */
    .chatbot-scrollbar-hide {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
    .chatbot-scrollbar-hide::-webkit-scrollbar {
        display: none;
    }
</style>

<!-- ALPINE.JS COMPONENT -->
<script>
    function chatbot() {
        return {
            isOpen: false,
            isTyping: false,
            userInput: '',
            messages: [],
            quickReplies: [],
            conversationHistory: [],   // Riwayat percakapan {role, content} — reset saat refresh
            unreadCount: 1,
            initialized: false,
            lastQuestion: '',

            // Position & Size
            chatX: 0,
            chatY: 0,
            chatWidth: 400,
            chatHeight: 560,
            minWidth: 300,
            maxWidth: 800,
            minHeight: 350,
            maxHeight: 900,

            //  Drag state
            isDragging: false,
            dragOffsetX: 0,
            dragOffsetY: 0,

            //  Resize state
            isResizing: false,
            resizeEdge: '',
            resizeStartX: 0,
            resizeStartY: 0,
            resizeStartW: 0,
            resizeStartH: 0,
            resizeStartLeft: 0,
            resizeStartTop: 0,

            /**
             * Register global mouse/touch listeners for drag and resize.
             */
            initListeners() {
                const onMove = (e) => {
                    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                    const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                    if (this.isDragging) this.onDrag(clientX, clientY);
                    if (this.isResizing) this.onResize(clientX, clientY);
                };
                const onEnd = () => {
                    this.isDragging = false;
                    this.isResizing = false;
                    document.body.style.cursor = '';
                };

                document.addEventListener('mousemove', onMove);
                document.addEventListener('touchmove', onMove, { passive: false });
                document.addEventListener('mouseup', onEnd);
                document.addEventListener('touchend', onEnd);
            },

            // DRAG

            /**
             * Begin dragging from the header.
             */
            startDrag(e) {
                const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                this.isDragging = true;
                this.dragOffsetX = clientX - this.chatX;
                this.dragOffsetY = clientY - this.chatY;
                document.body.style.cursor = 'move';
            },

            /**
             * Handle drag movement, clamp to viewport.
             */
            onDrag(clientX, clientY) {
                let newX = clientX - this.dragOffsetX;
                let newY = clientY - this.dragOffsetY;

                // Clamp so the window stays within viewport
                const maxX = window.innerWidth - this.chatWidth;
                const maxY = window.innerHeight - this.chatHeight;
                newX = Math.max(0, Math.min(newX, maxX));
                newY = Math.max(0, Math.min(newY, maxY));

                this.chatX = newX;
                this.chatY = newY;
            },

            // RESIZE
            /**
             * Begin resizing from an edge or corner.
             */
            startResize(e, edge) {
                const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                this.isResizing = true;
                this.resizeEdge = edge;
                this.resizeStartX = clientX;
                this.resizeStartY = clientY;
                this.resizeStartW = this.chatWidth;
                this.resizeStartH = this.chatHeight;
                this.resizeStartLeft = this.chatX;
                this.resizeStartTop = this.chatY;
            },

            /**
             * Handle resize movement based on edge direction.
             */
            onResize(clientX, clientY) {
                const dx = clientX - this.resizeStartX;
                const dy = clientY - this.resizeStartY;
                const edge = this.resizeEdge;

                let newW = this.resizeStartW;
                let newH = this.resizeStartH;
                let newX = this.resizeStartLeft;
                let newY = this.resizeStartTop;

                // Horizontal
                if (edge.includes('right')) {
                    newW = this.resizeStartW + dx;
                }
                if (edge.includes('left')) {
                    newW = this.resizeStartW - dx;
                    newX = this.resizeStartLeft + dx;
                }

                // Vertical
                if (edge.includes('bottom')) {
                    newH = this.resizeStartH + dy;
                }
                if (edge.includes('top')) {
                    newH = this.resizeStartH - dy;
                    newY = this.resizeStartTop + dy;
                }

                // Clamp dimensions
                newW = Math.max(this.minWidth, Math.min(this.maxWidth, newW));
                newH = Math.max(this.minHeight, Math.min(this.maxHeight, newH));

                // If clamped, adjust position back so it doesn't jump
                if (edge.includes('left')) {
                    newX = this.resizeStartLeft + (this.resizeStartW - newW);
                }
                if (edge.includes('top')) {
                    newY = this.resizeStartTop + (this.resizeStartH - newH);
                }

                // Clamp position to viewport
                newX = Math.max(0, newX);
                newY = Math.max(0, newY);

                this.chatWidth = newW;
                this.chatHeight = newH;
                this.chatX = newX;
                this.chatY = newY;
            },

            //  CHAT OPEN / CLOSE / TOGGLE
            /**
             * Toggle chat window open/close.
             */
            toggleChat() {
                this.isOpen ? this.closeChat() : this.openChat();
            },

            /**
             * Open chat and position above FAB.
             */
            openChat() {
                // Position above the FAB (bottom-right)
                this.chatX = window.innerWidth - this.chatWidth - 24;
                this.chatY = window.innerHeight - this.chatHeight - 90;

                // Clamp
                this.chatX = Math.max(0, this.chatX);
                this.chatY = Math.max(0, this.chatY);

                this.isOpen = true;
                this.unreadCount = 0;

                if (!this.initialized) {
                    this.initialized = true;
                    this.loadDemoConversation();
                }

                this.$nextTick(() => this.scrollToBottom());
            },

            /**
             * Close the chat window.
             */
            closeChat() {
                this.isOpen = false;
            },

            //  CHAT LOGIC
            /**
             * Get current time formatted as HH:MM.
             */
            getCurrentTime() {
                const now = new Date();
                return now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
            },

            /**
             * Scroll to the bottom of the chat container.
             */
            scrollToBottom() {
                this.$nextTick(() => {
                    const container = document.getElementById('chat-messages-container');
                    if (container) {
                        container.scrollTo({ top: container.scrollHeight, behavior: 'smooth' });
                    }
                });
            },

            /**
             * Load pre-defined welcome conversation.
             */
            loadDemoConversation() {
                this.messages.push({
                    sender: 'bot',
                    text: 'Halo!  Selamat datang di <strong>Asisten Akademik Terpadu</strong>. Saya siap membantu Anda mencari informasi dari dokumen akademik resmi seperti SOP, Pedoman Akademik, dan MOM.<br><br>Ada yang bisa saya bantu hari ini?',
                    time: this.getCurrentTime()
                });
                this.quickReplies = [
                    'Persyaratan cuti akademik',
                    'Prosedur registrasi ulang',
                    'Batas pengajuan KRS'
                ];
                this.scrollToBottom();
            },

            /**
             * Send user message and fetch real bot response.
             */
            async sendMessage() {
                const text = this.userInput.trim();
                if (!text || this.isTyping) return;

                // Simpan pertanyaan terakhir untuk fitur Coba Lagi
                this.lastQuestion = text;

                // Catat pesan user ke riwayat percakapan
                this.conversationHistory.push({ role: 'user', content: text });

                // Tampilkan pesan user
                this.messages.push({
                    sender: 'user',
                    text: this.escapeHtml(text),
                    time: this.getCurrentTime()
                });
                
                this.userInput = '';
                this.quickReplies = [];
                this.isTyping = true;
                this.scrollToBottom();

                try {
                    const response = await fetch('/chatbot/chat', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            question: text,
                            // Kirim maks 6 pesan terakhir (3 tanya-jawab) agar tidak overload context window LLM
                            history: this.conversationHistory.slice(-6)
                        })
                    });

                    if (!response.ok) {
                        throw new Error('Gagal memproses pertanyaan');
                    }

                    const data = await response.json();
                    
                    // Format response text to render nicely
                    let formattedAnswer = this.formatBotAnswer(data.answer);

                    // Catat jawaban bot ke riwayat percakapan
                    this.conversationHistory.push({ role: 'assistant', content: data.answer });

                    this.messages.push({
                        sender: 'bot',
                        text: formattedAnswer,
                        sources: data.sources || [], // Simpan sources array secara terpisah
                        time: this.getCurrentTime()
                    });

                } catch (error) {
                    console.error(error);
                    this.messages.push({
                        sender: 'bot',
                        text: 'Maaf, terjadi kesalahan saat menghubungi asisten akademik.',
                        time: this.getCurrentTime(),
                        isError: true // Tandai sebagai pesan error untuk memunculkan tombol retry
                    });
                } finally {
                    this.isTyping = false;
                    this.quickReplies = [
                        'Persyaratan cuti akademik',
                        'Prosedur registrasi ulang',
                        'Batas pengajuan KRS'
                    ];
                    this.scrollToBottom();
                }
            },

            /**
             * Mengirim ulang pertanyaan terakhir yang gagal.
             */
            retryLastMessage() {
                if (!this.lastQuestion) return;
                this.userInput = this.lastQuestion;
                this.sendMessage();
            },

            /**
             * Bersihkan seluruh riwayat percakapan dan kembalikan ke tampilan awal.
             */
            clearHistory() {
                this.conversationHistory = [];
                this.messages = [];
                this.quickReplies = [];
                this.loadDemoConversation();
            },

            /**
             * Handle quick reply button clicks.
             */
            sendQuickReply(reply) {
                this.userInput = reply;
                this.sendMessage();
            },

            /**
             * Format jawaban bot dari RAG backend ke HTML yang rapi.
             * Menangani: numbered list, lettered sub-list, bullet list,
             * bold markdown, heading, paragraf, dan baris sumber/rujukan.
             */
            formatBotAnswer(text) {
                if (!text) return '';

                // ── Helper: terapkan format inline (bold, link, URL) ──
                const applyInline = (str) => {
                    // Link markdown: [teks](url) → <a>
                    str = str.replace(
                        /\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g,
                        '<a href="$2" target="_blank" rel="noopener" class="text-[#9F1239] font-semibold underline underline-offset-2 hover:text-[#881131] transition-colors">$1</a>'
                    );
                    // URL mentah → hyperlink dengan label hostname
                    str = str.replace(
                        /(?<!=")(https?:\/\/[^\s<,)]+)/g,
                        (url) => {
                            try {
                                const hostname = new URL(url).hostname.replace('www.', '');
                                return `<a href="${url}" target="_blank" rel="noopener" class="text-[#9F1239] font-semibold underline underline-offset-2 hover:text-[#881131] transition-colors">${hostname} ↗</a>`;
                            } catch (e) { return url; }
                        }
                    );
                    // Bold: **teks** atau __teks__ → <strong>
                    str = str.replace(/\*\*(.+?)\*\*/g, '<strong class="font-semibold text-slate-800">$1</strong>');
                    str = str.replace(/__(.+?)__/g, '<strong class="font-semibold text-slate-800">$1</strong>');
                    return str;
                };

                // 1. Escape HTML untuk cegah XSS
                const escaped = this.escapeHtml(text);
                const lines = escaped.split('\n');
                const output = []; // { type: 'block'|'inline', html }
                let i = 0;

                while (i < lines.length) {
                    const trimmed = lines[i].trim();

                    // ── Baris kosong → spacer antar paragraf ──
                    if (trimmed === '') {
                        output.push({ type: 'block', html: '<div class="my-2"></div>' });
                        i++;
                        continue;
                    }

                    // ── 9. Baris Sumber / Rujukan / 📄 → footnote kecil ──
                    if (/^(📄\s*)?(Sumber|Rujukan)\s*:/i.test(trimmed)) {
                        let src = trimmed
                            .replace(/📄\s*/g, '')                  // Hapus emoji
                            .replace(/[\[\]]/g, '')                  // Hapus kurung siku
                            .replace(/\|\s*Tipe:\s*[^,\n]*/gi, '')   // Hapus "| Tipe: ..."
                            .replace(/\s{2,}/g, ' ')                 // Normalkan spasi ganda
                            .trim();
                        output.push({
                            type: 'block',
                            html: `<div class="mt-2 pt-2 border-t border-slate-100 text-[11px] text-slate-400 font-medium">${src}</div>`
                        });
                        i++;
                        continue;
                    }

                    // ── 6a. Heading markdown: ### teks ──
                    if (/^#{1,3}\s+/.test(trimmed)) {
                        const h = trimmed.replace(/^#{1,3}\s+/, '');
                        output.push({
                            type: 'block',
                            html: `<p class="font-semibold text-slate-700 mt-2 mb-1">${applyInline(h)}</p>`
                        });
                        i++;
                        continue;
                    }

                    // ── 6b. Heading: seluruh baris dibold **Judul** ──
                    if (trimmed.startsWith('**') && trimmed.endsWith('**')) {
                        const inner = trimmed.slice(2, -2);
                        if (inner.length > 0 && !inner.includes('**')) {
                            output.push({
                                type: 'block',
                                html: `<p class="font-semibold text-slate-700 mt-2 mb-1">${applyInline(inner)}</p>`
                            });
                            i++;
                            continue;
                        }
                    }

                    // ── 2. Numbered list: 1. 2. 3. dst ──
                    if (/^\d+\.\s+/.test(trimmed)) {
                        const listItems = [];

                        while (i < lines.length) {
                            const ct = lines[i].trim();

                            // Item bernomor
                            if (/^\d+\.\s+/.test(ct)) {
                                const content = ct.replace(/^\d+\.\s+/, '');
                                let subHtml = '';
                                let j = i + 1;

                                // Cek sub-list huruf (a. b. c.)
                                if (j < lines.length && /^[a-z]\.\s+/.test(lines[j].trim())) {
                                    const subs = [];
                                    while (j < lines.length && /^[a-z]\.\s+/.test(lines[j].trim())) {
                                        subs.push(lines[j].trim().replace(/^[a-z]\.\s+/, ''));
                                        j++;
                                    }
                                    subHtml = '<ol type="a" class="list-[lower-alpha] list-outside ml-5 space-y-1 my-1">'
                                        + subs.map(s => `<li class="leading-relaxed">${applyInline(s)}</li>`).join('')
                                        + '</ol>';
                                }
                                // Cek sub-list bullet (- • *)
                                else if (j < lines.length && /^[•*\-]\s+/.test(lines[j].trim()) && !/^---/.test(lines[j].trim())) {
                                    const subs = [];
                                    while (j < lines.length && /^[•*\-]\s+/.test(lines[j].trim()) && !/^---/.test(lines[j].trim())) {
                                        subs.push(lines[j].trim().replace(/^[•*\-]\s+/, ''));
                                        j++;
                                    }
                                    subHtml = '<ul class="list-disc list-outside ml-4 space-y-1 my-1">'
                                        + subs.map(s => `<li class="leading-relaxed">${applyInline(s)}</li>`).join('')
                                        + '</ul>';
                                }

                                listItems.push(`<li class="leading-relaxed">${applyInline(content)}${subHtml}</li>`);
                                i = subHtml ? j : i + 1;
                            }
                            // Sub-item huruf yang menyusul tanpa parent baru
                            else if (/^[a-z]\.\s+/.test(ct)) {
                                const subs = [];
                                while (i < lines.length && /^[a-z]\.\s+/.test(lines[i].trim())) {
                                    subs.push(lines[i].trim().replace(/^[a-z]\.\s+/, ''));
                                    i++;
                                }
                                const subOl = '<ol type="a" class="list-[lower-alpha] list-outside ml-5 space-y-1 my-1">'
                                    + subs.map(s => `<li class="leading-relaxed">${applyInline(s)}</li>`).join('')
                                    + '</ol>';
                                if (listItems.length > 0) {
                                    listItems[listItems.length - 1] =
                                        listItems[listItems.length - 1].replace(/<\/li>$/, subOl + '</li>');
                                }
                            }
                            // Baris kosong: peek apakah numbered list berlanjut
                            else if (ct === '') {
                                let next = -1;
                                for (let p = i + 1; p < lines.length; p++) {
                                    if (lines[p].trim() !== '') { next = p; break; }
                                }
                                if (next >= 0 && /^\d+\.\s+/.test(lines[next].trim())) {
                                    i++; // skip kosong, lanjut list
                                } else {
                                    break; // akhiri list
                                }
                            }
                            else { break; }
                        }

                        output.push({
                            type: 'block',
                            html: '<ol class="list-decimal list-outside ml-4 space-y-1 my-2">'
                                + listItems.join('') + '</ol>'
                        });
                        continue;
                    }

                    // ── 3. Standalone lettered list: a. b. c. ──
                    if (/^[a-z]\.\s+/.test(trimmed)) {
                        const items = [];
                        while (i < lines.length && /^[a-z]\.\s+/.test(lines[i].trim())) {
                            const content = lines[i].trim().replace(/^[a-z]\.\s+/, '');
                            items.push(`<li class="leading-relaxed">${applyInline(content)}</li>`);
                            i++;
                        }
                        output.push({
                            type: 'block',
                            html: '<ol type="a" class="list-[lower-alpha] list-outside ml-5 space-y-1 my-1">'
                                + items.join('') + '</ol>'
                        });
                        continue;
                    }

                    // ── 4. Bullet list: • * - (bukan ---) ──
                    if (/^[•*\-]\s+/.test(trimmed) && !/^---/.test(trimmed)) {
                        const items = [];
                        while (i < lines.length) {
                            const ct = lines[i].trim();
                            if (/^[•*\-]\s+/.test(ct) && !/^---/.test(ct)) {
                                items.push(`<li class="leading-relaxed">${applyInline(ct.replace(/^[•*\-]\s+/, ''))}</li>`);
                                i++;
                            } else { break; }
                        }
                        output.push({
                            type: 'block',
                            html: '<ul class="list-disc list-outside ml-4 space-y-1 my-2">'
                                + items.join('') + '</ul>'
                        });
                        continue;
                    }

                    // ── Baris teks biasa ──
                    output.push({ type: 'inline', html: applyInline(trimmed) });
                    i++;
                }

                // ── 8. Gabungkan: inline berturut → <br>, block tetap sendiri ──
                const parts = [];
                for (const item of output) {
                    if (item.type === 'inline' && parts.length > 0 && parts[parts.length - 1].type === 'inline') {
                        parts[parts.length - 1].html += '<br>' + item.html;
                    } else {
                        parts.push({ ...item });
                    }
                }

                return parts.map(p => p.html).join('');
            },

            /**
             * Escape HTML special characters.
             */
            escapeHtml(text) {
                const div = document.createElement('div');
                div.appendChild(document.createTextNode(text));
                return div.innerHTML;
            },
        };
    }
</script>
