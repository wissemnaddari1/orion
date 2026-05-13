/**
 * Ticket Support Messenger-Style Popup
 * 
 * Features:
 * - Modern messenger-style interface
 * - Floating chat button
 * - Slide-in chat panel
 * - Message bubbles with avatars
 * - Quick reply functionality
 * - Real-time feel with smooth animations
 */

// Initialize immediately when script loads
(function() {
    // Wait for both DOM and authentication check
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    function init() {
        // Only initialize if user is authenticated
        if (!document.body.dataset.userAuthenticated) {
            return;
        }
        initializeMessengerPopup();
    }
})();

function initializeMessengerPopup() {
    injectTicketPopupStyles();

    // Create floating messenger button
    const messengerButton = document.createElement('button');
    messengerButton.id = 'messenger-btn';
    messengerButton.className = 'fixed bottom-6 right-6 bg-gradient-to-br from-emerald-500 to-teal-600 hover:from-blue-600 hover:to-purple-700 text-white rounded-full p-4 shadow-2xl z-50 transition-all duration-300 hover:scale-110 animate-bounce-slow';
    messengerButton.innerHTML = `
        <svg class="w-7 h-7" fill="currentColor" viewBox="0 0 24 24">
            <path d="M12 2C6.48 2 2 6.15 2 11.25c0 2.92 1.44 5.51 3.69 7.23V22l3.41-1.87c.91.25 1.87.37 2.9.37 5.52 0 10-4.15 10-9.25S17.52 2 12 2zm1 12.5h-2v-2h2v2zm0-3.5h-2V6.5h2V11z"/>
            <circle cx="12" cy="8" r="1.5"/><circle cx="12" cy="13" r="1.5"/>
        </svg>
        <span id="ticket-unread-badge" class="hidden absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold rounded-full h-6 w-6 flex items-center justify-center ring-2 ring-white animate-pulse"></span>
    `;
    document.body.appendChild(messengerButton);

    // Create messenger panel
    const messenger = document.createElement('div');
    messenger.id = 'messenger-popup';
    messenger.className = 'fixed bottom-0 right-0 md:bottom-6 md:right-6 w-full md:w-[420px] h-full md:h-[680px] md:rounded-2xl bg-white dark:bg-slate-900 shadow-2xl z-50 transform translate-x-full md:translate-x-[calc(100%+24px)] transition-all duration-300 flex flex-col overflow-hidden';
    messenger.innerHTML = `
        <!-- Header -->
        <div class="bg-gradient-to-r from-emerald-600 to-teal-600 text-white p-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div id="back-btn" class="hidden cursor-pointer hover:bg-white/20 p-1 rounded-lg transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </div>
                <div>
                    <h2 id="messenger-title" class="text-lg font-bold">Support</h2>
                    <p id="messenger-subtitle" class="text-xs text-emerald-100">Your tickets</p>
                </div>
            </div>
            <button id="close-messenger" class="hover:bg-white/20 p-2 rounded-lg transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <!-- Tabs: Tickets | Messagerie -->
        <div id="support-popup-tabs" class="flex border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50">
            <button type="button" id="tab-tickets" class="flex-1 py-3 px-4 text-sm font-medium text-emerald-600 dark:text-emerald-400 border-b-2 border-emerald-600 dark:border-emerald-400 bg-white dark:bg-slate-900 -mb-px">Tickets</button>
            <button type="button" id="tab-messagerie" class="flex-1 py-3 px-4 text-sm font-medium text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 border-b-2 border-transparent">Messagerie</button>
        </div>

        <!-- Tickets tab panel -->
        <div id="ticket-tab-panel" class="flex-1 flex flex-col overflow-hidden">
        <!-- Ticket List View -->
        <div id="ticket-list-view" class="flex-1 flex flex-col overflow-hidden">
            <div class="p-4 border-b border-slate-200 dark:border-slate-800">
                <button id="new-ticket-btn" class="block w-full bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white text-center py-3 px-4 rounded-xl font-semibold transition-all hover:shadow-lg">
                    <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    New Ticket
                </button>
            </div>
            <div id="ticket-list-container" class="flex-1 overflow-y-auto">
                <div class="flex items-center justify-center h-full">
                    <div class="flex flex-col items-center gap-3">
                        <div class="relative">
                            <div class="animate-spin rounded-full h-12 w-12 border-4 border-emerald-200 border-t-emerald-600"></div>
                            <div class="absolute inset-0 rounded-full bg-emerald-50 dark:bg-slate-800 blur-xl opacity-50"></div>
                        </div>
                        <p class="text-slate-500 dark:text-slate-400 text-sm font-medium">Loading tickets...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chat View -->
        <div id="chat-view" class="hidden flex-1 flex flex-col overflow-hidden">
            <!-- Messages Container -->
            <div id="messages-container" class="flex-1 overflow-y-auto p-4 space-y-4 bg-slate-50 dark:bg-slate-950">
                <!-- Messages will be inserted here -->
            </div>

            <!-- Quick Reply -->
            <div class="p-4 bg-white dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800">
                <form id="quick-reply-form" class="space-y-3" novalidate>
                    <div class="flex gap-2">
                        <input type="text" id="quick-reply-input" placeholder="Type a message..." 
                               class="flex-1 px-4 py-3 rounded-full border border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-all">
                        <button type="button" id="attach-file-btn" class="bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 p-3 rounded-full transition-all">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                            </svg>
                        </button>
                        <button type="submit" class="bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-blue-600 hover:to-purple-700 text-white p-3 rounded-full transition-all hover:shadow-lg">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                            </svg>
                        </button>
                    </div>
                    <input type="file" id="quick-reply-file" class="hidden">
                    <div id="file-preview" class="hidden px-3 py-2 bg-emerald-50 dark:bg-blue-900/20 border border-emerald-200 dark:border-blue-800 rounded-lg flex items-center justify-between">
                        <div class="flex items-center gap-2 text-sm">
                            <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                            </svg>
                            <span id="file-name" class="text-slate-700 dark:text-slate-300 font-medium truncate"></span>
                        </div>
                        <button type="button" id="remove-file-btn" class="text-red-500 hover:text-red-700 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Create Ticket View -->
        <div id="create-ticket-view" class="hidden flex-1 flex flex-col overflow-hidden">
            <div class="flex-1 overflow-y-auto p-6 space-y-4">
                <form id="create-ticket-form" class="space-y-4" novalidate>
                    <!-- Smart Create with AI Button -->
                        <button type="button" id="smart-create-btn" 
                            class="w-full flex items-center justify-center gap-2 px-4 py-3 bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-violet-600 hover:to-purple-700 text-white rounded-xl font-semibold transition-all hover:shadow-lg hover:shadow-emerald-500/30 group">
                        <svg class="w-5 h-5 group-hover:animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        ✨ Smart Create with AI
                    </button>
                    
                    <!-- AI Paragraph Overlay (hidden by default) -->
                    <div id="ai-paragraph-overlay" class="hidden">
                        <div class="relative">
                            <textarea id="ai-paragraph-input" rows="5"
                                      class="w-full px-4 py-3 rounded-xl border-2 border-emerald-300 dark:border-emerald-700 dark:bg-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all resize-none"
                                      placeholder="Describe your issue in your own words... e.g. 'My payment failed when I tried to upgrade. I was charged $50 but the plan didn't change. Please help urgently.'"></textarea>
                            <div class="absolute top-2 right-2 text-xs text-emerald-400 font-medium">AI-Powered</div>
                        </div>
                        <div class="flex gap-2 mt-2">
                            <button type="button" id="ai-cancel-btn"
                                    class="flex-1 px-3 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-lg text-sm font-medium transition-all">
                                Cancel
                            </button>
                            <button type="button" id="ai-generate-btn"
                                    class="flex-1 px-3 py-2 bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-violet-600 hover:to-purple-700 text-white rounded-lg text-sm font-semibold transition-all shadow-lg shadow-emerald-500/20 flex items-center justify-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                                Generate Ticket
                            </button>
                        </div>
                        <div id="ai-generate-error" class="hidden mt-2 p-2 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-300 dark:border-red-700 text-sm text-red-700 dark:text-red-400"></div>
                    </div>
                    
                    <div class="relative flex items-center">
                        <div class="flex-grow border-t border-slate-200 dark:border-slate-700"></div>
                        <span class="flex-shrink mx-3 text-xs text-slate-400 dark:text-slate-500">or fill manually</span>
                        <div class="flex-grow border-t border-slate-200 dark:border-slate-700"></div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Subject</label>
                        <input type="text" name="subject" 
                               class="w-full px-4 py-2.5 rounded-lg border border-slate-300 dark:border-slate-700 dark:bg-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-all"
                               placeholder="Brief description of your issue">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Category</label>
                        <select name="category" id="category-select"
                                class="w-full px-4 py-2.5 rounded-lg border border-slate-300 dark:border-slate-700 dark:bg-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-all">
                            <option value="">Loading categories...</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Priority</label>
                        <select name="priority"
                                class="w-full px-4 py-2.5 rounded-lg border border-slate-300 dark:border-slate-700 dark:bg-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-all">
                            <option value="LOW">Low</option>
                            <option value="MEDIUM" selected>Medium</option>
                            <option value="HIGH">High</option>
                            <option value="URGENT">Urgent</option>
                        </select>
                    </div>                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Message</label>
                        <textarea name="message" rows="6"
                                  class="w-full px-4 py-2.5 rounded-lg border border-slate-300 dark:border-slate-700 dark:bg-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-all resize-none"
                                  placeholder="Describe your issue in detail..."></textarea>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Attachment (optional)</label>
                        <input type="file" name="attachment" 
                               class="block w-full text-sm text-slate-500 dark:text-slate-400
                                      file:mr-4 file:py-2.5 file:px-4 file:rounded-lg file:border-0
                                      file:text-sm file:font-semibold file:bg-gradient-to-r file:from-blue-50 file:to-purple-50
                                      file:text-blue-700 hover:file:from-blue-100 hover:file:to-purple-100
                                      file:transition-all file:cursor-pointer
                                      dark:file:from-blue-900/50 dark:file:to-purple-900/50 dark:file:text-blue-300">
                    </div>
                    
                    <div class="flex gap-3 pt-4">
                        <button type="button" id="cancel-create-btn" 
                                class="flex-1 px-4 py-2.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-lg font-medium transition-all">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="flex-1 px-4 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white rounded-lg font-medium transition-all shadow-lg shadow-emerald-500/30">
                            <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                            </svg>
                            Create Ticket
                        </button>
                    </div>
                </form>
            </div>
        </div>
        </div>
        <!-- Messagerie tab panel — built directly in JS, same structure as Tickets -->
        <div id="messagerie-tab-panel" style="display:none;flex:1;flex-direction:column;overflow:hidden;">

            <!-- Conversation List View -->
            <div id="messagerie-list-view" style="flex:1;display:flex;flex-direction:column;overflow:hidden;">
                <div style="padding:1rem;border-bottom:1px solid rgba(148,163,184,0.2);">
                    <button id="messagerie-new-btn" style="display:block;width:100%;background:linear-gradient(to right,#10b981,#0d9488);color:white;text-align:center;padding:0.75rem 1rem;border-radius:0.75rem;font-weight:600;border:none;cursor:pointer;">
                        <svg style="width:1.25rem;height:1.25rem;display:inline;margin-right:0.5rem;vertical-align:-4px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                        Your Conversations
                    </button>
                </div>
                <div id="messagerie-list-container" style="flex:1;overflow-y:auto;">
                    <div style="display:flex;align-items:center;justify-content:center;height:100%;">
                        <div style="display:flex;flex-direction:column;align-items:center;gap:0.75rem;">
                            <div style="width:3rem;height:3rem;border-radius:50%;border:4px solid #d1fae5;border-top-color:#10b981;animation:spin 1s linear infinite;"></div>
                            <p style="color:#94a3b8;font-size:0.875rem;font-weight:500;">Loading conversations...</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Chat View (hidden until a conversation is opened) -->
            <div id="messagerie-chat-view" style="display:none;flex:1;flex-direction:column;overflow:hidden;">

                <!-- Chat header (same as ticket chat) -->
                <div id="messagerie-chat-header" style="padding:0.75rem 1rem;border-bottom:1px solid rgba(148,163,184,0.2);display:flex;align-items:center;gap:0.75rem;">
                    <div id="messagerie-back-btn" style="cursor:pointer;padding:0.25rem;border-radius:0.5rem;display:flex;align-items:center;" title="Back">
                        <svg style="width:1.5rem;height:1.5rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <p id="messagerie-chat-other-name" style="font-weight:600;font-size:0.875rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"></p>
                        <p id="messagerie-chat-contract-title" style="font-size:0.75rem;color:#94a3b8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"></p>
                    </div>
                    <!-- Voice / Video call buttons -->
                    <div id="messagerie-call-btns" style="display:flex;gap:0.375rem;flex-shrink:0;align-items:center;">
                        <a id="messagerie-voice-call-btn" href="#" target="_blank" rel="noopener"
                           title="Voice Call"
                           style="display:flex;align-items:center;justify-content:center;width:2rem;height:2rem;border-radius:0.5rem;background:linear-gradient(135deg,#10b981,#059669);color:white;text-decoration:none;font-size:1rem;box-shadow:0 2px 6px rgba(16,185,129,0.4);transition:opacity 0.15s;">
                            📞
                        </a>
                        <a id="messagerie-video-call-btn" href="#" target="_blank" rel="noopener"
                           title="Video Call"
                           style="display:flex;align-items:center;justify-content:center;width:2rem;height:2rem;border-radius:0.5rem;background:linear-gradient(135deg,#3b82f6,#2563eb);color:white;text-decoration:none;font-size:1rem;box-shadow:0 2px 6px rgba(59,130,246,0.4);transition:opacity 0.15s;">
                            🎥
                        </a>
                    </div>
                </div>

                <!-- Closed banner -->
                <div id="messagerie-closed-banner" style="display:none;padding:0.5rem 1rem;background:#fefce8;border-top:1px solid #fde68a;text-align:center;font-size:0.875rem;color:#92400e;">
                    Conversation closed. No new messages allowed.
                </div>

                <!-- Messages area — same as #messages-container in Tickets -->
                <div id="messagerie-messages-container" style="flex:1;overflow-y:auto;padding:1rem;padding-right:1.5rem;display:flex;flex-direction:column;gap:1rem;background:#f8fafc;box-sizing:border-box;min-height:0;">
                </div>

                <!-- Send area — same layout as ticket quick-reply -->
                <div id="messagerie-send-area" style="padding:1rem;background:white;border-top:1px solid rgba(148,163,184,0.2);">
                    <form id="messagerie-send-form" novalidate>
                        <div style="display:flex;gap:0.5rem;">
                            <input type="text" id="messagerie-message-input" placeholder="Type a message..." maxlength="2000"
                                   style="flex:1;padding:0.75rem 1rem;border-radius:9999px;border:1px solid #cbd5e1;outline:none;font-size:0.875rem;background:transparent;color:inherit;">
                            <button type="submit" id="messagerie-send-btn"
                                    style="background:linear-gradient(135deg,#10b981,#0d9488);color:white;padding:0.75rem;border-radius:9999px;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;">
                                <svg style="width:1.5rem;height:1.5rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                </svg>
                            </button>
                        </div>
                    </form>
                    <div id="messagerie-delete-wrap" style="display:none;margin-top:0.75rem;padding-top:0.75rem;border-top:1px solid rgba(148,163,184,0.2);">
                        <button type="button" id="messagerie-delete-conversation" style="font-size:0.875rem;color:#dc2626;background:none;border:none;cursor:pointer;text-decoration:underline;">
                            Delete conversation from my view
                        </button>
                    </div>
                </div>
            </div>

        </div>
    `;
    document.body.appendChild(messenger);

    // Tab switching
    document.getElementById('tab-tickets').addEventListener('click', function() {
        switchToTicketsTab();
    });
    document.getElementById('tab-messagerie').addEventListener('click', function() {
        switchToMessagerieTab();
    });

    // Event listeners
    messengerButton.addEventListener('click', function() {
        openMessenger();
        loadTickets();
    });

    messenger.querySelector('#close-messenger').addEventListener('click', closeMessenger);
    messenger.querySelector('#back-btn').addEventListener('click', showTicketList);
    
    // New Ticket button
    messenger.querySelector('#new-ticket-btn').addEventListener('click', showCreateView);
    
    // Cancel create button
    messenger.querySelector('#cancel-create-btn').addEventListener('click', showTicketList);
    
    // Smart Create with AI button
    messenger.querySelector('#smart-create-btn').addEventListener('click', function() {
        const overlay = document.getElementById('ai-paragraph-overlay');
        const smartBtn = document.getElementById('smart-create-btn');
        overlay.classList.toggle('hidden');
        if (!overlay.classList.contains('hidden')) {
            smartBtn.classList.add('ring-2', 'ring-emerald-400', 'ring-offset-2', 'dark:ring-offset-slate-900');
            document.getElementById('ai-paragraph-input').focus();
        } else {
            smartBtn.classList.remove('ring-2', 'ring-emerald-400', 'ring-offset-2', 'dark:ring-offset-slate-900');
        }
    });
    
    // AI Cancel button
    messenger.querySelector('#ai-cancel-btn').addEventListener('click', function() {
        document.getElementById('ai-paragraph-overlay').classList.add('hidden');
        document.getElementById('smart-create-btn').classList.remove('ring-2', 'ring-emerald-400', 'ring-offset-2', 'dark:ring-offset-slate-900');
        document.getElementById('ai-paragraph-input').value = '';
        document.getElementById('ai-generate-error').classList.add('hidden');
    });
    
    // AI Generate button
    messenger.querySelector('#ai-generate-btn').addEventListener('click', async function() {
        const paragraphInput = document.getElementById('ai-paragraph-input');
        const generateBtn = document.getElementById('ai-generate-btn');
        const errorBox = document.getElementById('ai-generate-error');
        const paragraph = paragraphInput.value.trim();
        
        errorBox.classList.add('hidden');
        
        if (!paragraph || paragraph.length < 10) {
            errorBox.textContent = 'Please describe your issue in more detail (at least 10 characters).';
            errorBox.classList.remove('hidden');
            paragraphInput.focus();
            return;
        }
        
        // Show loading state
        const originalHTML = generateBtn.innerHTML;
        generateBtn.disabled = true;
        generateBtn.innerHTML = `
            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Analyzing...
        `;
        paragraphInput.disabled = true;
        
        try {
            const response = await fetch('/ticket/parse-paragraph', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ paragraph: paragraph })
            });
            
            const data = await response.json();
            
            if (!response.ok || !data.success) {
                throw new Error(data.error || 'AI could not process your description.');
            }
            
            // Auto-fill the form fields
            const form = document.getElementById('create-ticket-form');
            
            // Subject
            const subjectField = form.querySelector('[name="subject"]');
            if (subjectField && data.subject) {
                subjectField.value = data.subject;
                subjectField.classList.add('ring-2', 'ring-emerald-300', 'dark:ring-emerald-700');
                setTimeout(() => subjectField.classList.remove('ring-2', 'ring-emerald-300', 'dark:ring-emerald-700'), 2000);
            }
            
            // Category
            const categoryField = form.querySelector('[name="category"]');
            if (categoryField && data.category_id) {
                // Find and select the matching option
                const options = categoryField.options;
                for (let i = 0; i < options.length; i++) {
                    if (parseInt(options[i].value) === data.category_id) {
                        categoryField.selectedIndex = i;
                        break;
                    }
                }
                categoryField.classList.add('ring-2', 'ring-emerald-300', 'dark:ring-emerald-700');
                setTimeout(() => categoryField.classList.remove('ring-2', 'ring-emerald-300', 'dark:ring-emerald-700'), 2000);
            }
            
            // Priority
            const priorityField = form.querySelector('[name="priority"]');
            if (priorityField && data.priority) {
                const options = priorityField.options;
                for (let i = 0; i < options.length; i++) {
                    if (options[i].value === data.priority) {
                        priorityField.selectedIndex = i;
                        break;
                    }
                }
                priorityField.classList.add('ring-2', 'ring-emerald-300', 'dark:ring-emerald-700');
                setTimeout(() => priorityField.classList.remove('ring-2', 'ring-emerald-300', 'dark:ring-emerald-700'), 2000);
            }
            
            // Message
            const messageField = form.querySelector('[name="message"]');
            if (messageField && data.message) {
                messageField.value = data.message;
                messageField.classList.add('ring-2', 'ring-emerald-300', 'dark:ring-emerald-700');
                setTimeout(() => messageField.classList.remove('ring-2', 'ring-emerald-300', 'dark:ring-emerald-700'), 2000);
            }
            
            // Hide the overlay with success animation
            document.getElementById('ai-paragraph-overlay').classList.add('hidden');
            document.getElementById('smart-create-btn').classList.remove('ring-2', 'ring-emerald-400', 'ring-offset-2', 'dark:ring-offset-slate-900');
            paragraphInput.value = '';
            
            // Show a brief success toast
            showAiSuccessToast();
            
        } catch (error) {
            console.error('AI parse error:', error);
            errorBox.textContent = error.message || 'Failed to generate ticket. Please try again.';
            errorBox.classList.remove('hidden');
        } finally {
            generateBtn.disabled = false;
            generateBtn.innerHTML = originalHTML;
            paragraphInput.disabled = false;
        }
    });
    
    // Create ticket form submission
    messenger.querySelector('#create-ticket-form').addEventListener('submit', handleCreateTicket);

    // Live clear errors on input for ticket popup form
    messenger.querySelectorAll('#create-ticket-form input, #create-ticket-form select, #create-ticket-form textarea').forEach(function(field) {
        var ev = (field.tagName === 'SELECT' || field.type === 'file') ? 'change' : 'input';
        field.addEventListener(ev, function() {
            field.classList.remove('border-red-500');
            field.classList.add('border-slate-300', 'dark:border-slate-700');
            var sib = field.nextElementSibling;
            while (sib && sib.classList.contains('js-popup-error')) {
                var next = sib.nextElementSibling;
                sib.remove();
                sib = next;
            }
        });
    });

    // Close messenger when clicking outside
    document.addEventListener('click', function(e) {
        const isOpen = !messenger.classList.contains('translate-x-full') && !messenger.classList.contains('md:translate-x-[calc(100%+24px)]');
        if (!messenger.contains(e.target) && !messengerButton.contains(e.target) && isOpen) {
            closeMessenger();
        }
    });

    // Load initial unread count
    updateUnreadCount();
}

