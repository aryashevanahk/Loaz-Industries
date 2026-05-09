// Chat functionality
$(document).ready(function() {
    var technicianId = $('#technician-id').val();
    var userId = $('#user-id').val();
    
    // Load messages
    function loadMessages() {
        $.get('/loaz_industries/api/get_messages.php', {
            technician_id: technicianId
        }, function(messages) {
            var chatContainer = $('#chat-messages');
            chatContainer.empty();
            
            messages.forEach(function(message) {
                var messageClass = message.from_user_id == userId ? 'message-sent' : 'message-received';
                var html = '<div class="message ' + messageClass + '">' +
                    '<div>' + message.message + '</div>' +
                    '<small>' + new Date(message.timestamp).toLocaleTimeString() + '</small>' +
                    '</div>';
                chatContainer.append(html);
            });
            
            // Scroll to bottom
            chatContainer.scrollTop(chatContainer[0].scrollHeight);
        });
    }
    
    // Send message
    $('#send-message').click(function() {
        var message = $('#message-input').val();
        if (message.trim()) {
            $.post('/loaz_industries/api/send_message.php', {
                to_user_id: technicianId,
                message: message
            }, function() {
                $('#message-input').val('');
                loadMessages();
            });
        }
    });
    
    // Load messages every 3 seconds
    loadMessages();
    setInterval(loadMessages, 3000);
    
    // Enter key to send
    $('#message-input').keypress(function(e) {
        if (e.which == 13) {
            $('#send-message').click();
            return false;
        }
    });
});