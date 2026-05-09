<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
redirectIfNotLoggedIn();

$user_id = $_SESSION['user_id'];

// Generate atau ambil session chat
if (!isset($_SESSION['chat_session_id'])) {
    $_SESSION['chat_session_id'] = 'user_' . $user_id . '_' . time();
}
$session_id = $_SESSION['chat_session_id'];

// Load chat history
$stmt = $pdo->prepare("
    SELECT * FROM support_chat 
    WHERE session_id = ? 
    ORDER BY created_at ASC
");
$stmt->execute([$session_id]);
$chats = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="display-5 fw-light" style="color: var(--dark-brown);">
                        <i class="fas fa-comments me-2" style="color: var(--gold-brown);"></i>Live Chat Support
                    </h1>
                    <p class="text-muted">Chat langsung dengan customer service kami</p>
                </div>
                <a href="dashboard.php" class="btn btn-outline-gold rounded-4">
                    <i class="fas fa-arrow-left me-2"></i> Kembali
                </a>
            </div>
            
            <!-- Chat Container -->
            <div class="chat-card rounded-4">
                <div class="chat-header p-3 border-bottom">
                    <div class="d-flex align-items-center">
                        <div class="chat-avatar me-3">
                            <i class="fas fa-headset fa-2x" style="color: var(--gold-brown);"></i>
                        </div>
                        <div>
                            <h5 class="mb-0">Customer Support</h5>
                            <small class="text-muted">
                                <i class="fas fa-circle text-success me-1" style="font-size: 0.6rem;"></i> Online
                            </small>
                        </div>
                    </div>
                </div>
                
                <div class="chat-messages" id="chatMessages">
                    <?php if (count($chats) > 0): ?>
                        <?php foreach ($chats as $chat): ?>
                            <div class="message message-<?php echo $chat['sender_type']; ?>">
                                <div class="message-bubble">
                                    <?php echo nl2br(htmlspecialchars($chat['message'])); ?>
                                    <div class="message-time">
                                        <?php echo date('H:i', strtotime($chat['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-comment-dots fa-3x mb-3"></i>
                            <p>Mulai percakapan dengan customer service kami.<br>Kami siap membantu Anda!</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="chat-input p-3 border-top">
                    <form id="chatForm" class="d-flex gap-2">
                        <input type="hidden" id="session_id" value="<?php echo $session_id; ?>">
                        <input type="text" id="messageInput" class="form-control rounded-4" 
                               placeholder="Tulis pesan Anda..." autocomplete="off">
                        <button type="submit" class="btn btn-gold rounded-4 px-4">
                            <i class="fas fa-paper-plane me-2"></i> Kirim
                        </button>
                    </form>
                    <small class="text-muted d-block mt-2">
                        <i class="fas fa-info-circle me-1"></i> Tekan Enter untuk mengirim pesan
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .chat-card {
        background: white;
        border: 1px solid rgba(192, 133, 82, 0.15);
        border-radius: 20px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        height: 500px;
    }
    
    .chat-header {
        background: white;
        flex-shrink: 0;
    }
    
    .chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 1rem;
        background: #fafafa;
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }
    
    .message {
        display: flex;
        animation: fadeIn 0.3s ease;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .message-user {
        justify-content: flex-end;
    }
    
    .message-admin {
        justify-content: flex-start;
    }
    
    .message-bubble {
        max-width: 70%;
        padding: 0.6rem 1rem;
        border-radius: 18px;
        word-wrap: break-word;
    }
    
    .message-user .message-bubble {
        background: var(--gold-brown);
        color: white;
        border-bottom-right-radius: 4px;
    }
    
    .message-admin .message-bubble {
        background: white;
        color: var(--dark-brown);
        border-bottom-left-radius: 4px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    }
    
    .message-time {
        font-size: 0.55rem;
        margin-top: 0.25rem;
        opacity: 0.7;
    }
    
    .message-user .message-time {
        text-align: right;
    }
    
    .message-admin .message-time {
        text-align: left;
    }
    
    .chat-input {
        background: white;
        flex-shrink: 0;
    }
    
    .btn-gold {
        background: var(--gold-brown);
        color: white;
        border: none;
        transition: all 0.3s ease;
        white-space: nowrap;
    }
    
    .btn-gold:hover {
        background: var(--medium-brown);
    }
    
    .btn-outline-gold {
        border: 1.5px solid var(--gold-brown);
        color: var(--gold-brown);
        background: transparent;
        transition: all 0.3s ease;
    }
    
    .btn-outline-gold:hover {
        background: var(--gold-brown);
        color: white;
    }
    
    /* Scrollbar Styling */
    .chat-messages::-webkit-scrollbar {
        width: 4px;
    }
    
    .chat-messages::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }
    
    .chat-messages::-webkit-scrollbar-thumb {
        background: var(--gold-brown);
        border-radius: 4px;
    }
    
    @media (max-width: 576px) {
        .chat-card {
            height: 450px;
        }
        
        .message-bubble {
            max-width: 85%;
        }
        
        .btn-gold span {
            display: none;
        }
        
        .btn-gold i {
            margin: 0;
        }
        
        .btn-gold {
            padding: 0.5rem 0.8rem;
        }
    }
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    let lastMessageId = 0;
    let sessionId = $('#session_id').val();
    
    // Auto scroll to bottom
    function scrollToBottom() {
        const messagesDiv = document.getElementById('chatMessages');
        if (messagesDiv) {
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
        }
    }
    
    // Load messages with AJAX
    function loadMessages() {
        if (!sessionId) return;
        
        $.get('../api/get_support_messages.php?session_id=' + sessionId, function(messages) {
            if (messages.length > 0) {
                const lastMessage = messages[messages.length - 1];
                if (lastMessage.id !== lastMessageId) {
                    renderMessages(messages);
                    lastMessageId = lastMessage.id;
                    scrollToBottom();
                }
            }
        }, 'json');
    }
    
    // Render messages to DOM
    function renderMessages(messages) {
        let html = '';
        messages.forEach(msg => {
            const isUser = msg.sender_type == 'user';
            html += `
                <div class="message message-${isUser ? 'user' : 'admin'}">
                    <div class="message-bubble">
                        ${escapeHtml(msg.message)}
                        <div class="message-time">
                            ${new Date(msg.created_at).toLocaleTimeString('id-ID', {hour:'2-digit', minute:'2-digit'})}
                        </div>
                    </div>
                </div>
            `;
        });
        
        $('#chatMessages').html(html);
    }
    
    // Send message
    function sendMessage() {
        const message = $('#messageInput').val().trim();
        if (!message) return false;
        
        const btn = $('#chatForm button');
        const originalText = btn.html();
        
        btn.html('<i class="fas fa-spinner fa-spin me-2"></i>').prop('disabled', true);
        
        $.post('../api/send_support_message.php', {
            session_id: sessionId,
            message: message
        }, function(response) {
            if (response.success) {
                $('#messageInput').val('');
                loadMessages();
            }
        }, 'json').always(function() {
            btn.html(originalText).prop('disabled', false);
        });
        
        return false;
    }
    
    // Escape HTML
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }
    
    $(document).ready(function() {
        loadMessages();
        setInterval(loadMessages, 2000);
        
        $('#chatForm').on('submit', function(e) {
            e.preventDefault();
            sendMessage();
        });
        
        $('#messageInput').on('keypress', function(e) {
            if (e.which === 13 && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
        
        // Focus on input
        $('#messageInput').focus();
        scrollToBottom();
    });
</script>

<?php include '../includes/footer.php'; ?>