function injectTicketPopupStyles() {
    if (document.getElementById('ticket-popup-style-fixes')) {
        return;
    }

    const style = document.createElement('style');
    style.id = 'ticket-popup-style-fixes';
    style.textContent = `
        @keyframes spin { to { transform: rotate(360deg); } }

        @keyframes ticket-popup-bounce-slow {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-6px); }
        }

        .animate-bounce-slow {
            animation: ticket-popup-bounce-slow 2.2s ease-in-out infinite;
        }

        #messenger-popup {
            backdrop-filter: saturate(120%);
            border: 1px solid rgba(148, 163, 184, 0.25);
        }

        #messenger-popup #support-popup-tabs {
            display: flex !important;
            flex-shrink: 0;
            min-height: 48px;
        }
        #messenger-popup #tab-tickets,
        #messenger-popup #tab-messagerie {
            display: inline-block !important;
            visibility: visible !important;
        }

        #messenger-popup #messages-container {
            padding: 1rem;
            padding-right: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            background-color: #f8fafc !important;
            min-height: 0;
            box-sizing: border-box;
        }
        .dark #messenger-popup #messages-container {
            background-color: #020617 !important;
        }
        #messenger-popup #ticket-list-container,
        #messenger-popup #messages-container,
        #messenger-popup #messagerie-messages-container {
            scrollbar-width: thin;
            scrollbar-color: rgba(16, 185, 129, 0.45) transparent;
            scrollbar-gutter: stable;
        }

        #messenger-popup #ticket-list-container::-webkit-scrollbar,
        #messenger-popup #messages-container::-webkit-scrollbar,
        #messenger-popup #messagerie-messages-container::-webkit-scrollbar {
            width: 8px;
        }

        #messenger-popup #ticket-list-container::-webkit-scrollbar-thumb,
        #messenger-popup #messages-container::-webkit-scrollbar-thumb,
        #messenger-popup #messagerie-messages-container::-webkit-scrollbar-thumb {
            background: rgba(16, 185, 129, 0.45);
            border-radius: 999px;
        }

        #messenger-popup #ticket-list-container::-webkit-scrollbar-thumb:hover,
        #messenger-popup #messages-container::-webkit-scrollbar-thumb:hover,
        #messenger-popup #messagerie-messages-container::-webkit-scrollbar-thumb:hover {
            background: rgba(13, 148, 136, 0.65);
        }

        /* ========== MESSAGERIE: same look as Tickets (plain CSS, no Tailwind dependency) ========== */
        #messagerie-messages-container.messagerie-messages-area,
        #messenger-popup #messagerie-messages-container,
        #messenger-popup .messagerie-messages-area {
            padding: 1rem !important;
            padding-right: 1.5rem !important;
            display: flex !important;
            flex-direction: column !important;
            gap: 1rem !important;
            background-color: #f8fafc !important;
            min-height: 0 !important;
            box-sizing: border-box !important;
            overflow-y: auto !important;
        }
        html.dark #messagerie-messages-container,
        html.dark #messenger-popup #messagerie-messages-container,
        body.dark #messenger-popup #messagerie-messages-container,
        .dark #messenger-popup #messagerie-messages-container {
            background-color: #020617 !important;
        }
        #messagerie-messages-container .msg-bubble,
        #messenger-popup #messagerie-messages-container .msg-bubble {
            display: flex;
            align-items: flex-end;
            gap: 0.5rem;
            margin-bottom: 0;
            animation: ticket-popup-fade-in 180ms ease;
        }
        #messagerie-messages-container .msg-bubble--right,
        #messenger-popup #messagerie-messages-container .msg-bubble--right {
            justify-content: flex-end;
        }
        #messagerie-messages-container .msg-avatar,
        #messenger-popup #messagerie-messages-container .msg-avatar {
            width: 32px;
            height: 32px;
            min-width: 32px;
            min-height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #10b981 0%, #0d9488 100%);
            color: white;
            font-size: 0.75rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -2px rgba(0,0,0,0.1);
        }
        #messagerie-messages-container .msg-bubble-inner,
        #messenger-popup #messagerie-messages-container .msg-bubble-inner {
            max-width: 75%;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }
        #messagerie-messages-container .msg-bubble--left .msg-bubble-inner,
        #messenger-popup #messagerie-messages-container .msg-bubble--left .msg-bubble-inner {
            align-items: flex-start;
        }
        #messagerie-messages-container .msg-bubble-content,
        #messenger-popup #messagerie-messages-container .msg-bubble-content {
            padding: 0.625rem 1rem;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -2px rgba(0,0,0,0.1);
        }
        #messagerie-messages-container .msg-bubble--right .msg-bubble-content,
        #messenger-popup #messagerie-messages-container .msg-bubble--right .msg-bubble-content {
            background: linear-gradient(135deg, #10b981 0%, #0d9488 100%);
            color: white;
            border-bottom-right-radius: 0.25rem;
        }
        #messagerie-messages-container .msg-bubble--left .msg-bubble-content,
        #messenger-popup #messagerie-messages-container .msg-bubble--left .msg-bubble-content {
            background: #ffffff;
            color: #1e293b;
            border-bottom-left-radius: 0.25rem;
        }
        html.dark #messagerie-messages-container .msg-bubble--left .msg-bubble-content,
        html.dark #messenger-popup #messagerie-messages-container .msg-bubble--left .msg-bubble-content,
        body.dark #messenger-popup #messagerie-messages-container .msg-bubble--left .msg-bubble-content,
        .dark #messenger-popup #messagerie-messages-container .msg-bubble--left .msg-bubble-content {
            background: #1e293b;
            color: #e2e8f0;
        }
        #messagerie-messages-container .msg-sender-name,
        #messenger-popup #messagerie-messages-container .msg-sender-name {
            font-size: 0.75rem;
            font-weight: 600;
            color: #059669;
            margin-bottom: 0.25rem;
        }
        html.dark #messagerie-messages-container .msg-sender-name,
        html.dark #messenger-popup #messagerie-messages-container .msg-sender-name,
        body.dark #messenger-popup #messagerie-messages-container .msg-sender-name,
        .dark #messenger-popup #messagerie-messages-container .msg-sender-name {
            color: #34d399;
        }
        #messagerie-messages-container .msg-bubble-text,
        #messenger-popup #messagerie-messages-container .msg-bubble-text {
            font-size: 0.875rem;
            white-space: pre-wrap;
            word-break: break-word;
        }
        #messagerie-messages-container .msg-time,
        #messenger-popup #messagerie-messages-container .msg-time {
            font-size: 0.75rem;
            color: #94a3b8;
            margin-top: 0.25rem;
        }
        #messagerie-messages-container .msg-bubble--right .msg-time,
        #messenger-popup #messagerie-messages-container .msg-bubble--right .msg-time {
            margin-right: 0.5rem;
        }
        #messagerie-messages-container .msg-bubble--left .msg-time,
        #messenger-popup #messagerie-messages-container .msg-bubble--left .msg-time {
            margin-left: 0.5rem;
        }
        #messagerie-messages-container .message-bubble-left,
        #messagerie-messages-container .message-bubble-right,
        #messenger-popup #messagerie-messages-container .message-bubble-left,
        #messenger-popup #messagerie-messages-container .message-bubble-right {
            animation: ticket-popup-fade-in 180ms ease;
        }

        #messenger-popup .ticket-item {
            transition: background-color 180ms ease, transform 180ms ease;
        }

        #messenger-popup .ticket-item:hover {
            transform: translateY(-1px);
        }

        #messenger-popup .message-bubble-left,
        #messenger-popup .message-bubble-right {
            animation: ticket-popup-fade-in 180ms ease;
        }

        @keyframes ticket-popup-fade-in {
            from { opacity: 0; transform: translateY(4px); }
            to { opacity: 1; transform: translateY(0); }
        }
    `;

    document.head.appendChild(style);
}

