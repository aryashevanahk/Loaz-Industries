<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
redirectIfNotLoggedIn();
redirectIfNotAdmin();

// Get all active chat sessions
$stmt = $pdo->query("
    SELECT DISTINCT session_id, 
           MAX(created_at) as last_message,
           COUNT(CASE WHEN is_read = 0 AND sender_type = 'user' THEN 1 END) as unread_count
    FROM support_chat 
    GROUP BY session_id 
    ORDER BY last_message DESC
");
$sessions = $stmt->fetchAll();

$selected_session = isset($_GET['session']) ? $_GET['session'] : ($sessions[0]['session_id'] ?? '');

// Load messages for selected session
$messages = [];
if ($selected_session) {
    $stmt = $pdo->prepare("
        SELECT * FROM support_chat 
        WHERE session_id = ? 
        ORDER BY created_at ASC
    ");
    $stmt->execute([$selected_session]);
    $messages = $stmt->fetchAll();
    
    // Mark as read
    $stmt = $pdo->prepare("
        UPDATE support_chat SET is_read = 1 
        WHERE session_id = ? AND sender_type = 'user'
    ");
    $stmt->execute([$selected_session]);
}

// Get statistics for sidebar
$stmt = $pdo->query("SELECT COUNT(*) as total_unread FROM support_chat WHERE is_read = 0 AND sender_type = 'user'");
$unread_count = $stmt->fetch()['total_unread'] ?? 0;

// Get pending transactions count for badge
$stmt = $pdo->query("SELECT COUNT(*) as count FROM transactions WHERE payment_status = 'pending_confirmation'");
$pending_transactions = $stmt->fetch()['count'] ?? 0;

// Get pending applications count for badge
$stmt = $pdo->query("SELECT COUNT(*) as count FROM technician_applications WHERE status = 'pending'");
$pending_applications = $stmt->fetch()['count'] ?? 0;

// Get user profile photo for current admin
$user_profile_photo = null;
$stmt = $pdo->prepare("SELECT profile_photo FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_data = $stmt->fetch();
if ($user_data && $user_data['profile_photo']) {
    $user_profile_photo = $user_data['profile_photo'];
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Chat - Admin Panel</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --cream: #FFF8F0;
            --gold-brown: #C08552;
            --medium-brown: #8C5A3C;
            --dark-brown: #4B2E2B;
            --shadow-sm: 0 2px 8px rgba(75, 46, 43, 0.05);
            --shadow-md: 0 4px 16px rgba(75, 46, 43, 0.08);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--cream); height: 100vh; overflow: hidden; }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 280px;
            height: 100vh;
            background: linear-gradient(180deg, var(--dark-brown) 0%, #5C3A36 100%);
            padding: 1.5rem;
            transition: var(--transition);
            z-index: 1000;
            overflow-y: auto;
        }
        
        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 2rem;
            margin-bottom: 2rem;
            border-bottom: 1px solid rgba(255, 248, 240, 0.1);
        }
        
        .brand-icon {
            width: 45px;
            height: 45px;
            background: var(--gold-brown);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .brand-icon i { font-size: 1.3rem; color: white; }
        .brand-text { color: white; }
        .brand-name { font-size: 1.2rem; font-weight: 700; display: block; }
        .brand-sub { font-size: 0.7rem; opacity: 0.7; }
        
        .nav-menu { list-style: none; padding: 0; }
        .nav-item { margin-bottom: 0.5rem; }
        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0.8rem 1rem;
            color: rgba(255, 248, 240, 0.8);
            text-decoration: none;
            border-radius: 12px;
            transition: var(--transition);
        }
        .nav-link:hover, .nav-link.active {
            background: rgba(192, 133, 82, 0.2);
            color: var(--gold-brown);
        }
        .nav-link i { width: 22px; font-size: 1.1rem; }
        
        /* Notification Badge */
        .badge-notif {
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.6rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-left: 8px;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 280px;
            padding: 1rem;
            height: 100vh;
            overflow: hidden;
        }
        
        /* Header */
        .top-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid rgba(192, 133, 82, 0.15);
        }
        
        .page-title h1 {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--dark-brown);
            margin: 0;
        }
        
        .page-title p {
            color: var(--medium-brown);
            font-size: 0.75rem;
            margin: 0;
            margin-top: 0.25rem;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            background: white;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            box-shadow: var(--shadow-sm);
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: var(--gold-brown);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .user-avatar span {
            color: white;
            font-weight: 600;
            font-size: 1rem;
        }
        
        .user-name {
            font-weight: 600;
            color: var(--dark-brown);
            font-size: 0.9rem;
        }
        
        /* Chat Container */
        .chat-wrapper {
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            height: calc(100vh - 80px);
            display: flex;
            flex-direction: column;
        }
        
        .chat-container {
            display: flex;
            flex: 1;
            overflow: hidden;
        }
        
        /* Chat Sidebar */
        .chat-sidebar {
            width: 320px;
            background: #fafafa;
            border-right: 1px solid rgba(192, 133, 82, 0.1);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .chat-sidebar-header {
            padding: 1rem;
            border-bottom: 1px solid rgba(192, 133, 82, 0.1);
            background: white;
            flex-shrink: 0;
        }
        
        .chat-sidebar-header h6 {
            font-weight: 600;
            color: var(--dark-brown);
        }
        
        .chat-sessions-list {
            flex: 1;
            overflow-y: auto;
            padding: 0.5rem;
        }
        
        .chat-session {
            padding: 0.75rem;
            border-radius: 12px;
            margin-bottom: 0.5rem;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: block;
            background: white;
            border: 1px solid rgba(192, 133, 82, 0.08);
        }
        
        .chat-session:hover {
            background: rgba(192, 133, 82, 0.05);
            border-color: rgba(192, 133, 82, 0.15);
        }
        
        .chat-session.active {
            background: rgba(192, 133, 82, 0.1);
            border-left: 3px solid var(--gold-brown);
        }
        
        .session-id {
            font-size: 0.7rem;
            font-weight: 500;
            color: var(--dark-brown);
            margin-bottom: 0.25rem;
            word-break: break-all;
        }
        
        .session-time {
            font-size: 0.6rem;
            color: var(--medium-brown);
        }
        
        .unread-badge {
            background: var(--gold-brown);
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.6rem;
            font-weight: bold;
        }
        
        /* Chat Main Area */
        .chat-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .chat-header {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid rgba(192, 133, 82, 0.1);
            background: white;
            flex-shrink: 0;
        }
        
        .chat-header h6 {
            font-weight: 600;
            color: var(--dark-brown);
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
        
        /* Message Styles */
        .message {
            display: flex;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .message-user {
            justify-content: flex-start;
        }
        
        .message-admin {
            justify-content: flex-end;
        }
        
        .message-bubble {
            max-width: 70%;
            padding: 0.6rem 1rem;
            border-radius: 18px;
            position: relative;
            word-wrap: break-word;
        }
        
        .message-user .message-bubble {
            background: white;
            color: var(--dark-brown);
            border-bottom-left-radius: 4px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        
        .message-admin .message-bubble {
            background: var(--gold-brown);
            color: white;
            border-bottom-right-radius: 4px;
        }
        
        .message-time {
            font-size: 0.55rem;
            margin-top: 0.25rem;
            opacity: 0.7;
        }
        
        .message-user .message-time {
            text-align: left;
        }
        
        .message-admin .message-time {
            text-align: right;
        }
        
        /* Typing Indicator */
        .typing-indicator {
            display: flex;
            align-items: center;
            gap: 4px;
            padding: 0.4rem 0.8rem;
            background: white;
            border-radius: 20px;
            width: fit-content;
            margin-top: 0.5rem;
        }
        
        .typing-indicator span {
            width: 6px;
            height: 6px;
            background: var(--gold-brown);
            border-radius: 50%;
            animation: typing 1.4s infinite ease-in-out;
        }
        
        .typing-indicator span:nth-child(1) { animation-delay: 0s; }
        .typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
        .typing-indicator span:nth-child(3) { animation-delay: 0.4s; }
        
        @keyframes typing {
            0%, 60%, 100% { transform: translateY(0); opacity: 0.4; }
            30% { transform: translateY(-5px); opacity: 1; }
        }
        
        /* Chat Input */
        .chat-input {
            padding: 0.75rem 1rem;
            border-top: 1px solid rgba(192, 133, 82, 0.1);
            background: white;
            flex-shrink: 0;
        }
        
        .chat-input .input-group {
            position: relative;
        }
        
        .btn-emoji {
            background: transparent;
            border: none;
            color: var(--gold-brown);
            font-size: 1.2rem;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .btn-emoji:hover {
            transform: scale(1.1);
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
        
        .empty-chat {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            text-align: center;
            color: var(--medium-brown);
        }
        
        /* Sound Toggle */
        .sound-toggle {
            cursor: pointer;
            transition: var(--transition);
        }
        
        .sound-toggle:hover {
            color: var(--gold-brown);
        }
        
        /* Scrollbar */
        .chat-messages::-webkit-scrollbar,
        .chat-sessions-list::-webkit-scrollbar {
            width: 4px;
        }
        
        .chat-messages::-webkit-scrollbar-track,
        .chat-sessions-list::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        
        .chat-messages::-webkit-scrollbar-thumb,
        .chat-sessions-list::-webkit-scrollbar-thumb {
            background: var(--gold-brown);
            border-radius: 4px;
        }
        
        /* Emoji Picker */
        .emoji-picker {
            position: absolute;
            bottom: 50px;
            left: 0;
            background: white;
            border: 1px solid rgba(192, 133, 82, 0.2);
            border-radius: 12px;
            padding: 0.5rem;
            z-index: 1000;
            box-shadow: var(--shadow-md);
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 0.3rem;
        }
        
        .emoji-picker span {
            cursor: pointer;
            font-size: 1.2rem;
            padding: 0.3rem;
            text-align: center;
            border-radius: 8px;
            transition: var(--transition);
        }
        
        .emoji-picker span:hover {
            background: rgba(192, 133, 82, 0.1);
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                width: 80px;
                padding: 1rem;
            }
            
            .sidebar-brand .brand-text,
            .nav-link span {
                display: none;
            }
            
            .nav-link {
                justify-content: center;
            }
            
            .main-content {
                margin-left: 80px;
            }
            
            .chat-sidebar {
                width: 280px;
            }
        }
        
        @media (max-width: 768px) {
            .chat-sidebar {
                width: 250px;
            }
            
            .message-bubble {
                max-width: 85%;
            }
            
            .session-id {
                font-size: 0.65rem;
            }
        }
        
        @media (max-width: 576px) {
            .chat-sidebar {
                position: absolute;
                left: -280px;
                z-index: 1050;
                transition: left 0.3s ease;
                height: calc(100vh - 80px);
                background: white;
            }
            
            .chat-sidebar.open {
                left: 0;
            }
            
            .sidebar-toggle {
                display: block;
                position: fixed;
                bottom: 20px;
                left: 20px;
                z-index: 1060;
                background: var(--gold-brown);
                color: white;
                border: none;
                border-radius: 50%;
                width: 45px;
                height: 45px;
                box-shadow: var(--shadow-md);
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="brand-icon"><i class="fas fa-microchip"></i></div>
            <div class="brand-text">
                <span class="brand-name">Loaz</span>
                <span class="brand-sub">Admin Panel</span>
            </div>
        </div>
        <ul class="nav-menu">
            <li class="nav-item"><a href="dashboard.php" class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>"><i class="fas fa-chart-line"></i><span>Dashboard</span></a></li>
            <li class="nav-item"><a href="users.php" class="nav-link <?php echo $current_page == 'users.php' ? 'active' : ''; ?>"><i class="fas fa-users"></i><span>Users</span></a></li>
            <li class="nav-item"><a href="technician_applications.php" class="nav-link <?php echo $current_page == 'technician_applications.php' ? 'active' : ''; ?>"><i class="fas fa-briefcase"></i><span>Lamaran Teknisi</span>
                <?php if ($pending_applications > 0): ?>
                    <span class="badge-notif"><?php echo $pending_applications; ?></span>
                <?php endif; ?>
            </a></li>
            <li class="nav-item"><a href="technicians.php" class="nav-link <?php echo $current_page == 'technicians.php' ? 'active' : ''; ?>"><i class="fas fa-user-cog"></i><span>Technicians</span></a></li>
            <li class="nav-item"><a href="services.php" class="nav-link <?php echo $current_page == 'services.php' ? 'active' : ''; ?>"><i class="fas fa-tools"></i><span>Services</span></a></li>
            <li class="nav-item"><a href="parts.php" class="nav-link <?php echo $current_page == 'parts.php' ? 'active' : ''; ?>"><i class="fas fa-microchip"></i><span>Parts</span></a></li>
            <li class="nav-item"><a href="orders.php" class="nav-link <?php echo $current_page == 'orders.php' ? 'active' : ''; ?>"><i class="fas fa-shopping-cart"></i><span>Orders</span></a></li>
            <li class="nav-item"><a href="transactions.php" class="nav-link <?php echo $current_page == 'transactions.php' ? 'active' : ''; ?>"><i class="fas fa-money-bill-wave"></i><span>Transactions</span>
                <?php if ($pending_transactions > 0): ?>
                    <span class="badge-notif"><?php echo $pending_transactions; ?></span>
                <?php endif; ?>
            </a></li>
            <li class="nav-item"><a href="reports.php" class="nav-link <?php echo $current_page == 'reports.php' ? 'active' : ''; ?>"><i class="fas fa-chart-bar"></i><span>Reports</span></a></li>
            <li class="nav-item"><a href="support_chat.php" class="nav-link <?php echo $current_page == 'support_chat.php' ? 'active' : ''; ?>"><i class="fas fa-headset"></i><span>Support Chat</span>
                <?php if ($unread_count > 0): ?>
                    <span class="badge-notif"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </a></li>
            <li class="nav-item"><a href="/loaz_industries/auth/logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
        </ul>
    </aside>
    
    <!-- Mobile Sidebar Toggle -->
    <button class="sidebar-toggle d-md-none" id="sidebarToggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Main Content -->
    <main class="main-content">
        <div class="top-header">
            <div class="page-title">
                <h1>Support Chat</h1>
                <p>Balas pesan dari customer dengan cepat</p>
            </div>
            <div class="user-info">
                <div class="user-detail">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
                    <small style="color: var(--medium-brown);">Administrator</small>
                </div>
                <div class="user-avatar">
                    <?php if ($user_profile_photo && file_exists('../assets/images/users/' . $user_profile_photo)): ?>
                        <img src="../assets/images/users/<?php echo $user_profile_photo; ?>" alt="Profile">
                    <?php else: ?>
                        <span><?php echo strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 1)); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Chat Container -->
        <div class="chat-wrapper">
            <div class="chat-container">
                <!-- Chat Sessions Sidebar -->
                <div class="chat-sidebar" id="chatSidebar">
                    <div class="chat-sidebar-header">
                        <h6 class="mb-0"><i class="fas fa-comments me-2" style="color: var(--gold-brown);"></i>Sesi Chat</h6>
                        <small class="text-muted"><?php echo count($sessions); ?> percakapan</small>
                    </div>
                    <div class="chat-sessions-list" id="chatSessionsList">
                        <?php if (count($sessions) > 0): ?>
                            <?php foreach ($sessions as $session): ?>
                                <a href="?session=<?php echo urlencode($session['session_id']); ?>" 
                                   class="chat-session <?php echo $selected_session == $session['session_id'] ? 'active' : ''; ?>"
                                   data-session="<?php echo htmlspecialchars($session['session_id']); ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <div class="session-id">
                                                <i class="fas fa-user-circle me-1" style="color: var(--gold-brown);"></i>
                                                <?php echo substr($session['session_id'], 0, 25); ?>...
                                            </div>
                                            <div class="session-time">
                                                <i class="fas fa-clock me-1"></i>
                                                <?php echo date('d/m/Y H:i', strtotime($session['last_message'])); ?>
                                            </div>
                                        </div>
                                        <?php if ($session['unread_count'] > 0): ?>
                                            <span class="unread-badge"><?php echo $session['unread_count']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-comment-slash fa-3x mb-3"></i>
                                <p class="mb-0">Belum ada sesi chat</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Chat Area -->
                <div class="chat-main">
                    <?php if ($selected_session): ?>
                        <div class="chat-header">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <div class="chat-avatar me-3">
                                        <i class="fas fa-user-circle fa-2x" style="color: var(--gold-brown);"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0">Customer Support Chat</h6>
                                        <small class="text-muted">
                                            <i class="fas fa-circle text-success me-1" style="font-size: 0.6rem;"></i> 
                                            Online
                                        </small>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fas fa-volume-up sound-toggle" id="soundToggle" style="color: var(--gold-brown); cursor: pointer;"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="chat-messages" id="chatMessages">
                            <?php foreach ($messages as $msg): ?>
                                <div class="message message-<?php echo $msg['sender_type']; ?>">
                                    <div class="message-bubble">
                                        <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                                        <div class="message-time">
                                            <?php echo date('H:i', strtotime($msg['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="chat-input">
                            <form id="chatForm" class="d-flex gap-2">
                                <input type="hidden" id="session_id" value="<?php echo htmlspecialchars($selected_session); ?>">
                                <div class="input-group">
                                    <button type="button" class="btn-emoji" id="emojiBtn">
                                        <i class="far fa-smile-wink"></i>
                                    </button>
                                    <input type="text" id="messageInput" class="form-control rounded-4" 
                                           placeholder="Ketik pesan balasan..." autocomplete="off">
                                    <button type="submit" class="btn btn-gold rounded-4 px-4">
                                        <i class="fas fa-paper-plane me-2"></i> Kirim
                                    </button>
                                </div>
                            </form>
                            <div class="typing-indicator" id="typingIndicator" style="display: none;">
                                <span></span><span></span><span></span>
                                <span class="ms-2 small text-muted">Customer sedang mengetik...</span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="empty-chat">
                            <div>
                                <i class="fas fa-comments fa-4x mb-3" style="color: var(--medium-brown); opacity: 0.5;"></i>
                                <h5>Belum Ada Chat Dipilih</h5>
                                <p class="text-muted">Pilih sesi chat dari sidebar untuk memulai percakapan</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let lastMessageId = 0;
        let sessionId = $('#session_id').val();
        let soundEnabled = localStorage.getItem('chatSound') !== 'false';
        let audio = new Audio('https://www.soundjay.com/misc/sounds/bell-ringing-05.mp3');
        
        // Play sound for new message
        function playSound() {
            if (soundEnabled) {
                audio.play().catch(e => console.log('Sound play failed:', e));
            }
        }
        
        // Toggle sound
        $('#soundToggle').on('click', function() {
            soundEnabled = !soundEnabled;
            localStorage.setItem('chatSound', soundEnabled);
            $(this).css('color', soundEnabled ? 'var(--gold-brown)' : '#ccc');
        });
        
        // Set initial sound icon color
        $('#soundToggle').css('color', soundEnabled ? 'var(--gold-brown)' : '#ccc');
        
        // Auto scroll to bottom
        function scrollToBottom() {
            const messagesDiv = document.getElementById('chatMessages');
            if (messagesDiv) {
                messagesDiv.scrollTop = messagesDiv.scrollHeight;
            }
        }
        
        // Load messages with AJAX
        let lastMessageCount = 0;
        function loadMessages() {
            if (!sessionId) return;
            
            $.get('../api/get_support_messages.php?session_id=' + sessionId, function(messages) {
                if (messages.length > 0) {
                    const lastMessage = messages[messages.length - 1];
                    if (lastMessage.id !== lastMessageId) {
                        // Play sound if new message from user
                        const newMessages = messages.filter(m => m.id > lastMessageId && m.sender_type === 'user');
                        if (newMessages.length > 0 && lastMessageId !== 0) {
                            playSound();
                        }
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
                const isAdmin = msg.sender_type == 'admin';
                html += `
                    <div class="message message-${isAdmin ? 'admin' : 'user'}">
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
            
            const btn = $('#chatForm button[type="submit"]');
            const originalText = btn.html();
            
            btn.html('<i class="fas fa-spinner fa-spin me-2"></i> Mengirim...').prop('disabled', true);
            
            $.post('../api/send_admin_message.php', {
                session_id: sessionId,
                message: message
            }, function(response) {
                if (response.success) {
                    $('#messageInput').val('');
                    loadMessages();
                }
            }, 'json').always(function() {
                btn.html(originalText).prop('disabled', false);
                $('#messageInput').focus();
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
        
        // Simulate typing indicator
        let typingTimeout;
        $('#messageInput').on('input', function() {
            clearTimeout(typingTimeout);
            $('#typingIndicator').show();
            typingTimeout = setTimeout(() => {
                $('#typingIndicator').hide();
            }, 1000);
        });
        
        // Emoji picker
        const emojis = ['😊', '😂', '❤️', '👍', '🙏', '😢', '😡', '🎉', '✅', '❌', '⭐', '🔥', '💯', '🤔', '😎'];
        $('#emojiBtn').on('click', function(e) {
            e.stopPropagation();
            const picker = $('<div class="emoji-picker"></div>');
            emojis.forEach(emoji => {
                picker.append($('<span>' + emoji + '</span>').on('click', function() {
                    $('#messageInput').val($('#messageInput').val() + $(this).text());
                    picker.remove();
                }));
            });
            $(this).after(picker);
            $(document).one('click', function() { picker.remove(); });
        });
        
        // Handle session click
        $(document).on('click', '.chat-session', function(e) {
            e.preventDefault();
            const newSession = $(this).data('session');
            if (newSession) {
                window.location.href = '?session=' + encodeURIComponent(newSession);
            }
        });
        
        // Mobile sidebar toggle
        $('#sidebarToggle').on('click', function() {
            $('#chatSidebar').toggleClass('open');
        });
        
        // Auto refresh every 2 seconds
        $(document).ready(function() {
            loadMessages();
            const interval = setInterval(loadMessages, 2000);
            
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
            
            $('#messageInput').focus();
            scrollToBottom();
            
            $(window).on('beforeunload', function() {
                clearInterval(interval);
            });
            
            // Close sidebar on click outside in mobile
            $(document).on('click', function(e) {
                if ($(window).width() <= 576) {
                    if (!$(e.target).closest('#chatSidebar').length && !$(e.target).closest('#sidebarToggle').length) {
                        $('#chatSidebar').removeClass('open');
                    }
                }
            });
        });
    </script>
</body>
</html>