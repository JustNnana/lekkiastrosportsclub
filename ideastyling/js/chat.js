/**
 * Gate Wey Access Management System
 * Chat JavaScript Functions
 */

document.addEventListener('DOMContentLoaded', function() {
    // Elements
    const chatMessages = document.querySelector('.chat-messages');
    const messageForm = document.getElementById('messageForm');
    const messageTextarea = document.querySelector('textarea[name="message"]');
    const chatRoomId = messageForm ? messageForm.querySelector('input[name="chat_room_id"]').value : null;
    
    // Auto-resize textarea
    if (messageTextarea) {
        messageTextarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
            
            // Limit to 5 rows max
            const maxHeight = 20 * 5; // line-height * max rows
            if (this.scrollHeight > maxHeight) {
                this.style.height = maxHeight + 'px';
                this.style.overflowY = 'auto';
            } else {
                this.style.overflowY = 'hidden';
            }
        });
    }
    
    // Scroll to bottom of chat messages
    if (chatMessages) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    // Handle form submission
    if (messageForm) {
        messageForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitButton = this.querySelector('button[type="submit"]');
            
            // Disable form elements
            if (messageTextarea) messageTextarea.disabled = true;
            if (submitButton) submitButton.disabled = true;
            
            fetch(this.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // Clear textarea and reset its height
                    if (messageTextarea) {
                        messageTextarea.value = '';
                        messageTextarea.style.height = 'auto';
                    }
                    
                    // In a production app, we would append the new message to the UI
                    // without reloading, but for simplicity, we'll reload the page
                    window.location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while sending the message.');
            })
            .finally(() => {
                // Re-enable form elements
                if (messageTextarea) messageTextarea.disabled = false;
                if (submitButton) submitButton.disabled = false;
            });
        });
    }
    
    // Handle chat type change in the new chat modal
    const chatTypeSelect = document.getElementById('chatType');
    const participantsSection = document.getElementById('participantsSection');
    
    if (chatTypeSelect && participantsSection) {
        chatTypeSelect.addEventListener('change', function() {
            if (this.value === 'clan_wide') {
                participantsSection.style.display = 'none';
            } else {
                participantsSection.style.display = 'block';
            }
        });
        
        // Trigger on page load
        if (chatTypeSelect.value === 'clan_wide') {
            participantsSection.style.display = 'none';
        }
    }
    
    // Poll for new messages (in a production app, you would use WebSockets)
    if (chatRoomId) {
        const checkNewMessages = () => {
            const lastMessageElement = document.querySelector('.chat-message:last-child');
            const lastMessageId = lastMessageElement ? lastMessageElement.dataset.messageId : 0;
            
            fetch(`check_messages.php?chat_room_id=${chatRoomId}&last_message_id=${lastMessageId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success' && data.new_messages && data.new_messages.length > 0) {
                        // In a production app, you would append the new messages to the UI
                        // For simplicity, we'll reload the page if there are new messages
                        window.location.reload();
                    }
                })
                .catch(error => console.error('Error checking for new messages:', error));
        };
        
        // Check for new messages every 10 seconds
        // In a production app, you would use WebSockets instead
        setInterval(checkNewMessages, 10000);
    }
});