function openMessenger() {
    const messenger = document.getElementById('messenger-popup');
    messenger.classList.remove('translate-x-full', 'md:translate-x-[calc(100%+24px)]');
    messenger.classList.add('translate-x-0');
}

function closeMessenger() {
    const messenger = document.getElementById('messenger-popup');
    messenger.classList.remove('translate-x-0');
    messenger.classList.add('translate-x-full', 'md:translate-x-[calc(100%+24px)]');
    stopMessageriePolling();
    switchToTicketsTab();
    showTicketList();
}

function showTicketList() {
    const ticketPanel = document.getElementById('ticket-tab-panel');
    if (ticketPanel && !ticketPanel.classList.contains('hidden')) {
        document.getElementById('ticket-list-view').classList.remove('hidden');
        document.getElementById('chat-view').classList.add('hidden');
        document.getElementById('create-ticket-view').classList.add('hidden');
        document.getElementById('back-btn').classList.add('hidden');
        document.getElementById('messenger-title').textContent = 'Support';
        document.getElementById('messenger-subtitle').textContent = 'Your tickets';
        loadTickets();
    }
}

// --- Tabs: Tickets / Messagerie ---
function switchToTicketsTab() {
    var tTickets = document.getElementById('tab-tickets');
    var tMsg = document.getElementById('tab-messagerie');
    tTickets.style.color = '#10b981'; tTickets.style.borderBottomColor = '#10b981'; tTickets.style.borderBottomWidth = '2px'; tTickets.style.background = '';
    tMsg.style.color = '#94a3b8'; tMsg.style.borderBottomColor = 'transparent'; tMsg.style.borderBottomWidth = '2px';
    document.getElementById('ticket-tab-panel').style.display = 'flex';
    document.getElementById('messagerie-tab-panel').style.display = 'none';
    showTicketList();
}

