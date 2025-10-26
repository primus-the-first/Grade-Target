document.addEventListener('DOMContentLoaded', () => {
    const tutorForm = document.getElementById('tutorForm');
    const questionInput = document.getElementById('question');
    const chatMessages = document.getElementById('chat-container');
    const conversationIdInput = document.getElementById('conversation_id');
    const submitBtn = document.getElementById('ai-submit-btn');
    const fileInput = document.getElementById('file-attachment');
    const attachmentPreviewArea = document.getElementById('attachment-preview-area');
    const newChatBtn = document.getElementById('newChatBtn');
    const chatHistoryContainer = document.getElementById('chat-history-container');
    const menuToggle = document.getElementById('menu-toggle');
    const sidebar = document.getElementById('sidebar');
    const chatTitleDisplay = document.getElementById('chat-title-display');

    // --- Load chat history on page load ---
    loadChatHistory();

    // --- Welcome message for new chats ---
    const welcomeMessage = '<div class="chat-message ai-message"><div class="message-bubble">Hello! I\'m your AI Tutor. What would you like to learn about today?</div></div>';

    // --- Function to add a message to the chat window ---
    function addMessage(sender, messageHtml) {
        const messageWrapper = document.createElement('div');
        messageWrapper.classList.add('chat-message', `${sender}-message`);

        const messageBubble = document.createElement('div');
        messageBubble.classList.add('message-bubble');
        messageBubble.innerHTML = messageHtml;

        messageWrapper.appendChild(messageBubble);
        chatMessages.appendChild(messageWrapper);

        // Scroll to the bottom
        chatMessages.scrollTop = chatMessages.scrollHeight;

        // If MathJax is available, typeset the new message
        if (typeof MathJax !== 'undefined' && MathJax.typesetPromise) {
            MathJax.typesetPromise([messageBubble]);
        }
    }

    // --- Text-to-Speech Functionality ---
    function handleReadAloud(e) {
        const button = e.currentTarget;
        const messageBubble = button.closest('.message-bubble');
        // Clone the node to manipulate it without affecting the display
        const contentClone = messageBubble.cloneNode(true);
        // Remove the button from the clone so its text isn't read
        const footerClone = contentClone.querySelector('.message-footer');
        if (footerClone) footerClone.remove();
        const textToRead = contentClone.textContent.trim();

        if (speechSynthesis.speaking && button.dataset.speaking === 'true') {
            speechSynthesis.cancel();
            button.dataset.speaking = 'false';
            button.innerHTML = '<i class="fas fa-volume-up"></i>';
        } else {
            // Cancel any previous speech
            speechSynthesis.cancel();

            // Reset all other buttons
            document.querySelectorAll('.read-aloud-btn[data-speaking="true"]').forEach(btn => {
                btn.dataset.speaking = 'false';
                btn.innerHTML = '<i class="fas fa-volume-up"></i>';
            });

            const utterance = new SpeechSynthesisUtterance(textToRead);
            utterance.onstart = () => {
                button.dataset.speaking = 'true';
                button.innerHTML = '<i class="fas fa-stop-circle"></i>';
            };
            utterance.onend = () => {
                button.dataset.speaking = 'false';
                button.innerHTML = '<i class="fas fa-volume-up"></i>';
            };
            speechSynthesis.speak(utterance);
        }
    }

    // --- Function to show/hide the typing indicator ---
    function showTypingIndicator(show) {
        let indicator = document.getElementById('typing-indicator');
        if (show) {
            if (!indicator) {
                const indicatorWrapper = document.createElement('div');
                indicatorWrapper.id = 'typing-indicator';
                indicatorWrapper.classList.add('chat-message', 'ai-message');
                indicatorWrapper.innerHTML = `
                    <div class="message-bubble">
                        <div class="typing-indicator">
                            <span></span><span></span><span></span>
                        </div>
                    </div>
                `;
                chatMessages.appendChild(indicatorWrapper);
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        } else {
            if (indicator) {
                indicator.remove();
            }
        }
    }

    // --- Handle file selection ---
    fileInput.addEventListener('change', () => {
        if (fileInput.files.length > 0) {
            const fileName = fileInput.files[0].name;
            attachmentPreviewArea.innerHTML = `
                <div class="attachment-display">
                    <i class="fas fa-file-alt"></i>
                    <span>${fileName}</span>
                    <span class="clear-attachment" title="Remove file">&times;</span>
                </div>
            `;
        } else {
            attachmentPreviewArea.innerHTML = '';
        }
    });

    // --- Handle clearing the attachment ---
    attachmentPreviewArea.addEventListener('click', (e) => {
        if (e.target.classList.contains('clear-attachment')) {
            fileInput.value = ''; // Clear the file input
            attachmentPreviewArea.innerHTML = ''; // Clear the preview
        }
    });

    // --- Handle form submission ---
    tutorForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const question = questionInput.value.trim();
        if (!question && fileInput.files.length === 0) return;

        // --- The Fix: Capture FormData immediately ---
        // This ensures we have the question and file data before any other operations.
        const formData = new FormData(tutorForm);

        // --- Build and display the user's message ---
        let userMessageHtml = '';
        // If a file is attached, create a small display for it.
        if (fileInput.files.length > 0) {
            const fileName = fileInput.files[0].name;
            userMessageHtml += `
                <div class="message-attachment-display">
                    <i class="fas fa-file-alt"></i>
                    <span>${fileName}</span>
                </div>
            `;
        }
        const escapedQuestion = question.replace(/</g, "&lt;").replace(/>/g, "&gt;");
        addMessage('user', userMessageHtml + escapedQuestion);

        // Clear inputs immediately for better UX
        questionInput.value = '';
        attachmentPreviewArea.innerHTML = '';
        fileInput.value = '';

        try {
            // Disable form and show typing indicator
            submitBtn.disabled = true;
            questionInput.disabled = true;
            showTypingIndicator(true);
            const response = await fetch('server.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

            const result = await response.json();

            if (result.success) {
                // Add the AI message with a "Read Aloud" button
                const messageContent = `
                    ${result.answer}
                    <div class="message-footer">
                        <button class="read-aloud-btn" title="Read aloud">
                            <i class="fas fa-volume-up"></i>
                        </button>
                    </div>
                `;
                addMessage('ai', messageContent);
                // Attach event listener to the new button
                const newButton = chatMessages.lastElementChild.querySelector('.read-aloud-btn');
                if (newButton) newButton.addEventListener('click', handleReadAloud);

                // If a new title was generated, update the display
                if (result.title) {
                    chatTitleDisplay.textContent = result.title;
                }
                if (result.conversation_id) {
                    conversationIdInput.value = result.conversation_id;
                    // If this was a new chat, refresh the history to show it
                    const existingLink = chatHistoryContainer.querySelector(`[data-conversation-id="${result.conversation_id}"]`);
                    if (!existingLink) {
                        await loadChatHistory();
                        highlightActiveConversation(result.conversation_id);
                    }
                }
            } else {
                addMessage('ai', `<p style="color: red;">Error: ${result.error || 'An unknown error occurred.'}</p>`);
            }
        } catch (error) {
            console.error('Fetch Error:', error);
            addMessage('ai', `<p style="color: red;">Sorry, I couldn't connect to the server. Please try again later.</p>`);
        } finally {
            showTypingIndicator(false);
            submitBtn.disabled = false;
            questionInput.disabled = false;
            questionInput.focus();
        }
    });

    // --- Handle "New Chat" button ---
    newChatBtn.addEventListener('click', () => {
        conversationIdInput.value = '';
        chatMessages.innerHTML = welcomeMessage;
        chatTitleDisplay.textContent = 'New Chat';
        highlightActiveConversation(null);
    });

    // --- Function to load chat history from server ---
    async function loadChatHistory() {
        try {
            const response = await fetch('server.php?action=history');
            const result = await response.json();

            if (result.success && Array.isArray(result.history)) {
                chatHistoryContainer.innerHTML = ''; // Clear existing history
                result.history.forEach(convo => {
                    const historyItem = document.createElement('div');
                    historyItem.classList.add('history-item', 'flex', 'justify-between', 'items-center');

                    const link = document.createElement('a');
                    link.href = '#';
                    link.textContent = convo.title;
                    link.dataset.conversationId = convo.id;
                    link.classList.add('flex-1', 'truncate');
                    link.addEventListener('click', (e) => {
                        e.preventDefault();
                        loadConversation(convo.id);
                    });

                    const deleteBtn = document.createElement('button');
                    deleteBtn.innerHTML = '<i class="fas fa-trash-alt"></i>';
                    deleteBtn.classList.add('text-gray-400', 'hover:text-white', 'ml-2');
                    deleteBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        deleteConversation(convo.id);
                    });

                    historyItem.appendChild(link);
                    historyItem.appendChild(deleteBtn);
                    chatHistoryContainer.appendChild(historyItem);
                });
            }
        } catch (error) {
            console.error('Error loading chat history:', error);
        }
    }

    // --- Function to load a specific conversation ---
    async function loadConversation(id) {
        try {
            const response = await fetch(`server.php?action=get_conversation&id=${id}`);
            const result = await response.json();

            if (result.success) {
                chatMessages.innerHTML = '';
                conversationIdInput.value = id;
                chatTitleDisplay.textContent = result.conversation.title;
                result.conversation.chat_history.forEach(item => {
                    // The user's message in history includes file context, which we don't want to re-display.
                    // We'll just show the question part.
                    if (item.role === 'user') {
                        const userText = item.parts[0].text;
                        const questionMatch = userText.match(/User's question: (.*)/s);
                        const display_text = questionMatch ? questionMatch[1] : userText;
                        addMessage('user', display_text.replace(/</g, "&lt;").replace(/>/g, "&gt;"));
                    } else {
                        // Add the AI message with a "Read Aloud" button when loading from history
                        const messageContent = `
                            ${item.parts[0].text}
                            <div class="message-footer">
                                <button class="read-aloud-btn" title="Read aloud">
                                    <i class="fas fa-volume-up"></i>
                                </button>
                            </div>
                        `;
                        addMessage('ai', messageContent);
                        // Attach event listener to the new button
                        const newButton = chatMessages.lastElementChild.querySelector('.read-aloud-btn');
                        if (newButton) newButton.addEventListener('click', handleReadAloud);
                    }
                });
                highlightActiveConversation(id);

                // After adding all messages, typeset the entire chat container at once
                if (typeof MathJax !== 'undefined' && MathJax.typesetPromise) {
                    MathJax.typesetPromise([chatMessages]);
                }
            }
        } catch (error) {
            console.error('Error loading conversation:', error);
        }
    }

    // --- Function to delete a conversation ---
    async function deleteConversation(id) {
        if (!confirm('Are you sure you want to delete this chat?')) return;

        try {
            const response = await fetch(`server.php?action=delete_conversation&id=${id}`);
            const result = await response.json();

            if (result.success) {
                if (conversationIdInput.value === id) {
                    newChatBtn.click(); // Start a new chat if the active one was deleted
                }
                loadChatHistory(); // Refresh the history list
            }
        } catch (error) {
            console.error('Error deleting conversation:', error);
        }
    }

    // --- Function to highlight the active chat in the sidebar ---
    function highlightActiveConversation(id) {
        const allLinks = chatHistoryContainer.querySelectorAll('a');
        allLinks.forEach(link => link.classList.remove('active'));
        if (id) {
            const activeLink = chatHistoryContainer.querySelector(`[data-conversation-id="${id}"]`);
            if (activeLink) activeLink.classList.add('active');
        }
    }

    // --- Mobile Sidebar Toggle ---
    if (menuToggle && sidebar) {
        const overlay = document.getElementById('sidebar-overlay');
        menuToggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('hidden');
        });

        overlay.addEventListener('click', () => {
            sidebar.classList.remove('open');
            overlay.classList.add('hidden');
        });
    }
});