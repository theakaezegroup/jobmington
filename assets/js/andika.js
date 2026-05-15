const Andika = {
    elements: {
        scroll: document.getElementById('scroll-area'),
        thread: document.getElementById('chat-thread'),
        welcome: document.getElementById('welcome-ui'),
        input: document.getElementById('chat-input'),
        status: document.getElementById('status-tag'),
        progress: document.getElementById('progress-bar')
    },

    init: function() {
        this.elements.input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.submit();
            }
        });
    },

    quickSend: function(text) {
        this.elements.input.value = text;
        this.submit();
    },

    submit: async function() {
        const text = this.elements.input.value.trim();
        if (!text) return;

        if (this.elements.welcome) this.elements.welcome.style.display = 'none';

        this.appendMessage(text, 'user');
        this.elements.input.value = '';
        this.elements.input.disabled = true;

        this.showTypingIndicator();

        // HUD Update
        this.elements.status.innerText = "PROCESSING";
        this.elements.status.className = "text-[10px] font-black text-amber-400 animate-pulse";
        this.elements.progress.style.width = "100%";

        try {
            const response = await fetch('/jobmington/ai/andika-brain.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ message: text })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            this.appendMessage(data.reply, 'ai');

        } catch (error) {
            console.error('Error fetching AI response:', error);
            this.appendMessage('Sorry, I seem to be having trouble connecting to my core. Please try again in a moment.', 'ai');
        } finally {
            this.hideTypingIndicator();
            this.elements.input.disabled = false;
            this.elements.input.focus();

            // Reset HUD
            this.elements.status.innerText = "IDLE";
            this.elements.status.className = "text-[10px] font-black text-slate-500";
            this.elements.progress.style.width = "0%";
        }
    },

    appendMessage: function(text, role) {
        const div = document.createElement('div');
        div.className = `bubble bubble-${role}`;
        div.innerHTML = text.replace(/\n/g, '<br>');
        this.elements.thread.appendChild(div);
        
        this.scrollToBottom();
    },

    showTypingIndicator: function() {
        const typingBubble = document.createElement('div');
        typingBubble.id = 'typing-indicator';
        typingBubble.className = 'bubble bubble-ai';
        typingBubble.innerHTML = '<div class="flex items-center gap-2"><div class="w-2 h-2 bg-amber-400 rounded-full animate-pulse"></div><div class="w-2 h-2 bg-amber-400 rounded-full animate-pulse" style="animation-delay: 0.2s;"></div><div class="w-2 h-2 bg-amber-400 rounded-full animate-pulse" style="animation-delay: 0.4s;"></div></div>';
        this.elements.thread.appendChild(typingBubble);
        this.scrollToBottom();
    },

    hideTypingIndicator: function() {
        const indicator = document.getElementById('typing-indicator');
        if (indicator) {
            indicator.remove();
        }
    },

    scrollToBottom: function() {
        this.elements.scroll.scrollTo({
            top: this.elements.scroll.scrollHeight,
            behavior: 'smooth'
        });
    }
};

document.addEventListener('DOMContentLoaded', () => Andika.init());