function switchToMessagerieTab() {
    var tTickets = document.getElementById('tab-tickets');
    var tMsg = document.getElementById('tab-messagerie');
    tMsg.style.color = '#10b981'; tMsg.style.borderBottomColor = '#10b981'; tMsg.style.borderBottomWidth = '2px';
    tTickets.style.color = '#94a3b8'; tTickets.style.borderBottomColor = 'transparent'; tTickets.style.borderBottomWidth = '2px';
    document.getElementById('ticket-tab-panel').style.display = 'none';
    document.getElementById('messagerie-tab-panel').style.display = 'flex';
    ensureMessagerieTabLoaded();
}

var messagerieTabLoaded = false;
var messageriePollingInterval = null;
var currentMessagerieConversationId = null;
var messagerieLastMsgId = null;

function ensureMessagerieTabLoaded() {
    if (!messagerieTabLoaded) {
        messagerieTabLoaded = true;
        // Bind events once (panel HTML is already in the page)
        var backBtn = document.getElementById('messagerie-back-btn');
        if (backBtn) backBtn.addEventListener('click', showMessagerieConversationList);
        var form = document.getElementById('messagerie-send-form');
        if (form) form.addEventListener('submit', handleMessagerieSend);
        var deleteBtn = document.getElementById('messagerie-delete-conversation');
        if (deleteBtn) deleteBtn.addEventListener('click', handleMessagerieDelete);
        // Dark-mode background
        var isDark = document.documentElement.classList.contains('dark') || document.body.classList.contains('dark');
        var mc = document.getElementById('messagerie-messages-container');
        if (mc && isDark) mc.style.background = '#020617';
        var sa = document.getElementById('messagerie-send-area');
        if (sa && isDark) sa.style.background = '#0f172a';
        var mi = document.getElementById('messagerie-message-input');
        if (mi && isDark) { mi.style.background = '#1e293b'; mi.style.color = '#f1f5f9'; mi.style.borderColor = '#334155'; }
    }
    loadMessagerieConversations();
}

function loadMessagerieConversations() {
    var container = document.getElementById('messagerie-list-container');
    if (!container) return;
    container.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;">' +
        '<div style="display:flex;flex-direction:column;align-items:center;gap:0.75rem;">' +
        '<div style="width:2.5rem;height:2.5rem;border-radius:50%;border:4px solid #d1fae5;border-top-color:#10b981;animation:spin 1s linear infinite;"></div>' +
        '<p style="color:#94a3b8;font-size:0.875rem;">Loading...</p></div></div>';
    fetch('/messagerie/conversations', {
        method: 'GET',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin'
    })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) throw new Error(data.error || 'Failed');
            renderMessagerieConversations(data.conversations || []);
        })
        .catch(function(err) {
            container.innerHTML = '<div style="padding:1.5rem;text-align:center;color:#94a3b8;font-size:0.875rem;">Could not load conversations.</div>';
        });
}

