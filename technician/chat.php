<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
redirectIfNotLoggedIn();

if (!isTechnician()) {
    if (isAdmin()) header('Location: /loaz_industries/admin/dashboard.php');
    elseif (isUser()) header('Location: /loaz_industries/user/dashboard.php');
    exit();
}

$technician_id = $_SESSION['user_id'];

// Get technician's services with users
$stmt = $pdo->prepare("
    SELECT s.id, s.device, s.status, s.user_id, u.name as customer_name, u.email as customer_email
    FROM services s
    JOIN users u ON s.user_id = u.id
    JOIN technicians t ON s.technician_id = t.id
    WHERE t.user_id = ? AND s.status != 'done'
    ORDER BY s.created_at DESC
");
$stmt->execute([$technician_id]);
$active_services = $stmt->fetchAll();

// Get selected service
$selected_service = isset($_GET['service_id']) ? (int)$_GET['service_id'] : (isset($active_services[0]) ? $active_services[0]['id'] : 0);
$selected_customer = null;

if ($selected_service > 0) {
    $stmt = $pdo->prepare("
        SELECT s.*, u.id as customer_id, u.name as customer_name, u.email as customer_email
        FROM services s
        JOIN users u ON s.user_id = u.id
        JOIN technicians t ON s.technician_id = t.id
        WHERE s.id = ? AND t.user_id = ?
    ");
    $stmt->execute([$selected_service, $technician_id]);
    $selected_customer = $stmt->fetch();
}

include '../includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-12">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-2">
                            <li class="breadcrumb-item">
                                <a href="dashboard.php" style="color: var(--gold-brown); text-decoration: none;">
                                    <i class="fas fa-home me-1"></i> Dashboard
                                </a>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">
                                Chat dengan Customer
                            </li>
                        </ol>
                    </nav>
                    <h1 class="display-5 fw-light" style="color: var(--dark-brown);">
                        <i class="fas fa-comments me-2" style="color: var(--gold-brown);"></i>Chat Customer
                    </h1>
                    <p class="text-muted">Tanggapi pertanyaan customer tentang servis</p>
                </div>
                    <a href="dashboard.php" class="btn btn-outline-gold rounded-4 mt-3">
                        <i class="fas fa-home me-2"></i> Kembali ke Dashboard
                    </a>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Chat List Sidebar -->
        <div class="col-md-4 mb-4 mb-md-0">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-transparent border-0 pt-4">
                    <h5 class="mb-0"><i class="fas fa-users me-2" style="color: var(--gold-brown);"></i>Customer Aktif</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($active_services)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-comment-slash fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Belum ada customer yang menghubungi</p>
                            <a href="dashboard.php" class="btn btn-outline-gold rounded-4 mt-2">
                                <i class="fas fa-home me-2"></i> Kembali ke Dashboard
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($active_services as $service): ?>
                                <a href="?service_id=<?php echo $service['id']; ?>" 
                                   class="list-group-item list-group-item-action border-0 px-4 py-3 <?php echo ($selected_service == $service['id']) ? 'active-chat' : ''; ?>">
                                    <div class="d-flex align-items-center">
                                        <div class="chat-avatar me-3">
                                            <i class="fas fa-user-circle fa-2x" style="color: var(--gold-brown);"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-0"><?php echo htmlspecialchars($service['customer_name']); ?></h6>
                                            <small class="text-muted"><?php echo htmlspecialchars($service['device']); ?></small>
                                            <br>
                                            <small class="badge bg-<?php 
                                                echo $service['status'] == 'pending' ? 'warning' : 
                                                    ($service['status'] == 'accepted' ? 'info' : 
                                                    ($service['status'] == 'repairing' ? 'primary' : 'success')); 
                                            ?> rounded-pill px-2 mt-1">
                                                <?php echo ucfirst($service['status']); ?>
                                            </small>
                                        </div>
                                        <div class="chat-status">
                                            <i class="fas fa-circle text-success" style="font-size: 0.6rem;"></i>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Chat Area -->
        <div class="col-md-8">
            <?php if ($selected_customer): ?>
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-transparent border-0 pt-4 pb-0">
                        <div class="d-flex align-items-center">
                            <div class="chat-avatar me-3">
                                <i class="fas fa-user-circle fa-2x" style="color: var(--gold-brown);"></i>
                            </div>
                            <div>
                                <h5 class="mb-0"><?php echo htmlspecialchars($selected_customer['customer_name']); ?></h5>
                                <small class="text-muted">
                                    <i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($selected_customer['customer_email']); ?>
                                    <span class="mx-1">•</span>
                                    <i class="fas fa-microchip me-1"></i> <?php echo htmlspecialchars($selected_customer['device']); ?>
                                    <span class="mx-1">•</span>
                                    <span class="badge bg-<?php 
                                        echo $selected_customer['status'] == 'pending' ? 'warning' : 
                                            ($selected_customer['status'] == 'accepted' ? 'info' : 
                                            ($selected_customer['status'] == 'repairing' ? 'primary' : 'success')); 
                                    ?> rounded-pill">
                                        <?php echo ucfirst($selected_customer['status']); ?>
                                    </span>
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-body p-0">
                        <!-- Chat Messages -->
                        <div class="chat-messages p-4" id="chatMessages">
                            <div class="text-center py-5">
                                <div class="spinner-border text-gold" role="status"></div>
                                <p class="text-muted mt-2">Memuat pesan...</p>
                            </div>
                        </div>
                        
                        <!-- Typing Indicator -->
                        <div id="typingIndicator" class="px-4" style="display: none;">
                            <div class="typing-indicator mb-2">
                                <span></span><span></span><span></span>
                                <span class="ms-2 small text-muted">Customer sedang mengetik...</span>
                            </div>
                        </div>
                        
                        <!-- Chat Input -->
                        <div class="chat-input border-top p-4">
                            <form id="chatForm" class="d-flex gap-2">
                                <input type="hidden" id="service_id" value="<?php echo $selected_customer['id']; ?>">
                                <input type="hidden" id="customer_id" value="<?php echo $selected_customer['customer_id']; ?>">
                                <input type="text" id="messageInput" class="form-control rounded-4" placeholder="Tulis balasan Anda..." autocomplete="off">
                                <button type="submit" class="btn btn-gold rounded-4">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </form>
                            <small class="text-muted d-block mt-2">Tekan Enter untuk mengirim pesan</small>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="card border-0 shadow-sm rounded-4 text-center py-5">
                    <div class="card-body">
                        <i class="fas fa-comments fa-4x text-muted mb-3"></i>
                        <h4>Pilih Customer</h4>
                        <p class="text-muted">Pilih customer dari menu di samping untuk mulai berkonsultasi</p>
                        <a href="dashboard.php" class="btn btn-outline-gold rounded-4 mt-3">
                            <i class="fas fa-home me-2"></i> Kembali ke Dashboard
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .text-gold { color: var(--gold-brown); }
    .btn-gold {
        background: var(--gold-brown);
        color: white;
        border: none;
        transition: all 0.3s ease;
    }
    .btn-gold:hover {
        background: var(--medium-brown);
        transform: translateY(-2px);
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
    .active-chat {
        background: rgba(192, 133, 82, 0.1) !important;
        border-left: 3px solid var(--gold-brown) !important;
    }
    .chat-messages {
        height: 450px;
        overflow-y: auto;
        background: #fafafa;
        display: flex;
        flex-direction: column;
    }
    .message {
        margin-bottom: 1rem;
        display: flex;
        animation: fadeIn 0.3s ease;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .message-sent {
        justify-content: flex-end;
    }
    .message-received {
        justify-content: flex-start;
    }
    .message-bubble {
        max-width: 70%;
        padding: 0.8rem 1rem;
        border-radius: 20px;
        position: relative;
        word-wrap: break-word;
    }
    .message-sent .message-bubble {
        background: var(--gold-brown);
        color: white;
        border-bottom-right-radius: 5px;
    }
    .message-received .message-bubble {
        background: white;
        color: var(--dark-brown);
        border-bottom-left-radius: 5px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.1);
    }
    .message-time {
        font-size: 0.6rem;
        margin-top: 0.3rem;
        opacity: 0.7;
    }
    .message-sent .message-time {
        text-align: right;
    }
    .breadcrumb-item + .breadcrumb-item::before {
        content: "›";
        color: var(--medium-brown);
    }
    .typing-indicator {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 0.6rem 1rem;
        background: white;
        border-radius: 20px;
        border-bottom-left-radius: 5px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.1);
    }
    .typing-indicator span {
        width: 8px;
        height: 8px;
        background: var(--gold-brown);
        border-radius: 50%;
        animation: typing 1.4s infinite;
    }
    .typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
    .typing-indicator span:nth-child(3) { animation-delay: 0.4s; }
    @keyframes typing {
        0%, 60%, 100% { transform: translateY(0); opacity: 0.4; }
        30% { transform: translateY(-10px); opacity: 1; }
    }
    
    @media (max-width: 768px) {
        .chat-messages {
            height: 350px;
        }
        .message-bubble {
            max-width: 85%;
        }
    }
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    let lastMessageId = 0;
    let typingCheckInterval;
    
    function loadMessages() {
        let serviceId = $('#service_id').val();
        if (!serviceId) return;
        
        $.get('../api/get_messages.php?service_id=' + serviceId, function(messages) {
            if (messages && messages.length > 0) {
                let latestId = messages[messages.length-1].id;
                if (latestId !== lastMessageId) {
                    renderMessages(messages);
                    lastMessageId = latestId;
                    scrollToBottom();
                }
            } else {
                renderMessages([]);
            }
        }, 'json').fail(function() {
            console.error('Failed to load messages');
        });
    }
    
    function renderMessages(messages) {
        let html = '';
        let currentUserId = <?php echo $_SESSION['user_id']; ?>;
        
        if (!messages || messages.length === 0) {
            html = '<div class="text-center text-muted py-5"><i class="fas fa-comment-dots fa-3x mb-2"></i><br>Belum ada pesan. Mulai chat dengan customer!</div>';
        } else {
            messages.forEach(msg => {
                let isSent = msg.from_user_id == currentUserId;
                html += `
                    <div class="message message-${isSent ? 'sent' : 'received'}">
                        <div class="message-bubble">
                            ${escapeHtml(msg.message)}
                            <div class="message-time">${formatTime(msg.created_at)}</div>
                        </div>
                    </div>
                `;
            });
        }
        
        $('#chatMessages').html(html);
    }
    
    function sendMessage() {
        let message = $('#messageInput').val().trim();
        if (!message) return;
        
        let customerId = $('#customer_id').val();
        let serviceId = $('#service_id').val();
        
        let $input = $('#messageInput');
        let $btn = $('.btn-gold');
        $input.prop('disabled', true);
        $btn.prop('disabled', true);
        
        $.post('../api/send_message.php', {
            to_user_id: customerId,
            message: message,
            service_id: serviceId
        }, function(response) {
            if (response && response.success) {
                $('#messageInput').val('');
                loadMessages();
            } else {
                alert('Gagal mengirim pesan: ' + (response?.error || 'Unknown error'));
            }
        }, 'json').fail(function() {
            alert('Gagal mengirim pesan. Silakan coba lagi.');
        }).always(function() {
            $input.prop('disabled', false);
            $btn.prop('disabled', false);
            $input.focus();
        });
        
        return false;
    }
    
    function scrollToBottom() {
        let chatMessages = document.getElementById('chatMessages');
        if (chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
    }
    
    function formatTime(datetime) {
        let date = new Date(datetime);
        return date.toLocaleTimeString('id-ID', {hour:'2-digit', minute:'2-digit'});
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        let div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function checkTypingStatus() {
        let serviceId = $('#service_id').val();
        if (!serviceId) return;
        
        $.get('../api/typing_status.php?service_id=' + serviceId, function(response) {
            if (response && response.is_typing) {
                $('#typingIndicator').show();
            } else {
                $('#typingIndicator').hide();
            }
        }, 'json');
    }
    
    $(document).ready(function() {
        loadMessages();
        let interval = setInterval(loadMessages, 3000);
        typingCheckInterval = setInterval(checkTypingStatus, 2000);
        
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
        
        $(window).on('beforeunload', function() {
            clearInterval(interval);
            clearInterval(typingCheckInterval);
        });
        
        $('#messageInput').focus();
    });
</script>

<?php include '../includes/footer.php'; ?>