function renderMessagerieConversations(conversations) {
    var container = document.getElementById('messagerie-list-container');
    if (!container) return;

    if (!conversations.length) {
        container.innerHTML =
            '<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;text-align:center;padding:1.5rem;">' +
            '<div style="width:6rem;height:6rem;border-radius:50%;background:linear-gradient(135deg,#dbeafe,#ede9fe);display:flex;align-items:center;justify-content:center;margin-bottom:1rem;">' +
            '<svg style="width:3rem;height:3rem;color:#34d399;" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg></div>' +
            '<p style="font-weight:600;margin-bottom:0.5rem;">No conversations yet</p>' +
            '<p style="font-size:0.875rem;color:#94a3b8;">Sign a contract to start chatting with the other party</p></div>';
        return;
    }

    var seen = {};
    var list = conversations.filter(function(c) { if (seen[c.id]) return false; seen[c.id] = true; return true; });

    var html = '<div style="border-top:1px solid rgba(148,163,184,0.15);">';
    list.forEach(function(c) {
        var timeStr = c.lastMessageAt ? formatDate(c.lastMessageAt) : '—';
        var closedBadge = c.isClosed
            ? '<span style="font-size:0.7rem;padding:2px 8px;border-radius:9999px;background:#fef3c7;color:#92400e;font-weight:600;">Closed</span>'
            : '';
        // Same structure as .ticket-item
        html += '<div class="messagerie-conv-item" data-conv=\'' + JSON.stringify(c).replace(/'/g, '&apos;') + '\'' +
            ' style="padding:1rem;border-bottom:1px solid rgba(148,163,184,0.15);cursor:pointer;display:flex;align-items:flex-start;gap:0.75rem;transition:background 120ms;"' +
            ' onmouseenter="this.style.background=\'rgba(148,163,184,0.08)\'" onmouseleave="this.style.background=\'\'">' +
            // Avatar
            '<div style="flex-shrink:0;width:3rem;height:3rem;border-radius:50%;background:linear-gradient(135deg,#10b981,#0d9488);display:flex;align-items:center;justify-content:center;color:white;box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);">' +
            '<svg style="width:1.5rem;height:1.5rem;" fill="currentColor" viewBox="0 0 20 20"><path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"/></svg></div>' +
            // Content
            '<div style="flex:1;min-width:0;">' +
            '<div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:0.25rem;">' +
            '<h3 style="font-weight:600;font-size:0.875rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:60%;">' + escapeHtml(c.otherName || 'Other') + '</h3>' +
            '<span style="font-size:0.75rem;color:#94a3b8;flex-shrink:0;margin-left:0.5rem;">' + timeStr + '</span></div>' +
            '<div style="display:flex;align-items:center;gap:0.5rem;font-size:0.75rem;">' +
            '<span style="color:#94a3b8;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + escapeHtml(c.contractTitle || '') + '</span>' +
            (closedBadge ? '<span>·</span>' + closedBadge : '') +
            '</div></div></div>';
    });
    html += '</div>';
    container.innerHTML = html;
    container.querySelectorAll('.messagerie-conv-item').forEach(function(el) {
        el.addEventListener('click', function() {
            var c = JSON.parse(this.getAttribute('data-conv'));
            openMessagerieConversation(c);
        });
    });
}

function openMessagerieConversation(conv) {
    stopMessageriePolling();
    currentMessagerieConversationId = conv.id;
    // Show chat, hide list (using inline styles — no Tailwind required)
    var listView = document.getElementById('messagerie-list-view');
    var chatView = document.getElementById('messagerie-chat-view');
    if (listView) listView.style.display = 'none';
    if (chatView) { chatView.style.display = 'flex'; chatView.style.flex = '1'; chatView.style.flexDirection = 'column'; chatView.style.overflow = 'hidden'; }

    document.getElementById('messagerie-chat-other-name').textContent = conv.otherName || 'Other';
    document.getElementById('messagerie-chat-contract-title').textContent = conv.contractTitle || '';

    // Wire up call buttons: only enabled when contract is NOT closed
    var voiceBtn = document.getElementById('messagerie-voice-call-btn');
    var videoBtn = document.getElementById('messagerie-video-call-btn');
    if (voiceBtn && videoBtn) {
        var callAllowed = (conv.callAllowed === true) || (conv.callAllowed === undefined && !conv.isClosed);
        if (!callAllowed) {
            // Disabled appearance — grey out and remove href so clicks do nothing
            voiceBtn.removeAttribute('href');
            videoBtn.removeAttribute('href');
            voiceBtn.style.opacity = '0.35';
            videoBtn.style.opacity = '0.35';
            voiceBtn.style.cursor = 'not-allowed';
            videoBtn.style.cursor = 'not-allowed';
            voiceBtn.title = 'Call unavailable — contract is closed';
            videoBtn.title = 'Call unavailable — contract is closed';
            voiceBtn.onclick = function (e) { e.preventDefault(); };
            videoBtn.onclick = function (e) { e.preventDefault(); };
        } else {
            var callBase = '/messagerie/call/' + conv.id;
            voiceBtn.href = callBase + '?type=voice';
            videoBtn.href = callBase + '?type=video';
            voiceBtn.style.opacity = '1';
            videoBtn.style.opacity = '1';
            voiceBtn.style.cursor = 'pointer';
            videoBtn.style.cursor = 'pointer';
            voiceBtn.title = 'Voice Call';
            videoBtn.title = 'Video Call';
            voiceBtn.onclick = null;
            videoBtn.onclick = null;
        }
    }

    var closedBanner = document.getElementById('messagerie-closed-banner');
    var sendArea = document.getElementById('messagerie-send-area');
    var input = document.getElementById('messagerie-message-input');
    var sendBtn = document.getElementById('messagerie-send-btn');
    var deleteWrap = document.getElementById('messagerie-delete-wrap');
    if (conv.isClosed) {
        if (closedBanner) closedBanner.style.display = 'block';
        if (input) input.disabled = true;
        if (sendBtn) sendBtn.disabled = true;
        if (deleteWrap && conv.canDelete) deleteWrap.style.display = 'block';
    } else {
        if (closedBanner) closedBanner.style.display = 'none';
        if (input) input.disabled = false;
        if (sendBtn) sendBtn.disabled = false;
        if (deleteWrap) deleteWrap.style.display = 'none';
    }
    loadMessagerieMessages(conv.id);
    if (!conv.isClosed) startMessageriePolling(conv.id);
}

function showMessagerieConversationList() {
    stopMessageriePolling();
    currentMessagerieConversationId = null;
    var chatView = document.getElementById('messagerie-chat-view');
    var listView = document.getElementById('messagerie-list-view');
    if (chatView) chatView.style.display = 'none';
    if (listView) { listView.style.display = 'flex'; listView.style.flex = '1'; listView.style.flexDirection = 'column'; listView.style.overflow = 'hidden'; }
    loadMessagerieConversations();
}

function renderMessagerieBubble(m) {
    // Identical structure to ticket renderMessages() — inline styles guarantee rendering
    var msgId = m.id;
    var content = escapeHtml(m.content || '');
    var time = formatMessageTime(m.createdAt || new Date().toISOString());
    if (m.isMe) {
        return '<div data-msg-id="' + msgId + '" style="display:flex;align-items:flex-end;gap:0.5rem;justify-content:flex-end;">' +
            '<div style="display:flex;flex-direction:column;max-width:75%;align-items:flex-end;">' +
            '<div style="background:linear-gradient(135deg,#10b981,#0d9488);border-radius:1rem;border-bottom-right-radius:0.25rem;padding:0.625rem 1rem;box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);">' +
            '<p style="color:white;font-size:0.875rem;white-space:pre-wrap;word-break:break-word;">' + content + '</p></div>' +
            '<span style="font-size:0.75rem;color:#94a3b8;margin-top:0.25rem;margin-right:0.5rem;">' + time + '</span></div>' +
            '<div style="flex-shrink:0;width:2rem;height:2rem;border-radius:50%;background:linear-gradient(135deg,#10b981,#0d9488);display:flex;align-items:center;justify-content:center;color:white;font-size:0.75rem;font-weight:700;box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);">U</div></div>';
    }
    var name = (m.senderName && m.senderName.trim()) ? escapeHtml(m.senderName.trim()) : 'Other';
    return '<div data-msg-id="' + msgId + '" style="display:flex;align-items:flex-end;gap:0.5rem;">' +
        '<div style="flex-shrink:0;width:2rem;height:2rem;border-radius:50%;background:linear-gradient(135deg,#10b981,#0d9488);display:flex;align-items:center;justify-content:center;color:white;box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);">' +
        '<svg style="width:1rem;height:1rem;" fill="currentColor" viewBox="0 0 20 20"><path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"/></svg></div>' +
        '<div style="display:flex;flex-direction:column;max-width:75%;">' +
        '<div style="background:white;border-radius:1rem;border-bottom-left-radius:0.25rem;padding:0.625rem 1rem;box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);">' +
        '<p style="font-size:0.75rem;font-weight:600;color:#059669;margin-bottom:0.25rem;">' + name + '</p>' +
        '<p style="font-size:0.875rem;color:#1e293b;white-space:pre-wrap;word-break:break-word;">' + content + '</p></div>' +
        '<span style="font-size:0.75rem;color:#94a3b8;margin-top:0.25rem;margin-left:0.5rem;">' + time + '</span></div></div>';
}

function loadMessagerieMessages(convId, afterId) {
    var url = '/messagerie/conversation/' + convId + '/messages';
    if (afterId) url += '?after=' + afterId;
    var container = document.getElementById('messagerie-messages-container');
    if (!container) return;
    if (!afterId) {
        container.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;">' +
            '<div style="display:flex;flex-direction:column;align-items:center;gap:0.75rem;">' +
            '<div style="width:2.5rem;height:2.5rem;border-radius:50%;border:4px solid #d1fae5;border-top-color:#10b981;animation:spin 1s linear infinite;"></div>' +
            '<p style="color:#94a3b8;font-size:0.875rem;">Loading messages...</p></div></div>';
    }
    fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) return;
            var list = data.messages || [];
            if (!afterId) {
                if (list.length === 0) {
                    container.innerHTML =
                        '<div data-messagerie-empty="1" style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;text-align:center;padding:1.5rem;">' +
                        '<div style="width:5rem;height:5rem;border-radius:50%;background:linear-gradient(135deg,#dbeafe,#ede9fe);display:flex;align-items:center;justify-content:center;margin-bottom:1rem;">' +
                        '<svg style="width:2.5rem;height:2.5rem;color:#34d399;" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg></div>' +
                        '<p style="font-weight:600;margin-bottom:0.25rem;">No messages yet</p>' +
                        '<p style="font-size:0.875rem;color:#94a3b8;">Start the conversation</p></div>';
                    messagerieLastMsgId = null;
                    return;
                }
                container.innerHTML = '';
            }
            var lastId = null;
            list.forEach(function(m) {
                if (container.querySelector('[data-msg-id="' + m.id + '"]')) return;
                lastId = m.id;
                container.insertAdjacentHTML('beforeend', renderMessagerieBubble(m));
            });
            if (lastId != null) messagerieLastMsgId = lastId;
            container.scrollTop = container.scrollHeight;
        })
        .catch(function() {
            if (!afterId) container.innerHTML = '<p style="text-align:center;color:#94a3b8;font-size:0.875rem;padding:1rem;">Failed to load messages.</p>';
        });
}

function startMessageriePolling(convId) {
    stopMessageriePolling();
    var container = document.getElementById('messagerie-messages-container');
    if (container) {
        var bubbles = container.querySelectorAll('[data-msg-id]');
        bubbles.forEach(function(b) {
            var id = parseInt(b.getAttribute('data-msg-id'), 10);
            if (!isNaN(id) && (messagerieLastMsgId === null || id > messagerieLastMsgId)) messagerieLastMsgId = id;
        });
    }
    messageriePollingInterval = setInterval(function() {
        if (currentMessagerieConversationId !== convId) return;
        var url = '/messagerie/conversation/' + convId + '/messages';
        if (messagerieLastMsgId) url += '?after=' + messagerieLastMsgId;
        fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success || !data.messages || !data.messages.length) return;
                var container = document.getElementById('messagerie-messages-container');
                if (!container) return;
                var emptyEl = container.querySelector('[data-messagerie-empty="1"]');
                if (emptyEl) emptyEl.remove();
                var scrolled = false;
                data.messages.forEach(function(m) {
                    if (container.querySelector('[data-msg-id="' + m.id + '"]')) return;
                    messagerieLastMsgId = m.id;
                    container.insertAdjacentHTML('beforeend', renderMessagerieBubble(m));
                    scrolled = true;
                });
                if (scrolled) container.scrollTop = container.scrollHeight;
            });
    }, 4000);
}

function stopMessageriePolling() {
    if (messageriePollingInterval) {
        clearInterval(messageriePollingInterval);
        messageriePollingInterval = null;
    }
}

function handleMessagerieSend(e) {
    e.preventDefault();
    var convId = currentMessagerieConversationId;
    if (!convId) return;
    var input = document.getElementById('messagerie-message-input');
    var content = (input && input.value) ? input.value.trim() : '';
    if (!content) return;
    var csrf = document.body.dataset.csrfMsgMessage || '';
    var formData = new FormData();
    formData.append('content', content);
    formData.append('_token', csrf);
    var sendBtn = document.getElementById('messagerie-send-btn');
    if (sendBtn) { sendBtn.disabled = true; }
    fetch('/messagerie/conversation/' + convId + '/messages', {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (sendBtn) sendBtn.disabled = false;
            if (data.success && data.message) {
                if (input) input.value = '';
                var container = document.getElementById('messagerie-messages-container');
                if (container) {
                    var emptyEl = container.querySelector('[data-messagerie-empty]');
                    if (emptyEl) emptyEl.remove();
                    var msg = { id: data.message.id, content: data.message.content, isMe: true, createdAt: data.message.createdAt || new Date().toISOString() };
                    if (!container.querySelector('[data-msg-id="' + msg.id + '"]')) {
                        container.insertAdjacentHTML('beforeend', renderMessagerieBubble(msg));
                        messagerieLastMsgId = msg.id;
                    }
                    container.scrollTop = container.scrollHeight;
                }
            } else if (data.error) {
                alert(data.error);
            }
        })
        .catch(function() {
            if (sendBtn) sendBtn.disabled = false;
            alert('Failed to send message.');
        });
}

function handleMessagerieDelete() {
    var convId = currentMessagerieConversationId;
    if (!convId || !confirm('Remove this conversation from your list? (The other party will keep their copy.)')) return;
    var csrf = document.body.dataset.csrfMsgDelete || '';
    var formData = new FormData();
    formData.append('_token', csrf);
    fetch('/messagerie/conversation/' + convId + '/delete', {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                showMessagerieConversationList();
            } else {
                alert(data.error || 'Could not delete.');
            }
        })
        .catch(function() { alert('Request failed.'); });
}

function showChatView(ticket) {
    document.getElementById('ticket-list-view').classList.add('hidden');
    document.getElementById('chat-view').classList.remove('hidden');
    document.getElementById('create-ticket-view').classList.add('hidden');
    document.getElementById('back-btn').classList.remove('hidden');
    document.getElementById('messenger-title').textContent = `#${ticket.id}`;
    document.getElementById('messenger-subtitle').textContent = ticket.subject;
}

function showCreateView() {
    document.getElementById('ticket-list-view').classList.add('hidden');
    document.getElementById('chat-view').classList.add('hidden');
    document.getElementById('create-ticket-view').classList.remove('hidden');
    document.getElementById('back-btn').classList.remove('hidden');
    document.getElementById('messenger-title').textContent = 'New Ticket';
    document.getElementById('messenger-subtitle').textContent = 'Create a support ticket';
    
    // Load categories
    loadCategories();
    
    // Reset form
    document.getElementById('create-ticket-form').reset();
}

function loadTickets() {
    const container = document.getElementById('ticket-list-container');
    
    // Show loading spinner
    container.innerHTML = `
        <div class="flex items-center justify-center h-full">
            <div class="flex flex-col items-center gap-3">
                <div class="relative">
                    <div class="animate-spin rounded-full h-12 w-12 border-4 border-emerald-200 border-t-emerald-600"></div>
                    <div class="absolute inset-0 rounded-full bg-emerald-50 blur-xl opacity-50"></div>
                </div>
                <p class="text-slate-500 text-sm font-medium">Loading tickets...</p>
            </div>
        </div>
    `;

    // Fetch tickets via AJAX
    fetch('/ticket/list', {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Failed to load tickets');
        }
        return response.json();
    })
    .then(data => {
        renderTickets(data.tickets);
        // Update unread count badge
        if (data.totalUnread !== undefined) {
            const badge = document.getElementById('ticket-unread-badge');
            if (badge) {
                if (data.totalUnread > 0) {
                    badge.textContent = data.totalUnread > 99 ? '99+' : data.totalUnread;
                    badge.classList.remove('hidden');
                } else {
                    badge.classList.add('hidden');
                }
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        container.innerHTML = `
            <div class="flex flex-col items-center justify-center h-full text-center p-6">
                <svg class="w-16 h-16 text-red-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="font-semibold text-slate-800 dark:text-slate-200 mb-2">Oops! Something went wrong</p>
                <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">${error.message}</p>
                <button onclick="loadTickets()" class="bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-blue-600 hover:to-purple-700 text-white px-6 py-2 rounded-lg font-medium transition-all">
                    Try Again
                </button>
            </div>
        `;
    });
}

function renderTickets(tickets) {
    const container = document.getElementById('ticket-list-container');
    
    if (!tickets || tickets.length === 0) {
        container.innerHTML = `
            <div class="flex flex-col items-center justify-center h-full text-center p-6">
                <div class="w-24 h-24 bg-gradient-to-br from-blue-100 to-purple-100 dark:from-blue-900/20 dark:to-purple-900/20 rounded-full flex items-center justify-center mb-4">
                    <svg class="w-12 h-12 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                    </svg>
                </div>
                <p class="font-semibold text-slate-800 dark:text-slate-200 mb-2">No conversations yet</p>
                <p class="text-sm text-slate-500 dark:text-slate-400">Start a conversation by creating your first ticket</p>
            </div>
        `;
        return;
    }

    let html = '<div class="divide-y divide-slate-100 dark:divide-slate-800">';
    
    tickets.forEach(ticket => {
        const statusColors = {
            'OPEN': 'from-blue-500 to-blue-600',
            'IN_PROGRESS': 'from-amber-500 to-orange-600',
            'WAITING_USER': 'from-purple-500 to-pink-600',
            'CLOSED': 'from-slate-400 to-slate-500'
        };

        const statusIcons = {
            'OPEN': '<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><circle cx="10" cy="10" r="8"/></svg>',
            'IN_PROGRESS': '<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z"/></svg>',
            'WAITING_USER': '<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"/></svg>',
            'CLOSED': '<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/></svg>',
        };

        const statusGradient = statusColors[ticket.status] || 'from-slate-400 to-slate-500';
        const statusIcon = statusIcons[ticket.status] || statusIcons['OPEN'];
        
        const timeAgo = formatDate(ticket.lastMessageAt || ticket.createdAt);
        const isUnread = ticket.unreadCount > 0;

        html += `
            <div class="ticket-item p-4 hover:bg-slate-50 dark:hover:bg-slate-800/50 cursor-pointer transition-all group" 
                 data-ticket-id="${ticket.id}"
                 data-ticket='${JSON.stringify(ticket).replace(/'/g, '&apos;')}'>
                <div class="flex items-start gap-3">
                    <!-- Avatar/Icon -->
                    <div class="relative flex-shrink-0">
                        <div class="w-12 h-12 rounded-full bg-gradient-to-br ${statusGradient} flex items-center justify-center text-white shadow-lg">
                            ${statusIcon}
                        </div>
                        ${isUnread ? `<div class="absolute -top-1 -right-1 min-w-[1.25rem] h-5 bg-red-500 rounded-full border-2 border-white dark:border-slate-900 flex items-center justify-center"><span class="text-white text-xs font-bold px-1">${ticket.unreadCount > 9 ? '9+' : ticket.unreadCount}</span></div>` : ''}
                    </div>
                    
                    <!-- Content -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between mb-1">
                            <h3 class="font-semibold text-slate-800 dark:text-slate-200 text-sm truncate group-hover:text-blue-600 dark:group-hover:text-emerald-400 transition-colors">
                                ${escapeHtml(ticket.subject)}
                            </h3>
                            <span class="text-xs text-slate-500 dark:text-slate-400 ml-2 flex-shrink-0">${timeAgo}</span>
                        </div>
                        
                        <div class="flex items-center gap-2 text-xs">
                            <span class="px-2 py-1 rounded-full bg-gradient-to-r ${statusGradient} text-white font-medium shadow-sm">
                                ${ticket.status.replace('_', ' ')}
                            </span>
                            <span class="text-slate-500 dark:text-slate-400">ÔÇó</span>
                            <span class="text-slate-600 dark:text-slate-400">${ticket.category}</span>
                        </div>
                        
                        ${ticket.messageCount > 1 ? `
                            <div class="mt-2 flex items-center gap-1 text-xs text-slate-500 dark:text-slate-400">
                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z"/>
                                </svg>
                                <span class="font-medium">${ticket.messageCount}</span> messages
                            </div>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    container.innerHTML = html;

    // Add click handlers to tickets
    document.querySelectorAll('.ticket-item').forEach(item => {
        item.addEventListener('click', function() {
            const ticketData = JSON.parse(this.dataset.ticket);
            loadTicketMessages(ticketData);
        });
    });
}

function loadTicketMessages(ticket) {
    showChatView(ticket);
    
    const container = document.getElementById('messages-container');
    container.innerHTML = `
        <div class="flex items-center justify-center h-full">
            <div class="flex flex-col items-center gap-3">
                <div class="relative">
                    <div class="animate-spin rounded-full h-10 w-10 border-4 border-emerald-200 border-t-emerald-600"></div>
                </div>
                <p class="text-slate-500 text-sm font-medium">Loading messages...</p>
            </div>
        </div>
    `;

    // Fetch messages via AJAX
    fetch(`/ticket/${ticket.id}/messages`, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Failed to load messages');
        }
        return response.json();
    })
    .then(data => {
        renderMessages(data.messages, ticket);
        setupQuickReply(ticket);
        // Update unread count after viewing messages
        updateUnreadCount();
    })
    .catch(error => {
        console.error('Error:', error);
        container.innerHTML = `
            <div class="flex flex-col items-center justify-center h-full text-center p-6">
                <svg class="w-16 h-16 text-red-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="font-semibold text-slate-800 dark:text-slate-200 mb-2">Failed to load messages</p>
                <button onclick="loadTickets()" class="mt-4 bg-gradient-to-r from-emerald-500 to-teal-600 text-white px-6 py-2 rounded-lg font-medium">
                    Back to Tickets
                </button>
            </div>
        `;
    });
}

function renderMessages(messages, ticket) {
    const container = document.getElementById('messages-container');
    
    if (!messages || messages.length === 0) {
        container.innerHTML = `
            <div class="flex flex-col items-center justify-center h-full text-center p-6">
                <div class="w-20 h-20 bg-gradient-to-br from-blue-100 to-purple-100 rounded-full flex items-center justify-center mb-4">
                    <svg class="w-10 h-10 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                    </svg>
                </div>
                <p class="font-semibold text-slate-800 dark:text-slate-200 mb-2">No messages yet</p>
                <p class="text-sm text-slate-500 dark:text-slate-400">Start the conversation</p>
            </div>
        `;
        return;
    }

    let html = '';
    
    messages.forEach((message, index) => {
        const isAdmin = message.senderRole === 'ADMIN';
        const messageTime = formatMessageTime(message.createdAt);
        
        if (isAdmin) {
            // Admin message (left side)
            html += `
                <div class="flex items-end gap-2 message-bubble-left">
                    <div class="flex-shrink-0 w-8 h-8 rounded-full bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center text-white text-xs font-bold shadow-lg">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"/>
                        </svg>
                    </div>
                    <div class="flex flex-col max-w-[75%]">
                        <div class="bg-white dark:bg-slate-800 rounded-2xl rounded-bl-sm px-4 py-2.5 shadow-md">
                            <p class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 mb-1">Support Team</p>
                            <p class="text-slate-800 dark:text-slate-200 text-sm whitespace-pre-wrap break-words">${escapeHtml(message.message)}</p>
                            ${message.fileName ? renderAttachment(message, false) : ''}
                        </div>
                        <span class="text-xs text-slate-400 dark:text-slate-500 mt-1 ml-2">${messageTime}</span>
                    </div>
                </div>
            `;
        } else {
            // User message (right side)
            html += `
                <div class="flex items-end gap-2 justify-end message-bubble-right">
                    <div class="flex flex-col max-w-[75%] items-end">
                        <div class="bg-gradient-to-br from-emerald-500 to-teal-600 rounded-2xl rounded-br-sm px-4 py-2.5 shadow-md">
                            <p class="text-white text-sm whitespace-pre-wrap break-words">${escapeHtml(message.message)}</p>
                            ${message.fileName ? renderAttachment(message, true) : ''}
                        </div>
                        <span class="text-xs text-slate-400 dark:text-slate-500 mt-1 mr-2">${messageTime}</span>
                    </div>
                    <div class="flex-shrink-0 w-8 h-8 rounded-full bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center text-white text-xs font-bold shadow-lg">
                        U
                    </div>
                </div>
            `;
        }
    });
    
    container.innerHTML = html;
    
    // Scroll to bottom
    setTimeout(() => {
        container.scrollTop = container.scrollHeight;
    }, 100);
}

function setupQuickReply(ticket) {
    const form = document.getElementById('quick-reply-form');
    const input = document.getElementById('quick-reply-input');
    const fileInput = document.getElementById('quick-reply-file');
    const attachBtn = document.getElementById('attach-file-btn');
    const filePreview = document.getElementById('file-preview');
    const fileName = document.getElementById('file-name');
    const removeFileBtn = document.getElementById('remove-file-btn');
    
    // Check if ticket is closed
    if (ticket.status === 'CLOSED') {
        // Hide the quick reply form and show closed message
        const quickReplyContainer = form.parentElement;
        quickReplyContainer.innerHTML = `
            <div class="bg-gradient-to-r from-slate-500 to-slate-700 text-white p-4 text-center">
                <div class="flex items-center justify-center gap-2 mb-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    <p class="font-bold">This ticket is closed</p>
                </div>
                <p class="text-sm text-slate-100 mb-3">You cannot send new replies to closed tickets.</p>
                <button onclick="if(window.opener && window.opener.openRatingModal){window.opener.openRatingModal();window.close();}else{window.location.href='/ticket/${ticket.id}';}" class="inline-flex items-center gap-2 px-6 py-2.5 bg-white/20 hover:bg-white/30 rounded-full text-white font-medium transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                    </svg>
                    Rate this ticket
                </button>
            </div>
        `;
        return;
    }
    
    // Original quick reply logic for open tickets
    const ticketId = ticket.id;
    
    // Remove existing listeners
    const newForm = form.cloneNode(true);
    form.parentNode.replaceChild(newForm, form);
    
    const newInput = document.getElementById('quick-reply-input');
    const newFileInput = document.getElementById('quick-reply-file');
    const newAttachBtn = document.getElementById('attach-file-btn');
    const newFilePreview = document.getElementById('file-preview');
    const newFileName = document.getElementById('file-name');
    const newRemoveFileBtn = document.getElementById('remove-file-btn');
    const sendButton = document.querySelector('#quick-reply-form button[type="submit"]');
    
    // Attach file button click
    newAttachBtn.addEventListener('click', function() {
        newFileInput.click();
    });
    
    // File input change
    newFileInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            newFileName.textContent = this.files[0].name;
            newFilePreview.classList.remove('hidden');
        } else {
            newFilePreview.classList.add('hidden');
        }
    });
    
    // Remove file button
    newRemoveFileBtn.addEventListener('click', function() {
        newFileInput.value = '';
        newFilePreview.classList.add('hidden');
    });
    
    document.getElementById('quick-reply-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const message = newInput.value.trim();
        const hasFile = newFileInput.files.length > 0;
        
        if (!message && !hasFile) {
            newInput.classList.add('border-red-500');
            newInput.placeholder = 'Please type a message...';
            return;
        }
        if (message.length > 0 && message.length < 2) {
            newInput.classList.add('border-red-500');
            return;
        }
        newInput.classList.remove('border-red-500');
        
        // Disable input and button
        newInput.disabled = true;
        newAttachBtn.disabled = true;
        const originalButtonHTML = sendButton.innerHTML;
        sendButton.innerHTML = `
            <svg class="w-6 h-6 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        `;
        
        // Prepare request data
        // The messenger popup should use the dedicated AJAX endpoint:
        //   POST /ticket/{id}/quick-reply
        // which accepts either:
        //   - JSON: { message }
        //   - multipart/form-data: message + attachment
        let fetchUrl = `/ticket/${ticketId}/quick-reply`;
        let fetchOptions = {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        };

        if (hasFile) {
            const formData = new FormData();
            formData.append('message', message);
            formData.append('attachment', newFileInput.files[0]);
            fetchOptions.body = formData;
        } else {
            fetchOptions.headers['Content-Type'] = 'application/json';
            fetchOptions.body = JSON.stringify({ message: message });
        }
        
        // Send via AJAX
        fetch(fetchUrl, fetchOptions)
        .then(async response => {
            const text = await response.text();
            if (!response.ok) {
                let errorMsg = 'Failed to send message';
                try {
                    const errorData = JSON.parse(text);
                    errorMsg = errorData.error || errorData.message || errorMsg;
                } catch (e) {
                    errorMsg = text || errorMsg;
                }
                throw new Error(errorMsg);
            }
            try {
                return JSON.parse(text);
            } catch (e) {
                throw new Error('Invalid server response');
            }
        })
        .then(data => {
            if (data.success) {
                // Add message to chat using the returned data
                const messageData = data.message;
                addMessageToChat(messageData, false);
                
                // Clear input and file
                newInput.value = '';
                newFileInput.value = '';
                newFilePreview.classList.add('hidden');
                newInput.disabled = false;
                newAttachBtn.disabled = false;
                sendButton.innerHTML = originalButtonHTML;
                
                // Focus input
                newInput.focus();
            } else {
                throw new Error(data.error || data.message || 'Failed to send message');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            // Show inline error above the input
            showPopupInlineError(error.message || 'Your message could not be sent. Please try again.');
            newInput.disabled = false;
            newAttachBtn.disabled = false;
            sendButton.innerHTML = originalButtonHTML;
        });
    });
}

function addMessageToChat(message, isAdmin = false) {
    const container = document.getElementById('messages-container');
    const messageTime = formatMessageTime(message.createdAt || new Date().toISOString());
    
    const messageHTML = `
        <div class="flex items-end gap-2 justify-end message-bubble-right">
            <div class="flex flex-col max-w-[75%] items-end">
                <div class="bg-gradient-to-br from-emerald-500 to-teal-600 rounded-2xl rounded-br-sm px-4 py-2.5 shadow-md">
                    <p class="text-white text-sm whitespace-pre-wrap break-words">${escapeHtml(message.message || '')}</p>
                    ${message.fileName ? renderAttachment(message, true) : ''}
                </div>
                <span class="text-xs text-slate-400 dark:text-slate-500 mt-1 mr-2">${messageTime}</span>
            </div>
            <div class="flex-shrink-0 w-8 h-8 rounded-full bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center text-white text-xs font-bold shadow-lg">
                U
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', messageHTML);
    
    // Scroll to bottom
    setTimeout(() => {
        container.scrollTop = container.scrollHeight;
    }, 100);
}

function formatMessageTime(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMins / 60);

    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins}m ago`;
    if (diffHours < 24) return `${diffHours}h ago`;
    
    return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
}

function updateUnreadCount() {
    fetch('/ticket/list', {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.totalUnread !== undefined) {
            const badge = document.getElementById('ticket-unread-badge');
            if (badge) {
                if (data.totalUnread > 0) {
                    badge.textContent = data.totalUnread > 99 ? '99+' : data.totalUnread;
                    badge.classList.remove('hidden');
                } else {
                    badge.classList.add('hidden');
                }
            }
        }
    })
    .catch(error => {
        console.error('Error updating unread count:', error);
    });
}

function formatDate(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMins / 60);
    const diffDays = Math.floor(diffHours / 24);

    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins}m ago`;
    if (diffHours < 24) return `${diffHours}h ago`;
    if (diffDays < 7) return `${diffDays}d ago`;
    
    return date.toLocaleDateString();
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function loadCategories() {
    fetch('/ticket/categories/list', {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.categories) {
            const select = document.getElementById('category-select');
            select.innerHTML = '<option value="">Select a category</option>';
            data.categories.forEach(category => {
                const option = document.createElement('option');
                option.value = category.id;
                option.textContent = category.name;
                select.appendChild(option);
            });
            
            // Store CSRF token for form submission
            if (data.csrf_token) {
                window.ticketCsrfToken = data.csrf_token;
            }
        }
    })
    .catch(error => {
        console.error('Error loading categories:', error);
        const select = document.getElementById('category-select');
        select.innerHTML = '<option value="">Error loading categories</option>';
    });
}

async function handleCreateTicket(e) {
    e.preventDefault();
    
    const form = e.target;
    const submitButton = form.querySelector('button[type="submit"]');
    const originalButtonHTML = submitButton.innerHTML;

    // ÔöÇÔöÇ Client-side validation ÔöÇÔöÇ
    // Clear previous inline errors
    form.querySelectorAll('.js-popup-error').forEach(el => el.remove());
    form.querySelectorAll('.border-red-500').forEach(el => {
        el.classList.remove('border-red-500');
        el.classList.add('border-slate-300', 'dark:border-slate-700');
    });

    function showPopupError(field, msg) {
        if (!field) return;
        field.classList.remove('border-slate-300', 'dark:border-slate-700');
        field.classList.add('border-red-500');
        const p = document.createElement('p');
        p.className = 'mt-1 text-xs text-red-500 js-popup-error';
        p.textContent = msg;
        field.parentNode.insertBefore(p, field.nextSibling);
    }

    let errors = 0;
    const subject = (form.querySelector('[name="subject"]').value || '').trim();
    const category = (form.querySelector('[name="category"]').value || '');
    const message = (form.querySelector('[name="message"]').value || '').trim();
    const fileInput = form.querySelector('input[type="file"]');

    if (subject === '') { showPopupError(form.querySelector('[name="subject"]'), 'Subject is required.'); errors++; }
    else if (subject.length < 3) { showPopupError(form.querySelector('[name="subject"]'), 'Subject must be at least 3 characters.'); errors++; }
    else if (subject.length > 255) { showPopupError(form.querySelector('[name="subject"]'), 'Subject must not exceed 255 characters.'); errors++; }

    if (!category || category === '') { showPopupError(form.querySelector('[name="category"]'), 'Please select a category.'); errors++; }

    if (message === '') { showPopupError(form.querySelector('[name="message"]'), 'Message is required.'); errors++; }
    else if (message.length < 10) { showPopupError(form.querySelector('[name="message"]'), 'Message must be at least 10 characters.'); errors++; }
    else if (message.length > 5000) { showPopupError(form.querySelector('[name="message"]'), 'Message must not exceed 5000 characters.'); errors++; }

    if (fileInput && fileInput.files.length > 0) {
        const file = fileInput.files[0];
        if (file.size > 10 * 1024 * 1024) { showPopupError(fileInput, 'Attachment must be under 10MB.'); errors++; }
    }

    if (errors > 0) {
        const first = form.querySelector('.border-red-500');
        if (first) first.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }
    // ÔöÇÔöÇ End validation ÔöÇÔöÇ
    
    // Disable submit button
    submitButton.disabled = true;
    submitButton.innerHTML = `
        <svg class="w-5 h-5 inline mr-2 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        Creating...
    `;
    
    // Create FormData
    const formData = new FormData();
    formData.append('ticket[subject]', form.querySelector('[name="subject"]').value);
    formData.append('ticket[category]', form.querySelector('[name="category"]').value);
    formData.append('ticket[priority]', form.querySelector('[name="priority"]').value);
    formData.append('ticket[message]', form.querySelector('[name="message"]').value);
    
    // Add file if exists
    if (fileInput && fileInput.files.length > 0) {
        formData.append('ticket[attachment]', fileInput.files[0]);
    }
    
    // Always fetch fresh CSRF token from categories endpoint (uses ticket_item token; dashboard may set wrong subticket_item)
    try {
        const catRes = await fetch('/ticket/categories/list', { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        const catData = await catRes.json();
        if (catData.csrf_token) {
            formData.append('ticket[_token]', catData.csrf_token);
        } else {
            alert('Failed to create ticket. Please refresh the page and try again.');
            submitButton.disabled = false;
            submitButton.innerHTML = originalButtonHTML;
            return;
        }
    } catch (_) {
        alert('Failed to create ticket. Please refresh the page and try again.');
        submitButton.disabled = false;
        submitButton.innerHTML = originalButtonHTML;
        return;
    }

    fetch('/ticket/create', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => {
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                console.error('Server returned non-JSON response (status ' + response.status + '):', text?.substring?.(0, 500));
                if (response.status === 401 || response.status === 403) {
                    throw new Error('Session expired. Please log in again.');
                }
                if (response.status >= 500) {
                    throw new Error('Server error. Please try again later.');
                }
                throw new Error('Invalid response. Please refresh the page and try again.');
            });
        }
        return response.json().then(data => ({status: response.status, data: data}));
    })
    .then(({status, data}) => {
        if (status === 200 && data.success && data.ticketId) {
            // Load tickets to refresh the list
            loadTickets();
            
            // Show success message and navigate to the new ticket
            const ticket = {
                id: data.ticketId,
                subject: data.subject || form.querySelector('[name="subject"]').value
            };
            
            // Load and show the ticket conversation
            showChatView(ticket);
            loadTicketMessages(ticket);
        } else {
            // Show specific error message from server
            const errorMsg = data.message || data.errors?.join(', ') || 'Failed to create ticket';
            throw new Error(errorMsg);
        }
    })
    .catch(error => {
        console.error('Error creating ticket:', error);
        showPopupInlineError(error.message || 'Failed to create ticket. Please try again.');
        submitButton.disabled = false;
        submitButton.innerHTML = originalButtonHTML;
    });
}

function getCsrfToken() {
    // Use token from categories endpoint
    if (window.ticketCsrfToken) {
        return window.ticketCsrfToken;
    }
    
    // Try to get token from meta tag
    const metaToken = document.querySelector('meta[name="csrf-token"]');
    if (metaToken) {
        return metaToken.getAttribute('content');
    }
    
    // Fallback: try to get from existing form on page
    const existingToken = document.querySelector('input[name="_token"]');
    if (existingToken) {
        return existingToken.value;
    }
    
    // If no token found, return empty (will cause validation error but won't crash)
    return '';
}

function isImageFile(fileName) {
    if (!fileName) return false;
    const extension = fileName.split('.').pop().toLowerCase();
    return ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'].includes(extension);
}

function renderAttachment(message, isUserMessage = false) {
    if (!message.fileName || !message.filePath) return '';
    
    const fileSize = message.fileSize ? `(${(message.fileSize / 1024).toFixed(2)} KB)` : '';
    
    if (isImageFile(message.fileName)) {
        // Render image inline
        const borderColor = isUserMessage ? 'border-blue-400' : 'border-slate-200 dark:border-slate-700';
        const textColor = isUserMessage ? 'text-emerald-100' : 'text-emerald-600 dark:text-emerald-400';
        
        return `
            <div class="mt-2 pt-2 border-t ${isUserMessage ? 'border-blue-400/30' : 'border-slate-200 dark:border-slate-700'}">
                <a href="${message.filePath}" target="_blank" class="block group">
                    <img src="${message.filePath}" alt="${message.fileName}" class="max-w-full max-h-48 rounded-lg ${borderColor} border group-hover:opacity-90 transition-opacity">
                    <span class="text-xs ${textColor} mt-1 inline-block">${message.fileName} ${fileSize}</span>
                </a>
            </div>
        `;
    } else {
        // Render file download link
        const textColor = isUserMessage ? 'text-emerald-100 hover:text-white' : 'text-emerald-600 dark:text-emerald-400 hover:text-blue-700';
        
        return `
            <div class="mt-2 pt-2 border-t ${isUserMessage ? 'border-blue-400/30' : 'border-slate-200 dark:border-slate-700'}">
                <a href="${message.filePath}" target="_blank" class="flex items-center gap-2 text-xs ${textColor}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                    </svg>
                    <span class="truncate">${message.fileName} ${fileSize}</span>
                </a>
            </div>
        `;
    }
}


/**
 * Show an inline error banner inside the popup widget
 * Appears above the quick-reply input or at top of current view
 */
function showPopupInlineError(message) {
    // Remove any existing error
    const existing = document.getElementById('popup-inline-error');
    if (existing) existing.remove();

    const errorHTML = `
        <div id="popup-inline-error" class="mx-3 mb-2 p-3 rounded-xl bg-red-50 dark:bg-red-900/30 border border-red-300 dark:border-red-700 animate-popup-shake" style="animation: popupShake 0.5s ease-in-out;">
            <div class="flex items-start gap-2.5">
                <div class="flex-shrink-0 w-8 h-8 rounded-full bg-red-100 dark:bg-red-900/50 flex items-center justify-center mt-0.5">
                    <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-red-800 dark:text-red-300 text-sm">Message Blocked</p>
                    <p class="text-xs text-red-700 dark:text-red-400 mt-0.5">${message}</p>
                </div>
                <button onclick="this.closest('#popup-inline-error').remove()" class="flex-shrink-0 text-red-400 hover:text-red-600 dark:text-red-500 dark:hover:text-red-300 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
    `;

    // Try to insert above the quick-reply form
    const quickReplyForm = document.getElementById('quick-reply-form');
    if (quickReplyForm) {
        quickReplyForm.insertAdjacentHTML('beforebegin', errorHTML);
    } else {
        // Fallback: insert at top of messages container
        const messagesContainer = document.getElementById('messages-container');
        if (messagesContainer) {
            messagesContainer.insertAdjacentHTML('afterend', errorHTML);
        }
    }

    // Auto-dismiss after 8 seconds
    setTimeout(() => {
        const el = document.getElementById('popup-inline-error');
        if (el) {
            el.style.transition = 'opacity 0.3s, transform 0.3s';
            el.style.opacity = '0';
            el.style.transform = 'translateY(-10px)';
            setTimeout(() => el.remove(), 300);
        }
    }, 8000);
}

// Inject shake animation styles for popup
(function() {
    const style = document.createElement('style');
    style.textContent = `
        @keyframes popupShake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-3px); }
            20%, 40%, 60%, 80% { transform: translateX(3px); }
        }
    `;
    document.head.appendChild(style);
})();
/**
 * Show a brief success toast when AI fills in the form
 */
function showAiSuccessToast() {
    const existing = document.getElementById('ai-success-toast');
    if (existing) existing.remove();
    
    const toast = document.createElement('div');
    toast.id = 'ai-success-toast';
    toast.className = 'fixed bottom-24 right-8 z-[60] bg-gradient-to-r from-emerald-500 to-teal-600 text-white px-5 py-3 rounded-xl shadow-2xl flex items-center gap-3 animate-slide-up';
    toast.innerHTML = `
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
        <span class="font-medium text-sm">AI filled your ticket! Review and submit.</span>
    `;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.transition = 'opacity 0.5s, transform 0.5s';
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(20px)';
        setTimeout(() => toast.remove(), 500);
    }, 3000);
}

// Inject slide-up animation
(function() {
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-slide-up {
            animation: slideUp 0.3s ease-out;
        }
    `;
    document.head.appendChild(style);
})();
