<?php
// Kill2Earn - Complete PHP Gaming Platform
session_start();

// Database Configuration
$host = 'localhost';
$dbname = 'kill2earn';
$username = 'root';
$password = '12/10/05';

// Initialize PDO
$pdo = null;

// Create database if it doesn't exist
try {
    // First connect without database to create it
    $pdo_temp = new PDO("mysql:host=$host", $username, $password);
    $pdo_temp->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create database if it doesn't exist
    $pdo_temp->exec("CREATE DATABASE IF NOT EXISTS `$dbname`");
    $pdo_temp = null; // Close temporary connection

    // Now connect to the specific database
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Initialize database tables if they don't exist
    initDatabase($pdo);
    // Refresh user balance from database
    refreshUserBalance($pdo);
} catch(PDOException $e) {
    // If connection fails, show error but continue (for demo purposes)
    $db_error = "Database connection failed: " . $e->getMessage();
}

// Refresh user session balance from database
function refreshUserBalance($pdo) {
    if (isset($_SESSION['user'])) {
        $stmt = $pdo->prepare("SELECT wallet_balance FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user']['id']]);
        $balance = $stmt->fetchColumn();
        if ($balance !== false) {
            $_SESSION['user']['wallet_balance'] = $balance;
        }
    }
}

// Initialize database tables
function initDatabase($pdo) {
    // Users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        phone VARCHAR(15) UNIQUE NOT NULL,
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50) NOT NULL,
        game_name VARCHAR(50) NOT NULL,
        game_uid VARCHAR(20) NOT NULL,
        password VARCHAR(255) NOT NULL,
        wallet_balance DECIMAL(10,2) DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    
    // Matches table
    $pdo->exec("CREATE TABLE IF NOT EXISTS matches (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(100) NOT NULL,
        match_date DATETIME NOT NULL,
        entry_fee DECIMAL(10,2) NOT NULL,
        per_kill_reward DECIMAL(10,2) NOT NULL,
        booyah_bonus DECIMAL(10,2) NOT NULL,
        max_players INT DEFAULT 50,
        room_id VARCHAR(50) DEFAULT NULL,
        room_password VARCHAR(50) DEFAULT NULL,
        status ENUM('upcoming', 'live', 'completed') DEFAULT 'upcoming',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // UserMatches table (track users joining matches)
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_matches (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        match_id INT NOT NULL,
        kills INT DEFAULT 0,
        booyah BOOLEAN DEFAULT FALSE,
        earnings DECIMAL(10,2) DEFAULT 0.00,
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (match_id) REFERENCES matches(id)
    )");
    
    // Referrals table
    $pdo->exec("CREATE TABLE IF NOT EXISTS referrals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        referral_code VARCHAR(6) NOT NULL UNIQUE,
        total_referrals INT DEFAULT 0,
        referred_by_user_id INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (referred_by_user_id) REFERENCES users(id)
    )");
    
    // Add referral_code column to users table if it doesn't exist
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN referral_code VARCHAR(6) UNIQUE");
    } catch (PDOException $e) {
        // Column already exists, ignore error
    }
    
    // WalletTransactions table
    $pdo->exec("CREATE TABLE IF NOT EXISTS wallet_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        type ENUM('add', 'withdraw', 'reward', 'entry_fee') NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        description TEXT,
        upi_id VARCHAR(100) DEFAULT NULL,
        payment_screenshot VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");
    
    // Clear any existing sample matches (one-time cleanup)
    $stmt = $pdo->query("SELECT COUNT(*) FROM matches WHERE title IN ('Solo Deathmatch', 'Duo Clash', 'Squad Showdown')");
    if ($stmt->fetchColumn() > 0) {
        $pdo->exec("DELETE FROM user_matches WHERE match_id IN (SELECT id FROM matches WHERE title IN ('Solo Deathmatch', 'Duo Clash', 'Squad Showdown'))");
        $pdo->exec("DELETE FROM matches WHERE title IN ('Solo Deathmatch', 'Duo Clash', 'Squad Showdown')");
    }
    
    // No sample matches will be created - only manually created matches will appear
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'login':
                handleLogin($pdo);
                break;
            case 'signup':
                handleSignup($pdo);
                break;
            case 'join_match':
                handleJoinMatch($pdo);
                break;
            case 'add_money':
                handleAddMoney($pdo);
                break;
            case 'withdraw_money':
                handleWithdrawMoney($pdo);
                break;
            case 'admin_approve':
                handleAdminApprove($pdo);
                break;
            case 'admin_reject':
                handleAdminReject($pdo);
                break;
            case 'admin_login':
                handleAdminLogin($pdo);
                break;
            case 'forgot_password':
                handleForgotPassword($pdo);
                break;
            case 'reset_password':
                handleResetPassword($pdo);
                break;
            case 'admin_save_match':
                handleAdminSaveMatch($pdo);
                break;
            case 'admin_publish_results':
                handleAdminPublishResults($pdo);
                break;
            case 'admin_enter_results':
                handleAdminEnterResults($pdo);
                break;
            case 'admin_save_results':
                handleAdminSaveResults($pdo);
                break;
            case 'admin_generate_results':
                handleAdminGenerateResults($pdo);
                break;
            case 'admin_save_publish_results':
                handleAdminSavePublishResults($pdo);
                break;
            case 'admin_delete_match':
                handleAdminDeleteMatch($pdo);
                break;
            case 'delete_transaction_history':
                handleDeleteTransactionHistory($pdo);
                break;
            case 'update_profile':
                handleUpdateProfile($pdo);
                break;
            case 'clear_generate_results_session':
                unset($_SESSION['generate_results_match']);
                unset($_SESSION['generate_results_users']);
                break;
        }
    }
}

// Handle user login
function handleLogin($pdo) {
    if (!empty($_POST['phone']) && !empty($_POST['password'])) {
        // Check for admin login
        if ($_POST['phone'] === 'Sarthak' && $_POST['password'] === 'Sarthak12/10/2005') {
            // Set admin user session
            $_SESSION['user'] = [
                'id' => 1,
                'phone' => 'Sarthak',
                'first_name' => 'Admin',
                'last_name' => 'User',
                'game_name' => 'Admin',
                'game_uid' => 'admin',
                'wallet_balance' => 0.00,
                'password' => '',
            ];
            header("Location: ".$_SERVER['PHP_SELF']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM users WHERE phone = ?");
        $stmt->execute([$_POST['phone']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($_POST['password'], $user['password'])) {
            $_SESSION['user'] = $user;
            header("Location: ".$_SERVER['PHP_SELF']);
            exit;
        } else {
            $_SESSION['error'] = "Invalid phone number or password";
        }
    }
}

// Handle user signup
function handleSignup($pdo) {
    if (!empty($_POST['phone']) && !empty($_POST['password']) && !empty($_POST['first_name']) && 
        !empty($_POST['last_name']) && !empty($_POST['game_name']) && !empty($_POST['game_uid'])) {
        
        // Check if phone already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
        $stmt->execute([$_POST['phone']]);
        
        if ($stmt->fetch()) {
            $_SESSION['error'] = "Phone number already registered";
            return;
        }
        
        // Check if game_uid already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE game_uid = ?");
        $stmt->execute([$_POST['game_uid']]);
        
        if ($stmt->fetch()) {
            $_SESSION['error'] = "Game UID already registered";
            return;
        }
        
        try {
            $pdo->beginTransaction();
            
            // Generate unique referral code
            $referral_code = generateUniqueReferralCode($pdo);
        
        // Insert new user
            $stmt = $pdo->prepare("INSERT INTO users (phone, first_name, last_name, game_name, game_uid, password, referral_code) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt->execute([
            $_POST['phone'],
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['game_name'],
            $_POST['game_uid'],
                $hashed_password,
                $referral_code
            ]);
            
            $user_id = $pdo->lastInsertId();
            
            // Process referral code if provided
            $referred_by_user_id = null;
            if (!empty($_POST['referral_code'])) {
                $referrer = getUserByReferralCode($pdo, $_POST['referral_code']);
                if ($referrer) {
                    $referred_by_user_id = $referrer['id'];
                }
            }
            
            // Insert referral record
            $stmt = $pdo->prepare("INSERT INTO referrals (user_id, referral_code, referred_by_user_id) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $referral_code, $referred_by_user_id]);
            
            // Update referrer's total referrals count if applicable
            if ($referred_by_user_id) {
                $stmt = $pdo->prepare("UPDATE referrals SET total_referrals = total_referrals + 1 WHERE user_id = ?");
                $stmt->execute([$referred_by_user_id]);
            }
            
            $pdo->commit();
            $_SESSION['success'] = "Account created successfully. Your referral code is: " . $referral_code . ". Please login.";
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Error creating account: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "All fields are required";
    }
}

// Handle joining a match
function handleJoinMatch($pdo) {
    if (isset($_SESSION['user']) && !empty($_POST['match_id'])) {
        $user_id = $_SESSION['user']['id'];
        $match_id = $_POST['match_id'];
        
        // Get match details
        $stmt = $pdo->prepare("SELECT * FROM matches WHERE id = ?");
        $stmt->execute([$match_id]);
        $match = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$match) {
            $_SESSION['error'] = "Match not found";
            return;
        }
        
        // Check if user has already joined this match
        $stmt = $pdo->prepare("SELECT id FROM user_matches WHERE user_id = ? AND match_id = ?");
        $stmt->execute([$user_id, $match_id]);
        
        if ($stmt->fetch()) {
            $_SESSION['error'] = "You have already joined this match";
            return;
        }
        
        // Check if user has sufficient balance
        if ($_SESSION['user']['wallet_balance'] < $match['entry_fee']) {
            $_SESSION['error'] = "Insufficient balance. Please add money to your wallet.";
            return;
        }
        
        try {
            $pdo->beginTransaction();
            
            // Deduct entry fee from user's wallet
            $new_balance = $_SESSION['user']['wallet_balance'] - $match['entry_fee'];
            $stmt = $pdo->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?");
            $stmt->execute([$new_balance, $user_id]);
            
            // Record transaction
            $stmt = $pdo->prepare("INSERT INTO wallet_transactions (user_id, type, amount, description, status) VALUES (?, 'entry_fee', ?, 'Entry fee for match: {$match['title']}', 'approved')");
            $stmt->execute([$user_id, $match['entry_fee']]);
            
            // Add user to match
            $stmt = $pdo->prepare("INSERT INTO user_matches (user_id, match_id) VALUES (?, ?)");
            $stmt->execute([$user_id, $match_id]);
            
            $pdo->commit();
            
            // Update session user balance
            $_SESSION['user']['wallet_balance'] = $new_balance;
            
            $_SESSION['success'] = "Successfully joined match! ₹{$match['entry_fee']} deducted from your wallet.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Error joining match: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Please login to join matches";
    }
}

// Handle adding money to wallet
function handleAddMoney($pdo) {
    if (isset($_SESSION['user'])) {
        $user_id = $_SESSION['user']['id'];
        $amount = 25; // Fixed amount for add money

        // Check if file was uploaded
        if (!isset($_FILES['payment_screenshot']) || $_FILES['payment_screenshot']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = "Please upload a payment screenshot";
            return;
        }

        // Validate file type
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
        $file_type = $_FILES['payment_screenshot']['type'];
        if (!in_array($file_type, $allowed_types)) {
            $_SESSION['error'] = "Only JPG, PNG images are allowed";
            return;
        }

        // Validate file size (max 5MB)
        if ($_FILES['payment_screenshot']['size'] > 5 * 1024 * 1024) {
            $_SESSION['error'] = "File size must be less than 5MB";
            return;
        }

        // Generate unique filename
        $file_extension = pathinfo($_FILES['payment_screenshot']['name'], PATHINFO_EXTENSION);
        $filename = 'payment_' . $user_id . '_' . time() . '.' . $file_extension;
        $upload_path = 'uploads/' . $filename;

        // Move uploaded file
        if (!move_uploaded_file($_FILES['payment_screenshot']['tmp_name'], $upload_path)) {
            $_SESSION['error'] = "Failed to upload screenshot";
            return;
        }

        try {
            // Create a pending transaction with screenshot
            $stmt = $pdo->prepare("INSERT INTO wallet_transactions (user_id, type, amount, description, payment_screenshot) VALUES (?, 'add', ?, 'Deposit request for ₹25', ?)");
            $stmt->execute([$user_id, $amount, $upload_path]);

            $_SESSION['success'] = "Deposit request of ₹25   submitted with payment screenshot. It will be verified and approved by admin.";
        } catch (Exception $e) {
            // Delete uploaded file if database insert fails
            if (file_exists($upload_path)) {
                unlink($upload_path);
            }
            $_SESSION['error'] = "Error processing deposit request: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Please login to add money";
    }
}

// Handle withdrawing money from wallet
function handleWithdrawMoney($pdo) {
    if (isset($_SESSION['user'])) {
        $user_id = $_SESSION['user']['id'];
        $amount = 100; // Fixed withdrawal amount

        // Validate UPI ID
        if (empty($_POST['upi_id']) || empty($_POST['confirm_upi_id'])) {
            $_SESSION['error'] = "UPI ID is required";
            return;
        }

        if ($_POST['upi_id'] !== $_POST['confirm_upi_id']) {
            $_SESSION['error'] = "UPI IDs do not match";
            return;
        }

        // Validate UPI ID format (basic validation)
        $upi_pattern = '/^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+$/';
        if (!preg_match($upi_pattern, $_POST['upi_id'])) {
            $_SESSION['error'] = "Invalid UPI ID format";
            return;
        }

        // Check if user has sufficient balance
        if ($_SESSION['user']['wallet_balance'] < $amount) {
            $_SESSION['error'] = "Insufficient balance";
            return;
        }

        try {
            $pdo->beginTransaction();

            // Deduct amount from user's wallet instantly
            $new_balance = $_SESSION['user']['wallet_balance'] - $amount;
            $stmt = $pdo->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?");
            $stmt->execute([$new_balance, $user_id]);

            // Create pending withdrawal transaction with UPI ID
            $stmt = $pdo->prepare("INSERT INTO wallet_transactions (user_id, type, amount, description, status, upi_id) VALUES (?, 'withdraw', ?, 'Withdrawal request to UPI', 'pending', ?)");
            $stmt->execute([$user_id, $amount, $_POST['upi_id']]);

            $pdo->commit();

            // Update session balance
            $_SESSION['user']['wallet_balance'] = $new_balance;

            $_SESSION['success'] = "₹$amount withdrawal request submitted. It will be processed by admin within 24-48 hours.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Error processing withdrawal request: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Please login to withdraw money";
    }
}

// Handle admin approval
function handleAdminApprove($pdo) {
    if (isset($_SESSION['user']) && $_SESSION['user']['id'] == 1 && !empty($_POST['transaction_id'])) {
        $transaction_id = $_POST['transaction_id'];

        // Get transaction details
        $stmt = $pdo->prepare("SELECT * FROM wallet_transactions WHERE id = ?");
        $stmt->execute([$transaction_id]);
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$transaction) {
            $_SESSION['error'] = "Transaction not found";
            return;
        }

        try {
            $pdo->beginTransaction();

            if ($transaction['type'] == 'add') {
                // Add money to user's wallet
                $stmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
                $stmt->execute([$transaction['amount'], $transaction['user_id']]);

                // Update session balance if it's the current user
                if ($_SESSION['user']['id'] == $transaction['user_id']) {
                    $_SESSION['user']['wallet_balance'] += $transaction['amount'];
                }
                
                // Check for referral bonus (only on first deposit)
                $referral_info = getUserReferralInfo($pdo, $transaction['user_id']);
                if ($referral_info && $referral_info['referred_by_user_id']) {
                    // Check if this is the user's first approved deposit
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM wallet_transactions 
                                         WHERE user_id = ? AND type = 'add' AND status = 'approved' AND id != ?");
                    $stmt->execute([$transaction['user_id'], $transaction_id]);
                    $previous_approved_deposits = $stmt->fetchColumn();
                    
                    if ($previous_approved_deposits == 0) {
                        // This is the first approved deposit, give referral bonus
                        $referrer_id = $referral_info['referred_by_user_id'];
                        
                        // Add ₹5 to referrer's wallet
                        $stmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + 5.00 WHERE id = ?");
                        $stmt->execute([$referrer_id]);
                        
                        // Record the referral bonus transaction
                        $stmt = $pdo->prepare("INSERT INTO wallet_transactions (user_id, type, amount, status, description) VALUES (?, 'add', 5.00, 'approved', 'Referral bonus for referring user')");
                        $stmt->execute([$referrer_id]);
                    }
                }
            } elseif ($transaction['type'] == 'withdraw') {
                // For withdrawals, the money was already deducted when the request was made
                // We just need to mark it as approved - no additional wallet changes needed
            }

            // Update transaction status
            $stmt = $pdo->prepare("UPDATE wallet_transactions SET status = 'approved' WHERE id = ?");
            $stmt->execute([$transaction_id]);

            $pdo->commit();

            $_SESSION['success'] = "Transaction approved successfully";
            header("Location: ".$_SERVER['PHP_SELF']);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Error approving transaction: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Unauthorized action";
    }
}

// Handle admin rejection
function handleAdminReject($pdo) {
    if (isset($_SESSION['user']) && $_SESSION['user']['id'] == 1 && !empty($_POST['transaction_id'])) {
        $transaction_id = $_POST['transaction_id'];

        // Get transaction details
        $stmt = $pdo->prepare("SELECT * FROM wallet_transactions WHERE id = ?");
        $stmt->execute([$transaction_id]);
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$transaction) {
            $_SESSION['error'] = "Transaction not found";
            return;
        }

        try {
            $pdo->beginTransaction();

            if ($transaction['type'] == 'withdraw') {
                // For withdrawals, refund the money back to user's wallet
                $stmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
                $stmt->execute([$transaction['amount'], $transaction['user_id']]);

                // Update session balance if it's the current user
                if ($_SESSION['user']['id'] == $transaction['user_id']) {
                    $_SESSION['user']['wallet_balance'] += $transaction['amount'];
                }
            }
            // For 'add' transactions, just reject without wallet changes

            // Update transaction status
            $stmt = $pdo->prepare("UPDATE wallet_transactions SET status = 'rejected' WHERE id = ?");
            $stmt->execute([$transaction_id]);

            $pdo->commit();

            $_SESSION['success'] = "Transaction rejected successfully";
            header("Location: ".$_SERVER['PHP_SELF']);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Error rejecting transaction: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Unauthorized action";
    }
}

// Handle admin login
function handleAdminLogin($pdo) {
    if (!empty($_POST['phone']) && !empty($_POST['password'])) {
        // Check for admin login
        if ($_POST['phone'] === 'Sarthak' && $_POST['password'] === 'Sarthak12/10/2005') {
            // Set admin user session
            $_SESSION['user'] = [
                'id' => 1,
                'phone' => 'Sarthak',
                'first_name' => 'Admin',
                'last_name' => 'User',
                'game_name' => 'Admin',
                'game_uid' => 'admin',
                'wallet_balance' => 0.00,
                'password' => '',
            ];
            $_SESSION['success'] = "Admin login successful";
            header("Location: ".$_SERVER['PHP_SELF']);
            exit;
        } else {
            $_SESSION['error'] = "Invalid admin credentials";
        }
    } else {
        $_SESSION['error'] = "Please enter both phone and password";
    }
}

// Handle forgot password
function handleForgotPassword($pdo) {
    if (!empty($_POST['phone']) && !empty($_POST['game_uid'])) {
        try {
            $stmt = $pdo->prepare("SELECT id, phone, game_uid FROM users WHERE phone = ? AND game_uid = ?");
            $stmt->execute([$_POST['phone'], $_POST['game_uid']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Store user ID in session for password reset
                $_SESSION['reset_user_id'] = $user['id'];
                $_SESSION['show_reset_modal'] = true;
                $_SESSION['success'] = "Verification successful. Please enter your new password.";
                header("Location: ".$_SERVER['PHP_SELF']);
                exit;
            } else {
                $_SESSION['error'] = "Mobile number and Game UID do not match our records.";
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error verifying credentials: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Please enter both mobile number and Game UID.";
    }
}

// Handle reset password
function handleResetPassword($pdo) {
    if (!empty($_POST['new_password']) && isset($_SESSION['reset_user_id'])) {
        try {
            $hashed_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $_SESSION['reset_user_id']]);
            
            // Clear reset session
            unset($_SESSION['reset_user_id']);
            $_SESSION['success'] = "Password updated successfully. Please login with your new password.";
            header("Location: ".$_SERVER['PHP_SELF']);
            exit;
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error updating password: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Please enter a new password.";
    }
}

// Get matches from database
function getMatches($pdo) {
    $stmt = $pdo->query("SELECT * FROM matches ORDER BY match_date ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get user transactions
function getUserTransactions($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT * FROM wallet_transactions WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get pending transactions for admin
function getPendingTransactions($pdo) {
    $stmt = $pdo->query("SELECT wt.*, u.game_name, u.phone, u.wallet_balance, wt.upi_id FROM wallet_transactions wt
                         JOIN users u ON wt.user_id = u.id
                         WHERE wt.status = 'pending' ORDER BY wt.created_at DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get user match results
function getUserMatchResults($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT m.title, m.match_date, um.kills, um.booyah, um.earnings FROM user_matches um JOIN matches m ON um.match_id = m.id WHERE um.user_id = ? AND m.status = 'completed' ORDER BY m.match_date DESC");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get completed matches for publish result
function getCompletedMatchesForPublish($pdo) {
    $stmt = $pdo->query("SELECT m.*, COUNT(um.id) as joined_players FROM matches m 
                        LEFT JOIN user_matches um ON m.id = um.match_id 
                        WHERE m.status = 'completed' 
                        GROUP BY m.id 
                        ORDER BY m.match_date DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get joined users for a specific match
function getJoinedUsersForMatch($pdo, $match_id) {
    // Include player name, game name and game UID so admin can see full details
    $stmt = $pdo->prepare("SELECT um.*, u.first_name, u.last_name, u.game_name, u.game_uid, u.phone 
                          FROM user_matches um 
                          JOIN users u ON um.user_id = u.id 
                          WHERE um.match_id = ? 
                          ORDER BY u.game_name");
    $stmt->execute([$match_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get total users count
function getTotalUsersCount($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    return $stmt->fetchColumn();
}

// Generate unique referral code
function generateUniqueReferralCode($pdo) {
    do {
        $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM referrals WHERE referral_code = ?");
        $stmt->execute([$code]);
    } while ($stmt->fetchColumn() > 0);
    
    return $code;
}

// Get user by referral code
function getUserByReferralCode($pdo, $referral_code) {
    $stmt = $pdo->prepare("SELECT u.*, r.referral_code FROM users u 
                          JOIN referrals r ON u.id = r.user_id 
                          WHERE r.referral_code = ?");
    $stmt->execute([$referral_code]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get referred users for a specific user
function getReferredUsers($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT u.first_name, u.last_name, u.game_name, u.game_uid, r.created_at 
                          FROM users u 
                          JOIN referrals r ON u.id = r.user_id 
                          WHERE r.referred_by_user_id = ? 
                          ORDER BY r.created_at DESC");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get user's referral info
function getUserReferralInfo($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT * FROM referrals WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get total matches count
function getTotalMatchesCount($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM matches");
    return $stmt->fetchColumn();
}

// Get completed matches count
function getCompletedMatchesCount($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM matches WHERE status = 'completed'");
    return $stmt->fetchColumn();
}

// Check if user has joined a match
function hasUserJoinedMatch($pdo, $user_id, $match_id) {
    $stmt = $pdo->prepare("SELECT id FROM user_matches WHERE user_id = ? AND match_id = ?");
    $stmt->execute([$user_id, $match_id]);
    return $stmt->fetch() ? true : false;
}

// Get the actual number of players joined for a match
function getJoinedPlayerCount($pdo, $match_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM user_matches WHERE match_id = ?");
    $stmt->execute([$match_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'];
}

// Check if match is full
function isMatchFull($pdo, $match_id) {
    $stmt = $pdo->prepare("SELECT max_players FROM matches WHERE id = ?");
    $stmt->execute([$match_id]);
    $match = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$match || !$match['max_players']) {
        return false; // If no max_players set, assume not full
    }

    $joinedCount = getJoinedPlayerCount($pdo, $match_id);
    return $joinedCount >= $match['max_players'];
}

// Handle admin save match (create/edit)
function handleAdminSaveMatch($pdo) {
    if (isset($_SESSION['user']) && $_SESSION['user']['id'] == 1) {
        $match_id = !empty($_POST['match_id']) ? $_POST['match_id'] : null;

        // Validate required fields
        if (empty($_POST['title']) || empty($_POST['match_date']) || !isset($_POST['entry_fee']) ||
            !isset($_POST['per_kill_reward']) || !isset($_POST['booyah_bonus']) || !isset($_POST['max_players'])) {
            $_SESSION['error'] = "All required fields must be filled";
            return;
        }

        try {
            if ($match_id) {
                // Update existing match
                $stmt = $pdo->prepare("UPDATE matches SET
                    title = ?,
                    match_date = ?,
                    entry_fee = ?,
                    per_kill_reward = ?,
                    booyah_bonus = ?,
                    max_players = ?,
                    room_id = ?,
                    room_password = ?,
                    status = ?
                    WHERE id = ?");

                $stmt->execute([
                    $_POST['title'],
                    $_POST['match_date'],
                    $_POST['entry_fee'],
                    $_POST['per_kill_reward'],
                    $_POST['booyah_bonus'],
                    $_POST['max_players'],
                    $_POST['room_id'] ?? null,
                    $_POST['room_password'] ?? null,
                    $_POST['status'],
                    $match_id
                ]);

                $_SESSION['success'] = "Match updated successfully";
            } else {
                // Create new match
                $stmt = $pdo->prepare("INSERT INTO matches
                    (title, match_date, entry_fee, per_kill_reward, booyah_bonus, max_players, room_id, room_password, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

                $stmt->execute([
                    $_POST['title'],
                    $_POST['match_date'],
                    $_POST['entry_fee'],
                    $_POST['per_kill_reward'],
                    $_POST['booyah_bonus'],
                    $_POST['max_players'],
                    $_POST['room_id'] ?? null,
                    $_POST['room_password'] ?? null,
                    $_POST['status']
                ]);

                $_SESSION['success'] = "Match created successfully";
            }

            header("Location: ".$_SERVER['PHP_SELF']);
            exit;
        } catch (Exception $e) {
            $_SESSION['error'] = "Error saving match: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Unauthorized action";
    }
}

// Handle admin enter results (load users for match)
function handleAdminEnterResults($pdo) {
    if (isset($_SESSION['user']) && $_SESSION['user']['id'] == 1 && !empty($_POST['match_id'])) {
        $match_id = $_POST['match_id'];

        // Get match details
        $stmt = $pdo->prepare("SELECT * FROM matches WHERE id = ?");
        $stmt->execute([$match_id]);
        $match = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$match) {
            $_SESSION['error'] = "Match not found";
            return;
        }

        // Get joined users
        $stmt = $pdo->prepare("SELECT um.*, u.game_name FROM user_matches um JOIN users u ON um.user_id = u.id WHERE um.match_id = ?");
        $stmt->execute([$match_id]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Store in session for modal
        $_SESSION['results_match'] = $match;
        $_SESSION['results_users'] = $users;
    } else {
        $_SESSION['error'] = "Unauthorized action";
    }
}

// Handle admin save results
function handleAdminSaveResults($pdo) {
    if (isset($_SESSION['user']) && $_SESSION['user']['id'] == 1 && !empty($_POST['match_id'])) {
        $match_id = $_POST['match_id'];

        // Get match details
        $stmt = $pdo->prepare("SELECT * FROM matches WHERE id = ?");
        $stmt->execute([$match_id]);
        $match = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$match) {
            $_SESSION['error'] = "Match not found";
            return;
        }

        // Get joined users
        $stmt = $pdo->prepare("SELECT um.*, u.game_name FROM user_matches um JOIN users u ON um.user_id = u.id WHERE um.match_id = ?");
        $stmt->execute([$match_id]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        try {
            $pdo->beginTransaction();

            foreach ($users as $user) {
                $user_id = $user['user_id'];
                $kills = (int)($_POST['kills_' . $user_id] ?? 0);
                $booyah = isset($_POST['booyah_' . $user_id]) ? 1 : 0;
                $earnings = $kills * $match['per_kill_reward'] + ($booyah ? $match['booyah_bonus'] : 0);

                // Update user_matches
                $stmt = $pdo->prepare("UPDATE user_matches SET kills = ?, booyah = ?, earnings = ? WHERE user_id = ? AND match_id = ?");
                $stmt->execute([$kills, $booyah, $earnings, $user_id, $match_id]);
            }

            $pdo->commit();

            $_SESSION['success'] = "Results saved successfully";
            header("Location: ".$_SERVER['PHP_SELF']);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Error saving results: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Unauthorized action";
    }
}

// Handle admin generate results (load users for result entry)
function handleAdminGenerateResults($pdo) {
    if (isset($_SESSION['user']) && $_SESSION['user']['id'] == 1 && !empty($_POST['match_id'])) {
        $match_id = $_POST['match_id'];

        // Get match details
        $stmt = $pdo->prepare("SELECT * FROM matches WHERE id = ?");
        $stmt->execute([$match_id]);
        $match = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$match) {
            $_SESSION['error'] = "Match not found";
            return;
        }

        // Get joined users
        $users = getJoinedUsersForMatch($pdo, $match_id);

        // Store in session for modal
        $_SESSION['generate_results_match'] = $match;
        $_SESSION['generate_results_users'] = $users;
        
        $_SESSION['success'] = "Users loaded for result entry";
    } else {
        $_SESSION['error'] = "Unauthorized access";
    }
}

// Handle admin save and publish results
function handleAdminSavePublishResults($pdo) {
    if (isset($_SESSION['user']) && $_SESSION['user']['id'] == 1 && !empty($_POST['match_id'])) {
        $match_id = $_POST['match_id'];
        
        // Debug: Log the data being received
        error_log("Publishing results for match $match_id");
        error_log("POST data: " . print_r($_POST, true));

        // Get match details
        $stmt = $pdo->prepare("SELECT * FROM matches WHERE id = ?");
        $stmt->execute([$match_id]);
        $match = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$match) {
            $_SESSION['error'] = "Match not found";
            return;
        }

        try {
            $pdo->beginTransaction();

            // Process each user's results
            foreach ($_POST['user_results'] as $user_id => $result) {
                $kills = intval($result['kills']);
                $booyah = isset($result['booyah']) ? 1 : 0;
                $earnings = floatval($result['earnings']);

                // Debug: Log the data being processed
                error_log("Processing user $user_id: kills=$kills, booyah=$booyah, earnings=$earnings");

                // Update user_matches table
                $stmt = $pdo->prepare("UPDATE user_matches SET kills = ?, booyah = ?, earnings = ? WHERE user_id = ? AND match_id = ?");
                $stmt->execute([$kills, $booyah, $earnings, $user_id, $match_id]);

                // Add earnings to user's wallet if > 0
                if ($earnings > 0) {
                    // Get current wallet balance for debugging
                    $stmt = $pdo->prepare("SELECT wallet_balance FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $current_balance = $stmt->fetchColumn();
                    
                    // Update user's wallet balance
                    $stmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
                    $result_update = $stmt->execute([$earnings, $user_id]);
                    
                    // Debug: Log wallet update
                    error_log("User $user_id: Old balance=$current_balance, Adding=$earnings, Update result=" . ($result_update ? 'success' : 'failed'));
                    
                    // Verify the update by checking new balance
                    $stmt = $pdo->prepare("SELECT wallet_balance FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $new_balance = $stmt->fetchColumn();
                    error_log("User $user_id: New balance after update=$new_balance");

                    // Record wallet transaction
                    $stmt = $pdo->prepare("INSERT INTO wallet_transactions (user_id, type, amount, description, status) VALUES (?, 'reward', ?, ?, 'approved')");
                    $stmt->execute([$user_id, $earnings, "Match earnings: {$match['title']}"]);
                    
                    // Update session balance if it's the current user
                    if ($_SESSION['user']['id'] == $user_id) {
                        $_SESSION['user']['wallet_balance'] += $earnings;
                        error_log("Session balance updated for user $user_id: " . $_SESSION['user']['wallet_balance']);
                    }
                }
            }

            $pdo->commit();
            
            // Update session wallet balance for current user
            if (isset($_SESSION['user'])) {
                $current_user_id = $_SESSION['user']['id'];
                $stmt = $pdo->prepare("SELECT wallet_balance FROM users WHERE id = ?");
                $stmt->execute([$current_user_id]);
                $updated_balance = $stmt->fetchColumn();
                if ($updated_balance !== false) {
                    $_SESSION['user']['wallet_balance'] = $updated_balance;
                }
            }
            
            // Count total earnings added and verify wallet updates
            $total_earnings = 0;
            $users_updated = 0;
            $wallet_verification = [];
            
            foreach ($_POST['user_results'] as $user_id => $result) {
                $earnings = floatval($result['earnings']);
                if ($earnings > 0) {
                    $total_earnings += $earnings;
                    $users_updated++;
                    
                    // Verify wallet balance was updated
                    $stmt = $pdo->prepare("SELECT wallet_balance FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $final_balance = $stmt->fetchColumn();
                    $wallet_verification[] = "User $user_id: ₹$earnings added, New balance: ₹" . number_format($final_balance, 2);
                }
            }
            
            $verification_text = implode("; ", $wallet_verification);
            $_SESSION['success'] = "Results published successfully! ₹" . number_format($total_earnings, 2) . " added to $users_updated user wallets. Details: $verification_text";
            
            // Clear session data
            unset($_SESSION['generate_results_match']);
            unset($_SESSION['generate_results_users']);
            
            // Redirect to prevent form resubmission
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Error publishing results: " . $e->getMessage();
            error_log("Error in handleAdminSavePublishResults: " . $e->getMessage());
        }
    } else {
        $_SESSION['error'] = "Unauthorized access";
    }
}

// Handle admin delete match
function handleAdminDeleteMatch($pdo) {
    if (isset($_SESSION['user']) && $_SESSION['user']['id'] == 1 && !empty($_POST['match_id'])) {
        $match_id = $_POST['match_id'];

        try {
            $pdo->beginTransaction();

            // First, delete related user_matches records
            $stmt = $pdo->prepare("DELETE FROM user_matches WHERE match_id = ?");
            $stmt->execute([$match_id]);

            // Then delete the match
            $stmt = $pdo->prepare("DELETE FROM matches WHERE id = ?");
            $stmt->execute([$match_id]);

            $pdo->commit();
            $_SESSION['success'] = "Match deleted successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Error deleting match: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Unauthorized access";
    }
}

// Handle delete transaction history
function handleDeleteTransactionHistory($pdo) {
    if (isset($_SESSION['user'])) {
        $user_id = $_SESSION['user']['id'];

        try {
            $pdo->beginTransaction();

            // Delete all transaction history for the user
            $stmt = $pdo->prepare("DELETE FROM wallet_transactions WHERE user_id = ?");
            $stmt->execute([$user_id]);

            $pdo->commit();
            $_SESSION['success'] = "All transaction history deleted successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Error deleting transaction history: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Please login to delete transaction history";
    }
}

// Handle update profile
function handleUpdateProfile($pdo) {
    if (isset($_SESSION['user'])) {
        $user_id = $_SESSION['user']['id'];
        
        try {
            $pdo->beginTransaction();
            
            // Update user profile
            $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, game_name = ? WHERE id = ?");
            $stmt->execute([
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['game_name'],
                $user_id
            ]);
            
            // Update session data
            $_SESSION['user']['first_name'] = $_POST['first_name'];
            $_SESSION['user']['last_name'] = $_POST['last_name'];
            $_SESSION['user']['game_name'] = $_POST['game_name'];
            
            $pdo->commit();
            $_SESSION['success'] = "Profile updated successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Error updating profile: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Please login to update profile";
    }
}

// Handle admin publish results
function handleAdminPublishResults($pdo) {
    if (isset($_SESSION['user']) && $_SESSION['user']['id'] == 1 && !empty($_POST['match_id'])) {
        $match_id = $_POST['match_id'];

        // Get match details
        $stmt = $pdo->prepare("SELECT * FROM matches WHERE id = ?");
        $stmt->execute([$match_id]);
        $match = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$match) {
            $_SESSION['error'] = "Match not found";
            return;
        }

        // Get joined users with earnings
        $stmt = $pdo->prepare("SELECT um.*, u.game_name FROM user_matches um JOIN users u ON um.user_id = u.id WHERE um.match_id = ? AND um.earnings > 0");
        $stmt->execute([$match_id]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        try {
            $pdo->beginTransaction();

            foreach ($users as $user) {
                $user_id = $user['user_id'];
                $earnings = $user['earnings'];

                // Credit to wallet
                $stmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
                $stmt->execute([$earnings, $user_id]);

                // Record transaction
                $stmt = $pdo->prepare("INSERT INTO wallet_transactions (user_id, type, amount, description, status) VALUES (?, 'reward', ?, 'Match reward for {$match['title']}', 'approved')");
                $stmt->execute([$user_id, $earnings]);
            }

            $pdo->commit();

            $_SESSION['success'] = "Results published and rewards credited successfully";
            header("Location: ".$_SERVER['PHP_SELF']);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Error publishing results: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Unauthorized action";
    }
}

// Logout function
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: ".$_SERVER['PHP_SELF']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kill2Earn - Free Fire Tournament Platform</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <meta property="og:title" content="Kill2Earn - Free Fire Tournament Platform" />
  <meta property="og:description" content="Join tournaments, earn per kill 15₹ + 50₹ Booyah Bonus." />
  <meta property="og:image" content="kill2earn_logo.png" />
  <meta property="og:url" content="https://kill2earn.gamer.gd" />
  <meta name="twitter:card" content="summary_large_image">
  <!-- PWA Support -->
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#ff5722">
<link rel="apple-touch-icon" href="/icons/icon-192.png">

    <style>
        :root {
            --primary: #4a044e;
            --secondary: #9d4edd;
            --accent: #f72585;
            --dark: #1a1a2e;
            --light: #f8f9fa;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, var(--dark) 0%, #16213e 100%);
            color: var(--light);
            min-height: 100vh;
        }
        
        .card {
            background: rgba(26, 26, 46, 0.8);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .btn-primary {
            background: linear-gradient(90deg, var(--secondary) 0%, var(--accent) 100%);
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(157, 78, 221, 0.4);
        }
        
        .nav-link {
            position: relative;
        }
        
        .nav-link::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -4px;
            left: 0;
            background: var(--secondary);
            transition: width 0.3s ease;
        }
        
        .nav-link:hover::after {
            width: 100%;
        }
        
        .match-card {
            transition: all 0.3s ease;
            border-left: 4px solid var(--secondary);
        }
        
        .match-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.3);
        }
        
        .wallet-card {
            background: linear-gradient(135deg, #4a044e 0%, #3a0ca3 100%);
        }
        
        .stats-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 1.5rem;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .form-input {
            background: rgba(255, 255, 255, 0.07);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(157, 78, 221, 0.3);
        }
        
        .mobile-menu {
            transform: translateX(-100%);
            transition: transform 0.3s ease;
        }
        
        .mobile-menu.open {
            transform: translateX(0);
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 100;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            width: 90%;
            max-width: 500px;
            background: var(--dark);
            border-radius: 12px;
            overflow: hidden;
        }
        
        .payment-option {
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .payment-option.selected {
            border-color: var(--secondary);
            background: rgba(157, 78, 221, 0.1);
        }
        
        .admin-panel {
            display: none;
        }
        
        .admin-panel.active {
            display: block;
        }
        
        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 1rem 2rem;
            background: var(--secondary);
            color: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.3s ease;
            z-index: 1000;
        }
        
        .toast.show {
            transform: translateY(0);
            opacity: 1;
        }
        
        /* Mobile-first responsive adjustments */
        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
        }

        /* Transaction History Show/Hide */
        .transaction-row.hidden {
            display: none;
        }

        /* Global sizing adjustments for 100% zoom */
        .container {
            max-width: 1200px;
        }
        
        .card {
            padding: 1.25rem;
            margin-bottom: 1.25rem;
        }
        
        .match-card {
            padding: 1.25rem;
        }
        
        .modal-content {
            max-width: 450px;
            padding: 0;
        }
        
        .modal-content.max-w-md {
            max-width: 450px;
        }
        
        .text-3xl {
            font-size: 1.5rem;
        }
        
        .text-2xl {
            font-size: 1.25rem;
        }
        
        .text-xl {
            font-size: 1.125rem;
        }
        
        .p-6 {
            padding: 1.25rem;
        }
        
        .p-4 {
            padding: 1rem;
        }
        
        .mb-6 {
            margin-bottom: 1.25rem;
        }
        
        .mb-4 {
            margin-bottom: 1rem;
        }
        
        .space-y-4 > * + * {
            margin-top: 1rem;
        }
        
        .space-y-3 > * + * {
            margin-top: 0.75rem;
        }
        
        .space-y-2 > * + * {
            margin-top: 0.5rem;
        }
        
        .grid {
            gap: 1rem;
        }
        
        .grid-cols-3 {
            gap: 0.75rem;
        }
        
        button, .btn-primary, .amount-btn {
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
        }
        
        .tab-btn {
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
        }
        
        .form-input {
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
        }
        
        .flex.space-x-4 > * + * {
            margin-left: 0.75rem;
        }
        
        .flex.space-x-2 > * + * {
            margin-left: 0.5rem;
        }

        /* Mobile-specific improvements */
        @media (max-width: 768px) {
            .modal-content {
                margin: 5px;
                max-height: 98vh;
                border-radius: 8px;
                width: calc(100% - 10px);
                max-width: calc(100% - 10px);
            }
            
            .modal {
                padding: 5px;
            }
            
            .card {
                margin-bottom: 1rem;
                padding: 1rem;
            }
            
            .match-card {
                padding: 1rem;
            }
            
            .grid {
                gap: 0.75rem;
            }
            
            .grid-cols-3 {
                grid-template-columns: repeat(3, 1fr);
                gap: 0.5rem;
            }
            
            .text-3xl {
                font-size: 1.875rem;
            }
            
            .text-2xl {
                font-size: 1.5rem;
            }
            
            .text-xl {
                font-size: 1.25rem;
            }
            
            .p-6 {
                padding: 1rem;
            }
            
            .p-4 {
                padding: 0.75rem;
            }
            
            .mb-6 {
                margin-bottom: 1rem;
            }
            
            .mb-4 {
                margin-bottom: 0.75rem;
            }
            
            .space-y-4 > * + * {
                margin-top: 0.75rem;
            }
            
            .space-y-3 > * + * {
                margin-top: 0.5rem;
            }
            
            .space-y-2 > * + * {
                margin-top: 0.5rem;
            }
            
            .overflow-x-auto {
                -webkit-overflow-scrolling: touch;
            }
            
            .max-h-96 {
                max-height: 20rem;
            }
            
            /* Touch-friendly buttons */
            button, .btn-primary, .amount-btn {
                min-height: 44px;
                min-width: 44px;
            }
            
            /* Better touch targets */
            .tab-btn {
                padding: 0.75rem 1rem;
                min-height: 44px;
            }
            
            /* Improved form inputs */
            .form-input {
                min-height: 44px;
                font-size: 16px;
            }
            
            /* Better spacing for mobile */
            .flex.space-x-4 > * + * {
                margin-left: 0.5rem;
            }
            
            .flex.space-x-2 > * + * {
                margin-left: 0.25rem;
            }

            /* Mobile navigation improvements */
            .mobile-menu {
                width: 100%;
                max-width: 300px;
            }

            /* Modal improvements for mobile */
            .modal-content.max-w-md {
                max-width: 95%;
            }

            /* Better table responsiveness */
            .overflow-x-auto table {
                min-width: 600px;
            }
        }

        /* Prevent horizontal scroll on mobile */
        @media (max-width: 640px) {
            body {
                overflow-x: hidden;
            }
            
            .container {
                padding-left: 1rem;
                padding-right: 1rem;
            }

            /* Stack grid items on very small screens */
            .grid-cols-1.md\\:grid-cols-2 {
                grid-template-columns: 1fr;
            }

            .grid-cols-1.md\\:grid-cols-3 {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="min-h-screen flex flex-col">
    <!-- Toast Notification -->
    <?php if (isset($_SESSION['error'])): ?>
        <div class="toast show bg-red-600">
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="toast show bg-green-600">
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <!-- Navigation -->
    <nav class="bg-gray-900 border-b border-gray-800 sticky top-0 z-10">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center py-3">
                <div class="flex items-center space-x-3">
                    <button onclick="goBack()" class="md:hidden text-white hover:text-gray-300 text-xl">
                        <i class="fas fa-arrow-left"></i>
                    </button>
                    <h1 class="text-2xl font-bold text-white">
                        <span class="text-purple-500">Kill</span><span class="text-pink-500">2Earn</span>
                    </h1>
                </div>
                
                <!-- Desktop Navigation -->
                <div class="hidden md:flex space-x-8">
                    <a href="#" class="nav-link text-white font-medium" data-page="dashboard">Dashboard</a>
                    <a href="#" class="nav-link text-gray-400 hover:text-white font-medium" data-page="matches">Matches</a>
                    <a href="#" class="nav-link text-gray-400 hover:text-white font-medium" data-page="wallet">Wallet</a>
                    <a href="#" class="nav-link text-gray-400 hover:text-white font-medium" data-page="support">Support</a>
                    <?php if (isset($_SESSION['user']) && $_SESSION['user']['id'] == 1): ?>
                        <a href="#" class="nav-link text-gray-400 hover:text-white font-medium" data-page="admin">Admin Panel</a>
                    <?php endif; ?>
                </div>
                
                <!-- User Profile -->
                <?php if (isset($_SESSION['user'])): ?>
                    <div class="flex items-center space-x-4">
                        <div class="hidden md:flex flex-col items-end">
                            <span class="text-white font-medium"><?php echo $_SESSION['user']['game_name']; ?></span>
                            <span class="text-gray-400 text-sm">₹<?php echo number_format($_SESSION['user']['wallet_balance'], 2); ?></span>
                        </div>
                        <div class="relative">
                            <button id="userMenuButton" class="flex items-center focus:outline-none">
                            <div class="w-10 h-10 rounded-full bg-purple-600 flex items-center justify-center text-white font-bold"><?php echo substr($_SESSION['user']['game_name'], 0, 2); ?></div>
                            </button>
                            <div id="userMenu" class="absolute right-0 mt-2 w-48 bg-gray-800 rounded-lg shadow-lg py-2 hidden">
                                <a href="#" onclick="openProfileModal()" class="block px-4 py-2 text-white hover:bg-gray-700">Profile</a>
                                <a href="#" onclick="navigateToReferral()" class="block px-4 py-2 text-white hover:bg-gray-700">Referral</a>
                                <a href="?logout=1" class="block px-4 py-2 text-white hover:bg-gray-700 border-t border-gray-700">Logout</a>
                            </div>
                        </div>
                        
                        <!-- Mobile Menu Button -->
                        <button id="mobileMenuButton" class="md:hidden text-white focus:outline-none">
                            <i class="fas fa-bars text-xl"></i>
                        </button>
                    </div>
                <?php else: ?>
                    <div>
                        <a href="#" class="text-white" onclick="document.getElementById('loginModal').classList.add('active')">Login</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    
    <!-- Mobile Menu -->
    <div id="mobileMenu" class="mobile-menu fixed inset-0 bg-gray-900 z-20 p-6 md:hidden">
        <div class="flex justify-between items-center mb-10">
            <h1 class="text-2xl font-bold text-white">
                <span class="text-purple-500">Kill</span><span class="text-pink-500">2Earn</span>
            </h1>
            <button id="closeMobileMenu" class="text-white">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <div class="flex flex-col space-y-6">
            <a href="#" class="text-white text-lg font-medium py-2 border-b border-gray-800" data-page="dashboard">Dashboard</a>
            <a href="#" class="text-gray-400 text-lg font-medium py-2 border-b border-gray-800" data-page="matches">Matches</a>
            <a href="#" class="text-gray-400 text-lg font-medium py-2 border-b border-gray-800" data-page="wallet">Wallet</a>
            <a href="#" class="text-gray-400 text-lg font-medium py-2 border-b border-gray-800" data-page="support">Support</a>
            <?php if (isset($_SESSION['user']) && $_SESSION['user']['id'] == 1): ?>
                <a href="#" class="text-gray-400 text-lg font-medium py-2 border-b border-gray-800" data-page="admin">Admin Panel</a>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['user'])): ?>
                <div class="pt-10">
                    <div class="flex items-center space-x-4 mb-4">
                        <?php if (!empty($_SESSION['user']['profile_image']) && file_exists($_SESSION['user']['profile_image'])): ?>
                            <img src="<?php echo $_SESSION['user']['profile_image']; ?>" alt="Profile" class="w-12 h-12 rounded-full object-cover">
                        <?php else: ?>
                            <div class="w-12 h-12 rounded-full bg-purple-600 flex items-center justify-center text-white font-bold"><?php echo substr($_SESSION['user']['game_name'], 0, 2); ?></div>
                        <?php endif; ?>
                        <div>
                            <span class="text-white font-medium block"><?php echo $_SESSION['user']['game_name']; ?></span>
                            <span class="text-gray-400 text-sm">₹<?php echo number_format($_SESSION['user']['wallet_balance'], 2); ?></span>
                        </div>
                    </div>
                    <a href="#" onclick="openProfileModal()" class="block text-white py-2">Profile</a>
                    <a href="#" class="block text-white py-2">Referral</a>
                    <a href="?logout=1" class="block text-white py-2 border-t border-gray-800 mt-4 pt-4">Logout</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main Content -->
    <main class="flex-grow container mx-auto px-4 py-6">
        <!-- Login/Signup Prompt -->
        <?php if (!isset($_SESSION['user'])): ?>
            <div class="card p-6 text-center">
                <h2 class="text-2xl font-bold text-white mb-3">Welcome to Kill2Earn</h2>
                <p class="text-gray-300 mb-4">Join Free Fire tournaments, earn money for each kill, and win booyah bonuses!</p>
                <div class="flex justify-center space-x-4">
                    <button class="btn-primary px-6 py-2 rounded-lg text-white font-medium" onclick="document.getElementById('loginModal').classList.add('active')">Login</button>
                    <button class="bg-gray-700 px-6 py-2 rounded-lg text-white font-medium hover:bg-gray-600 transition" onclick="document.getElementById('signupModal').classList.add('active')">Sign Up</button>
                </div>
            </div>
        <?php else: ?>
            <!-- Dashboard Page -->
            <div id="dashboardPage" class="page active">
                <!-- Dashboard Header -->
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-white">Matches</h2>
                    <div class="flex items-center space-x-2 text-sm text-green-400 bg-green-900 bg-opacity-20 px-3 py-1 rounded-full">
                        <i class="fas fa-wallet"></i>
                        <span>₹<?php echo number_format($_SESSION['user']['wallet_balance'], 2); ?></span>
                    </div>
                </div>
                
                
                <!-- Tabs -->
                <div class="border-b border-gray-700 mb-4">
                    <div class="flex space-x-4">
                        <button class="tab-btn py-2 px-4 text-white font-medium border-b-2 border-purple-500" data-tab="upcoming">Upcoming Matches</button>
                        <button class="tab-btn py-2 px-4 text-gray-400 font-medium" data-tab="live">Live Matches</button>
                        <button class="tab-btn py-2 px-4 text-gray-400 font-medium" data-tab="results">Match Results</button>
                        <button class="tab-btn py-2 px-4 text-gray-400 font-medium" data-tab="referral">Referral</button>
                    </div>
                </div>
                
                <!-- Tab Contents -->
                <div id="upcoming" class="tab-content active">
                    <h3 class="text-xl font-bold text-white mb-3">Upcoming Matches</h3>
                    
                    <!-- Match Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php 
                        $matches = getMatches($pdo);
                        foreach ($matches as $match): 
                            if ($match['status'] !== 'upcoming') continue;
                            $hasJoined = hasUserJoinedMatch($pdo, $_SESSION['user']['id'], $match['id']);
                        ?>
                            <div class="match-card card p-5">
                                <div class="flex justify-between items-start mb-4">
                                    <div>
                                        <h4 class="text-lg font-bold text-white"><?php echo $match['title']; ?></h4>
                                        <p class="text-gray-400 text-sm"><?php echo date('M j, g:i A', strtotime($match['match_date'])); ?></p>
                                    </div>
                                    <span class="bg-purple-900 text-purple-300 text-xs font-medium px-2 py-1 rounded">
                                        <?php echo strpos($match['title'], 'Solo') !== false ? 'Solo' : (strpos($match['title'], 'Duo') !== false ? 'Duo' : 'Squad'); ?>
                                    </span>
                                </div>
                                
                                <div class="flex justify-between items-center mb-4">
                                    <div>
                                        <span class="text-gray-400 text-sm">Entry Fee</span>
                                        <p class="text-white font-medium">₹<?php echo $match['entry_fee']; ?></p>
                                    </div>
                                    <div>
                                        <span class="text-gray-400 text-sm">Per Kill</span>
                                        <p class="text-white font-medium">₹<?php echo $match['per_kill_reward']; ?></p>
                                    </div>
                                    <div>
                                        <span class="text-gray-400 text-sm">Booyah</span>
                                        <p class="text-white font-medium">₹<?php echo $match['booyah_bonus']; ?></p>
                                    </div>
                                </div>
                                
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-400 text-sm"><?php echo getJoinedPlayerCount($pdo, $match['id']); ?>/<?php echo $match['max_players']; ?> players</span>
                                    <?php if ($hasJoined): ?>
                                        <span class="px-4 py-2 rounded-lg bg-green-900 text-green-300 font-medium">Joined</span>
                                    <?php elseif (isMatchFull($pdo, $match['id'])): ?>
                                        <span class="px-4 py-2 rounded-lg bg-red-900 text-red-300 font-medium">Match Full</span>
                                    <?php else: ?>
                                        <form method="POST" class="m-0">
                                            <input type="hidden" name="action" value="join_match">
                                            <input type="hidden" name="match_id" value="<?php echo $match['id']; ?>">
                                            <button type="submit" class="join-match-btn px-4 py-2 rounded-lg text-white font-medium">Join Match</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div id="live" class="tab-content">
                    <h3 class="text-xl font-bold text-white mb-4">Live Matches</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php 
                        $matches = getMatches($pdo);
                        foreach ($matches as $match): 
                            if ($match['status'] !== 'live') continue;
                            $hasJoined = hasUserJoinedMatch($pdo, $_SESSION['user']['id'], $match['id']);
                        ?>
                            <div class="match-card card p-5">
                                <div class="flex justify-between items-start mb-4">
                                    <div>
                                        <h4 class="text-lg font-bold text-white"><?php echo $match['title']; ?></h4>
                                        <p class="text-gray-400 text-sm"><?php echo date('M j, g:i A', strtotime($match['match_date'])); ?></p>
                                    </div>
                                    <span class="bg-purple-900 text-purple-300 text-xs font-medium px-2 py-1 rounded">
                                        <?php echo strpos($match['title'], 'Solo') !== false ? 'Solo' : (strpos($match['title'], 'Duo') !== false ? 'Duo' : 'Squad'); ?>
                                    </span>
                                </div>
                                
                                <div class="flex justify-between items-center mb-4">
                                    <div>
                                        <span class="text-gray-400 text-sm">Entry Fee</span>
                                        <p class="text-white font-medium">₹<?php echo $match['entry_fee']; ?></p>
                                    </div>
                                    <div>
                                        <span class="text-gray-400 text-sm">Per Kill</span>
                                        <p class="text-white font-medium">₹<?php echo $match['per_kill_reward']; ?></p>
                                    </div>
                                    <div>
                                        <span class="text-gray-400 text-sm">Booyah</span>
                                        <p class="text-white font-medium">₹<?php echo $match['booyah_bonus']; ?></p>
                                    </div>
                                </div>
                                
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-400 text-sm"><?php echo getJoinedPlayerCount($pdo, $match['id']); ?>/<?php echo $match['max_players']; ?> players</span>
                                    <?php if ($hasJoined): ?>
                                        <span class="px-4 py-2 rounded-lg bg-green-900 text-green-300 font-medium inline-flex items-center space-x-2">
                                            <span>Joined</span>
                                            <button onclick="showRoomDetails('<?php echo htmlspecialchars(addslashes($match['room_id'])); ?>', '<?php echo htmlspecialchars(addslashes($match['room_password'])); ?>')" class="text-purple-400 underline text-sm hover:text-purple-600 focus:outline-none">Details</button>
                                        </span>
                                    <?php elseif (isMatchFull($pdo, $match['id'])): ?>
                                        <span class="px-4 py-2 rounded-lg bg-red-900 text-red-300 font-medium">Match Full</span>
                                    <?php else: ?>
                                        <form method="POST" class="m-0">
                                            <input type="hidden" name="action" value="join_match">
                                            <input type="hidden" name="match_id" value="<?php echo $match['id']; ?>">
                                            <button type="submit" class="join-match-btn px-4 py-2 rounded-lg text-white font-medium">Join Match</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div id="results" class="tab-content">
                    <h3 class="text-xl font-bold text-white mb-4">Match Results</h3>
                    
                    <?php 
                    $userResults = getUserMatchResults($pdo, $_SESSION['user']['id']);
                    if (empty($userResults)): 
                    ?>
                        <div class="text-center py-8">
                            <p class="text-gray-400">No match results available yet.</p>
                            <p class="text-gray-500 text-sm mt-2">Your results will appear here after matches are completed and results are published.</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full text-white">
                                <thead class="bg-gray-800">
                                    <tr>
                                        <th class="px-4 py-3 text-left">Match</th>
                                        <th class="px-4 py-3 text-left">Date</th>
                                        <th class="px-4 py-3 text-left">Kills</th>
                                        <th class="px-4 py-3 text-left">Booyah</th>
                                        <th class="px-4 py-3 text-left">Earnings</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-700">
                                    <?php foreach ($userResults as $result): ?>
                                    <tr>
                                        <td class="px-4 py-3"><?php echo htmlspecialchars($result['title']); ?></td>
                                        <td class="px-4 py-3"><?php echo date('M j, Y', strtotime($result['match_date'])); ?></td>
                                        <td class="px-4 py-3"><?php echo $result['kills']; ?></td>
                                        <td class="px-4 py-3">
                                            <?php if ($result['booyah']): ?>
                                                <span class="text-green-400">Yes</span>
                                            <?php else: ?>
                                                <span class="text-gray-400">No</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 text-green-400">₹<?php echo number_format($result['earnings'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Referral Tab -->
                <div id="referral" class="tab-content">
                    <h3 class="text-xl font-bold text-white mb-4">Referral System</h3>
                    
                    <?php 
                    $user_referral_info = getUserReferralInfo($pdo, $_SESSION['user']['id']);
                    $referred_users = getReferredUsers($pdo, $_SESSION['user']['id']);
                    
                    // If user doesn't have referral info, create it
                    if (!$user_referral_info) {
                        try {
                            $pdo->beginTransaction();
                            
                            // Generate unique referral code
                            $referral_code = generateUniqueReferralCode($pdo);
                            
                            // Update user's referral_code in users table
                            $stmt = $pdo->prepare("UPDATE users SET referral_code = ? WHERE id = ?");
                            $stmt->execute([$referral_code, $_SESSION['user']['id']]);
                            
                            // Insert referral record
                            $stmt = $pdo->prepare("INSERT INTO referrals (user_id, referral_code) VALUES (?, ?)");
                            $stmt->execute([$_SESSION['user']['id'], $referral_code]);
                            
                            $pdo->commit();
                            
                            // Refresh the referral info
                            $user_referral_info = getUserReferralInfo($pdo, $_SESSION['user']['id']);
                        } catch (Exception $e) {
                            $pdo->rollBack();
                            $_SESSION['error'] = "Error creating referral code: " . $e->getMessage();
                        }
                    }
                    ?>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <!-- Your Referral Code -->
                        <div class="card p-6">
                            <h4 class="text-lg font-bold text-white mb-4">Your Referral Code</h4>
                            <div class="bg-gray-800 p-4 rounded-lg mb-4">
                                <div class="text-center">
                                    <p class="text-3xl font-bold text-purple-400 mb-2 cursor-pointer hover:text-purple-300 transition-colors" 
                                       onclick="copyReferralCode('<?php echo $user_referral_info['referral_code']; ?>')" 
                                       title="Click to copy referral code">
                                        <?php echo $user_referral_info['referral_code']; ?>
                                    </p>
                                    <p class="text-gray-400 text-sm">Click on code to copy or share with friends</p>
                                </div>
                            </div>
                            <button onclick="copyReferralCode('<?php echo $user_referral_info['referral_code']; ?>')" class="btn-primary w-full py-2 rounded-lg text-white font-medium">
                                Copy Referral Code
                            </button>
                        </div>
                        
                        <!-- Referral Stats -->
                        <div class="card p-6">
                            <h4 class="text-lg font-bold text-white mb-4">Referral Stats</h4>
                            <div class="space-y-4">
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-300">Total Referrals:</span>
                                    <span class="text-white font-bold"><?php echo $user_referral_info['total_referrals']; ?></span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-300">Earnings from Referrals:</span>
                                    <span class="text-green-400 font-bold">₹<?php echo number_format($user_referral_info['total_referrals'] * 5, 2); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- How Referral System Works -->
                    <div class="card p-6 mb-6">
                        <h4 class="text-lg font-bold text-white mb-4">How Referral System Works</h4>
                        <div class="space-y-3 text-gray-300">
                            <div class="flex items-start space-x-3">
                                <span class="text-purple-400 font-bold">1.</span>
                                <p>Share your referral code with friends</p>
                            </div>
                            <div class="flex items-start space-x-3">
                                <span class="text-purple-400 font-bold">2.</span>
                                <p>When they sign up using your code, they become your referral</p>
                            </div>
                            <div class="flex items-start space-x-3">
                                <span class="text-purple-400 font-bold">3.</span>
                                <p>You earn ₹5 when they add money for the first time</p>
                            </div>
                            <div class="flex items-start space-x-3">
                                <span class="text-purple-400 font-bold">4.</span>
                                <p>Bonus is credited automatically after admin approval</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Referred Users List -->
                    <div class="card p-6">
                        <h4 class="text-lg font-bold text-white mb-4">Your Referred Users</h4>
                        <?php if (empty($referred_users)): ?>
                            <div class="text-center py-8">
                                <p class="text-gray-400">No referrals yet.</p>
                                <p class="text-gray-500 text-sm mt-2">Share your referral code to start earning!</p>
                            </div>
                        <?php else: ?>
                            <div class="overflow-x-auto">
                                <table class="w-full text-white">
                                    <thead class="bg-gray-800">
                                        <tr>
                                            <th class="px-4 py-3 text-left">Name</th>
                                            <th class="px-4 py-3 text-left">Game Name</th>
                                            <th class="px-4 py-3 text-left">Game UID</th>
                                            <th class="px-4 py-3 text-left">Joined Date</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-700">
                                        <?php foreach ($referred_users as $user): ?>
                                        <tr>
                                            <td class="px-4 py-3"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                            <td class="px-4 py-3"><?php echo htmlspecialchars($user['game_name']); ?></td>
                                            <td class="px-4 py-3"><?php echo htmlspecialchars($user['game_uid']); ?></td>
                                            <td class="px-4 py-3"><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Wallet Page -->
            <div id="walletPage" class="page">
                <h2 class="text-2xl font-bold text-white mb-8">Wallet</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="wallet-card card p-6 col-span-1">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-bold text-white">Current Balance</h3>
                            <i class="fas fa-wallet text-2xl text-purple-300"></i>
                        </div>
                        <p class="text-3xl font-bold text-white mb-2">₹<?php echo number_format($_SESSION['user']['wallet_balance'], 2); ?></p>
                        <p class="text-gray-400 text-sm">Last updated: Just now</p>
                    </div>
                    
                    <div class="card p-6 col-span-1 md:col-span-2">
                        <h3 class="text-lg font-bold text-white mb-4">Quick Actions</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <button class="btn-primary py-3 rounded-lg text-white font-medium" onclick="document.getElementById('addMoneyModal').classList.add('active')">Add Money</button>
                            <button class="bg-gray-700 py-3 rounded-lg text-white font-medium hover:bg-gray-600 transition" onclick="document.getElementById('withdrawModal').classList.add('active')">Withdraw Money</button>
                        </div>
                    </div>
                </div>
                
                <div class="card p-6">
                    <h3 class="text-lg font-bold text-white mb-4">Transaction History</h3>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full text-white">
                            <thead class="bg-gray-800">
                                <tr>
                                    <th class="px-4 py-3 text-left">Date</th>
                                    <th class="px-4 py-3 text-left">Description</th>
                                    <th class="px-4 py-3 text-left">Amount</th>
                                    <th class="px-4 py-3 text-left">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-700">
                                <?php
                                $transactions = getUserTransactions($pdo, $_SESSION['user']['id']);
                                $count = 0;
                                foreach ($transactions as $transaction):
                                    $rowClass = $count < 3 ? 'transaction-row visible' : 'transaction-row hidden';
                                    $count++;
                                ?>
                                    <tr class="<?php echo $rowClass; ?>">
                                        <td class="px-4 py-3"><?php echo date('M j, Y', strtotime($transaction['created_at'])); ?></td>
                                        <td class="px-4 py-3"><?php echo $transaction['description']; ?></td>
                                        <td class="px-4 py-3 <?php echo $transaction['type'] == 'add' || $transaction['type'] == 'reward' ? 'text-green-400' : 'text-red-400'; ?>">
                                            <?php echo ($transaction['type'] == 'add' || $transaction['type'] == 'reward' ? '+' : '-') . '₹' . $transaction['amount']; ?>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="
                                                <?php
                                                if ($transaction['status'] == 'approved') echo 'bg-green-900 text-green-300';
                                                elseif ($transaction['status'] == 'rejected') echo 'bg-red-900 text-red-300';
                                                else echo 'bg-yellow-900 text-yellow-300';
                                                ?>
                                                text-xs font-medium px-2 py-1 rounded">
                                                <?php echo ucfirst($transaction['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 text-center">
                        <button id="viewMoreBtn" class="btn-primary px-6 py-2 rounded-lg text-white font-medium mr-3">View More</button>
                        <button id="viewLessBtn" class="bg-gray-700 px-6 py-2 rounded-lg text-white font-medium hover:bg-gray-600 transition mr-3" style="display:none;">View Less</button>
                        <form method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to delete all your transaction history? This action cannot be undone.')">
                            <input type="hidden" name="action" value="delete_transaction_history">
                            <button type="submit" class="bg-red-600 text-white px-6 py-2 rounded-lg text-white font-medium hover:bg-red-700 transition">Delete Transaction History</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Support Page -->
            <div id="supportPage" class="page">
                <h2 class="text-2xl font-bold text-white mb-8">Support</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div class="card p-6">
                        <h3 class="text-lg font-bold text-white mb-4">Contact Us</h3>
                        <p class="text-gray-400 mb-6">Have questions or need help? Reach out to our support team through any of these channels:</p>
                        
                        <div class="space-y-4">
                            <a href="https://wa.me/917265953656" class="flex items-center p-3 bg-gray-800 rounded-lg hover:bg-gray-700 transition">
                                <div class="w-10 h-10 rounded-full bg-green-600 flex items-center justify-center text-white mr-3">
                                    <i class="fab fa-whatsapp"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-white">WhatsApp Support</p>
                                    <p class="text-gray-400 text-sm">+91 72659 53656</p>
                                </div>
                            </a>
                            
                            <a href="https://t.me/kill2earn_freefire_tournament" class="flex items-center p-3 bg-gray-800 rounded-lg hover:bg-gray-700 transition">
                                <div class="w-10 h-10 rounded-full bg-blue-500 flex items-center justify-center text-white mr-3">
                                    <i class="fab fa-telegram"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-white">Telegram Channel</p>
                                    <p class="text-gray-400 text-sm">@Kill2EarnSupport</p>
                                </div>
                            </a>
                            
                            <a href="mailto:patelsarthak133@gmail.com" class="flex items-center p-3 bg-gray-800 rounded-lg hover:bg-gray-700 transition">
                                <div class="w-10 h-10 rounded-full bg-red-500 flex items-center justify-center text-white mr-3">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-white">Email Support</p>
                                    <p class="text-gray-400 text-sm">patelsarthak133@gmail.com</p>
                                </div>
                            </a>
                        </div>
                    </div>
                    
                    <div class="card p-6">
                        <h3 class="text-lg font-bold text-white mb-4">FAQ</h3>
                        
                        <div class="space-y-4">
                            <div class="bg-gray-800 p-4 rounded-lg">
                                <p class="font-medium text-white mb-2">How do I join a match?</p>
                                <p class="text-gray-400 text-sm">Go to the Matches section, select a match you want to join, pay the entry fee, and you'll receive room details before the match starts.</p>
                            </div>
                            
                            <div class="bg-gray-800 p-4 rounded-lg">
                                <p class="font-medium text-white mb-2">When will I receive my earnings?</p>
                                <p class="text-gray-400 text-sm">Earnings are credited to your wallet within 24 hours after the match ends, once kills are verified by our admin team.</p>
                            </div>
                            
                            <div class="bg-gray-800 p-4 rounded-lg">
                                <p class="font-medium text-white mb-2">How can I withdraw money?</p>
                                <p class="text-gray-400 text-sm">You can request a withdrawal from your wallet. Withdrawals are processed manually within 24-48 hours by our admin team.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Admin Panel -->
            <?php if (isset($_SESSION['user']) && $_SESSION['user']['id'] == 1): ?>
                <div id="adminPage" class="page admin-panel">
                    <h2 class="text-2xl font-bold text-white mb-8">Admin Panel</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <div class="card p-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-bold text-white">Total Users</h3>
                                <i class="fas fa-users text-2xl text-purple-300"></i>
                            </div>
                            <p class="text-3xl font-bold text-white"><?php echo number_format(getTotalUsersCount($pdo)); ?></p>
                        </div>
                        
                        <div class="card p-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-bold text-white">Pending Requests</h3>
                                <i class="fas fa-clock text-2xl text-yellow-300"></i>
                            </div>
                            <p class="text-3xl font-bold text-white"><?php 
                                $pending = getPendingTransactions($pdo);
                                echo count($pending);
                            ?></p>
                        </div>
                        
                        <div class="card p-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-bold text-white">Completed Matches</h3>
                                <i class="fas fa-gamepad text-2xl text-green-300"></i>
                            </div>
                            <p class="text-3xl font-bold text-white"><?php echo number_format(getCompletedMatchesCount($pdo)); ?></p>
                        </div>
            </div>
            
            <div class="card p-6 mb-8">
                <h3 class="text-lg font-bold text-white mb-4">Pending Transactions</h3>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-white">
                        <thead class="bg-gray-800">
                            <tr>
                                <th class="px-4 py-3 text-left">User</th>
                                <th class="px-4 py-3 text-left">Type</th>
                                <th class="px-4 py-3 text-left">Amount</th>
                                <th class="px-4 py-3 text-left">UPI ID</th>
                                <th class="px-4 py-3 text-left">Available Balance</th>
                                <th class="px-4 py-3 text-left">Requested</th>
                                <th class="px-4 py-3 text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            <?php foreach ($pending as $transaction): ?>
                                <tr>
                                    <td class="px-4 py-3"><?php echo $transaction['game_name']; ?><br><span class="text-gray-400 text-sm"><?php echo $transaction['phone']; ?></span></td>
                                    <td class="px-4 py-3"><?php echo ucfirst($transaction['type']); ?></td>
                                    <td class="px-4 py-3">₹<?php echo $transaction['amount']; ?></td>
                                    <td class="px-4 py-3"><?php echo $transaction['type'] == 'withdraw' && !empty($transaction['upi_id']) ? $transaction['upi_id'] : 'N/A'; ?></td>
                                    <td class="px-4 py-3">₹<?php echo number_format($transaction['wallet_balance'], 2); ?></td>
                                    <td class="px-4 py-3"><?php echo date('M j, g:i A', strtotime($transaction['created_at'])); ?></td>
                                    <td class="px-4 py-3">
                                        <?php if ($transaction['type'] == 'add' && !empty($transaction['payment_screenshot'])): ?>
                                            <button onclick="showScreenshotModal('<?php echo $transaction['payment_screenshot']; ?>', '<?php echo $transaction['game_name']; ?>')" class="bg-blue-600 text-white px-3 py-1 rounded text-sm mr-2">View Screenshot</button>
                                        <?php endif; ?>
                                        <form method="POST" class="inline-block">
                                            <input type="hidden" name="action" value="admin_approve">
                                            <input type="hidden" name="transaction_id" value="<?php echo $transaction['id']; ?>">
                                            <button type="submit" class="bg-green-600 text-white px-3 py-1 rounded text-sm mr-2">Approve</button>
                                        </form>
                                        <form method="POST" class="inline-block">
                                            <input type="hidden" name="action" value="admin_reject">
                                            <input type="hidden" name="transaction_id" value="<?php echo $transaction['id']; ?>">
                                            <button type="submit" class="bg-red-600 text-white px-3 py-1 rounded text-sm">Reject</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Admin Matches Management -->
        <div class="card p-6 mb-8">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-white">Manage Matches</h3>
                <button id="createMatchBtn" class="btn-primary px-4 py-2 rounded-lg text-white font-medium">Create Match</button>
            </div>

            <?php
            $allMatches = getMatches($pdo);
            if (empty($allMatches)):
            ?>
                <div class="text-center py-8">
                    <p class="text-gray-400 text-lg mb-4">No matches found</p>
                    <p class="text-gray-500 text-sm mb-6">Create your first match to get started!</p>
                    <button id="createMatchBtn" class="btn-primary px-6 py-2 rounded-lg text-white font-medium">Create Your First Match</button>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto mb-4">
                    <table class="w-full text-white">
                        <thead class="bg-gray-800">
                            <tr>
                                <th class="px-4 py-3 text-left">Title</th>
                                <th class="px-4 py-3 text-left">Date & Time</th>
                                <th class="px-4 py-3 text-left">Entry Fee</th>
                                <th class="px-4 py-3 text-left">Per Kill Reward</th>
                                <th class="px-4 py-3 text-left">Booyah Bonus</th>
                                <th class="px-4 py-3 text-left">Room ID</th>
                                <th class="px-4 py-3 text-left">Room Password</th>
                                <th class="px-4 py-3 text-left">Status</th>
                                <th class="px-4 py-3 text-left">Players</th>
                                <th class="px-4 py-3 text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            foreach ($allMatches as $m):
                                $joinedCount = getJoinedPlayerCount($pdo, $m['id']);
                                // For upcoming matches, fetch joined players so admin can see details
                                $joinedUsers = [];
                                if ($m['status'] === 'upcoming') {
                                    $joinedUsers = getJoinedUsersForMatch($pdo, $m['id']);
                                }
                            ?>
                            <tr>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($m['title']); ?></td>
                                <td class="px-4 py-3"><?php echo date('Y-m-d H:i', strtotime($m['match_date'])); ?></td>
                                <td class="px-4 py-3">₹<?php echo $m['entry_fee']; ?></td>
                                <td class="px-4 py-3">₹<?php echo $m['per_kill_reward']; ?></td>
                                <td class="px-4 py-3">₹<?php echo $m['booyah_bonus']; ?></td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($m['room_id']); ?></td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($m['room_password']); ?></td>
                                <td class="px-4 py-3"><?php echo ucfirst($m['status']); ?></td>
                                <td class="px-4 py-3"><?php echo $joinedCount; ?>/<?php echo $m['max_players']; ?></td>
                                <td class="px-4 py-3">
                                    <button class="editMatchBtn bg-yellow-600 text-white px-3 py-1 rounded text-sm mr-2" data-match='<?php echo json_encode($m); ?>'>Edit</button>
                                    <form method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to delete this match? This action cannot be undone.')">
                                        <input type="hidden" name="action" value="admin_delete_match">
                                        <input type="hidden" name="match_id" value="<?php echo $m['id']; ?>">
                                        <button type="submit" class="bg-red-600 text-white px-3 py-1 rounded text-sm">Delete</button>
                                    </form>
                                </td>
                            </tr>
                            <?php if ($m['status'] === 'upcoming'): ?>
                                <tr class="bg-gray-900">
                                    <td colspan="10" class="px-4 py-3">
                                        <h4 class="text-sm font-semibold text-white mb-2">Joined Players (Upcoming Match)</h4>
                                        <?php if (empty($joinedUsers)): ?>
                                            <p class="text-gray-400 text-sm">No players have joined this match yet.</p>
                                        <?php else: ?>
                                            <div class="overflow-x-auto">
                                                <table class="w-full text-xs md:text-sm text-white border border-gray-800">
                                                    <thead class="bg-gray-800">
                                                        <tr>
                                                            <th class="px-3 py-2 text-left">#</th>
                                                            <th class="px-3 py-2 text-left">Name</th>
                                                            <th class="px-3 py-2 text-left">Game Name</th>
                                                            <th class="px-3 py-2 text-left">Game UID</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-gray-800">
                                                        <?php $i = 1; foreach ($joinedUsers as $ju): ?>
                                                            <tr>
                                                                <td class="px-3 py-2"><?php echo $i++; ?></td>
                                                                <td class="px-3 py-2">
                                                                    <?php echo htmlspecialchars(trim(($ju['first_name'] ?? '') . ' ' . ($ju['last_name'] ?? ''))); ?>
                                                                </td>
                                                                <td class="px-3 py-2"><?php echo htmlspecialchars($ju['game_name'] ?? ''); ?></td>
                                                                <td class="px-3 py-2"><?php echo htmlspecialchars($ju['game_uid'] ?? ''); ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Publish Results Section -->
        <div class="card p-6 mb-8">
            <h3 class="text-lg font-bold text-white mb-4">Publish Results</h3>
            <p class="text-gray-400 mb-4">Select a completed match to publish results and add earnings to user wallets.</p>
            
            <div class="overflow-x-auto">
                <table class="w-full text-white">
                    <thead class="bg-gray-800">
                        <tr>
                            <th class="px-4 py-3 text-left">Match Name</th>
                            <th class="px-4 py-3 text-left">Date & Time</th>
                            <th class="px-4 py-3 text-left">Joined Players</th>
                            <th class="px-4 py-3 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">
                        <?php 
                        $completedMatches = getCompletedMatchesForPublish($pdo);
                        foreach ($completedMatches as $match): 
                        ?>
                        <tr>
                            <td class="px-4 py-3"><?php echo htmlspecialchars($match['title']); ?></td>
                            <td class="px-4 py-3"><?php echo date('Y-m-d H:i', strtotime($match['match_date'])); ?></td>
                            <td class="px-4 py-3"><?php echo $match['joined_players']; ?></td>
                            <td class="px-4 py-3">
                                <form method="POST" class="inline-block">
                                    <input type="hidden" name="action" value="admin_generate_results">
                                    <input type="hidden" name="match_id" value="<?php echo $match['id']; ?>">
                                    <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded text-sm hover:bg-green-700 transition">
                                        Generate Result
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
        <?php endif; ?>
    </main>
    
    <!-- Footer -->
    <footer class="bg-gray-900 border-t border-gray-800 py-8">
        <div class="container mx-auto px-4">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="mb-4 md:mb-0">
                    <h2 class="text-xl font-bold text-white">
                        <span class="text-purple-500">Kill</span><span class="text-pink-500">2Earn</span>
                    </h2>
                    <p class="text-gray-400 text-sm">Free Fire Tournament Platform</p>
                </div>
                
                <div class="flex space-x-6">
                    <a href="#" class="text-gray-400 hover:text-white">
                        <i class="fab fa-whatsapp text-lg"></i>
                    </a>
                    <a href="#" class="text-gray-400 hover:text-white">
                        <i class="fab fa-telegram text-lg"></i>
                    </a>
                    <a href="#" class="text-gray-400 hover:text-white">
                        <i class="fas fa-envelope text-lg"></i>
                    </a>
                </div>
            </div>
            
            <div class="border-t border-gray-800 mt-8 pt-8 text-center text-gray-400 text-sm">
                <p>© 2025 Kill2Earn. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Room Details Modal -->
    <div id="roomDetailsModal" class="modal">
        <div class="modal-content">
            <div class="bg-purple-900 p-4 flex justify-between items-center">
                <h3 class="text-xl font-bold text-white">Room Details</h3>
                <button onclick="document.getElementById('roomDetailsModal').classList.remove('active')" class="text-white hover:text-gray-300">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div class="p-6 text-white">
                <p><strong>Room ID:</strong> <span id="roomIdText"></span></p>
                <p><strong>Room Password:</strong> <span id="roomPasswordText"></span></p>
            </div>
            <div class="p-6 text-center">
                <button onclick="document.getElementById('roomDetailsModal').classList.remove('active')" class="btn-primary px-6 py-2 rounded-lg text-white font-medium">Close</button>
            </div>
        </div>
    </div>
    <div id="matchModal" class="modal">
        <div class="modal-content">
            <div class="bg-purple-900 p-4 flex justify-between items-center">
                <h3 id="modalTitle" class="text-xl font-bold text-white">Create Match</h3>
                <button onclick="closeMatchModal()" class="text-white hover:text-gray-300">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div class="p-6">
                <form id="matchForm" method="POST">
                    <input type="hidden" name="action" value="admin_save_match">
                    <input type="hidden" name="match_id" id="match_id" value="">
                    <div class="mb-4">
                        <label class="block text-gray-300 mb-2" for="title">Title</label>
                        <input type="text" name="title" id="title" class="form-input w-full" placeholder="Match Title" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-300 mb-2" for="match_date">Date & Time</label>
                        <input type="datetime-local" name="match_date" id="match_date" class="form-input w-full" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-300 mb-2" for="entry_fee">Entry Fee (₹)</label>
                        <input type="number" name="entry_fee" id="entry_fee" class="form-input w-full" min="0" step="0.01" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-300 mb-2" for="per_kill_reward">Per Kill Reward (₹)</label>
                        <input type="number" name="per_kill_reward" id="per_kill_reward" class="form-input w-full" min="0" step="0.01" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-300 mb-2" for="booyah_bonus">Booyah Bonus (₹)</label>
                        <input type="number" name="booyah_bonus" id="booyah_bonus" class="form-input w-full" min="0" step="0.01" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-300 mb-2" for="max_players">Max Players</label>
                        <input type="number" name="max_players" id="max_players" class="form-input w-full" min="1" max="100" value="50" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-300 mb-2" for="room_id">Room ID</label>
                        <input type="text" name="room_id" id="room_id" class="form-input w-full" placeholder="Room ID">
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-300 mb-2" for="room_password">Room Password</label>
                        <input type="text" name="room_password" id="room_password" class="form-input w-full" placeholder="Room Password">
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-300 mb-2" for="status">Status</label>
                        <select name="status" id="status" class="form-input w-full" required>
                            <option value="upcoming">Upcoming</option>
                            <option value="live">Live</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                    <div class="flex justify-end space-x-4">
                        <button type="button" onclick="closeMatchModal()" class="bg-gray-700 px-6 py-2 rounded-lg text-white font-medium hover:bg-gray-600 transition">Cancel</button>
                        <button type="submit" class="btn-primary px-6 py-2 rounded-lg text-white font-medium">Save Match</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Profile Edit Modal -->
    <div id="profileModal" class="modal">
        <div class="modal-content">
            <div class="bg-purple-900 p-4 flex justify-between items-center">
                <h3 class="text-xl font-bold text-white">Edit Profile</h3>
                <button onclick="closeProfileModal()" class="text-white hover:text-gray-300">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div class="p-6">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="mb-4 text-center">
                        <div class="w-20 h-20 rounded-full bg-purple-600 flex items-center justify-center text-white font-bold text-2xl mx-auto mb-2">
                            <?php echo substr($_SESSION['user']['game_name'], 0, 2); ?>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-300 mb-2" for="first_name">First Name</label>
                        <input type="text" name="first_name" id="first_name" value="<?php echo htmlspecialchars($_SESSION['user']['first_name']); ?>" class="form-input w-full" required>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-300 mb-2" for="last_name">Last Name</label>
                        <input type="text" name="last_name" id="last_name" value="<?php echo htmlspecialchars($_SESSION['user']['last_name']); ?>" class="form-input w-full" required>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-300 mb-2" for="game_name">Game Name</label>
                        <input type="text" name="game_name" id="game_name" value="<?php echo htmlspecialchars($_SESSION['user']['game_name']); ?>" class="form-input w-full" required>
                    </div>
                    
                    <div class="flex justify-end space-x-4">
                        <button type="button" onclick="closeProfileModal()" class="bg-gray-700 px-6 py-2 rounded-lg text-white font-medium hover:bg-gray-600 transition">Cancel</button>
                        <button type="submit" class="btn-primary px-6 py-2 rounded-lg text-white font-medium">Update Profile</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Admin Panel: Create/Edit Match Modal Logic
        const matchModal = document.getElementById('matchModal');
        const matchForm = document.getElementById('matchForm');
        const modalTitle = document.getElementById('modalTitle');

        document.getElementById('createMatchBtn').addEventListener('click', () => {
            modalTitle.textContent = 'Create Match';
            matchForm.reset();
            document.getElementById('match_id').value = '';
            matchModal.classList.add('active');
        });

        function closeMatchModal() {
            matchModal.classList.remove('active');
        }

        document.querySelectorAll('.editMatchBtn').forEach(button => {
            button.addEventListener('click', () => {
                const matchData = JSON.parse(button.getAttribute('data-match'));
                modalTitle.textContent = 'Edit Match';
                document.getElementById('match_id').value = matchData.id;
                document.getElementById('title').value = matchData.title;
                // Format datetime-local input value
                const dt = new Date(matchData.match_date);
                const localISOTime = dt.toISOString().slice(0,16);
                document.getElementById('match_date').value = localISOTime;
                document.getElementById('entry_fee').value = matchData.entry_fee;
                document.getElementById('per_kill_reward').value = matchData.per_kill_reward;
                document.getElementById('booyah_bonus').value = matchData.booyah_bonus;
                document.getElementById('room_id').value = matchData.room_id || '';
                document.getElementById('room_password').value = matchData.room_password || '';
                document.getElementById('status').value = matchData.status;
                matchModal.classList.add('active');
            });
        });

        // Show Room Details Modal
        function showRoomDetails(roomId, roomPassword) {
            document.getElementById('roomIdText').textContent = roomId || 'N/A';
            document.getElementById('roomPasswordText').textContent = roomPassword || 'N/A';
            document.getElementById('roomDetailsModal').classList.add('active');
        }
    </script>

    <!-- Login Modal -->
    <div id="loginModal" class="modal <?php echo !isset($_SESSION['user']) ? 'active' : ''; ?>">
        <div class="modal-content max-w-md mx-auto">
            <div class="bg-purple-900 p-4 flex justify-between items-center">
                <h3 class="text-xl font-bold text-white">Login to Your Account</h3>
                <button onclick="closeLoginModal()" class="text-white hover:text-gray-300 text-2xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-6">
                <form method="POST">
                    <input type="hidden" name="action" value="login">
                    <div class="mb-4">
                        <label class="block text-gray-300 mb-2">Phone Number</label>
                        <input type="tel" name="phone" class="form-input w-full" placeholder="Enter your phone number" required>
                    </div>
                    <div class="mb-6">
                        <label class="block text-gray-300 mb-2">Password</label>
                        <input type="password" name="password" class="form-input w-full" placeholder="Enter your password" required>
                    </div>
                    <button type="submit" class="btn-primary w-full py-3 rounded-lg text-white font-medium">Login</button>
                </form>
                <p class="text-gray-400 text-center mt-4">
                    <a href="#" class="text-purple-400" onclick="document.getElementById('loginModal').classList.remove('active'); document.getElementById('forgotPasswordModal').classList.add('active');">Forgot Password?</a>
                </p>
                <p class="text-gray-400 text-center mt-2">
                    Don't have an account? <a href="#" class="text-purple-400" onclick="document.getElementById('loginModal').classList.remove('active'); document.getElementById('signupModal').classList.add('active');">Sign up</a>
                </p>
            </div>
        </div>
    </div>

    <!-- Signup Modal -->
    <div id="signupModal" class="modal">
        <div class="modal-content max-w-md mx-auto">
            <div class="bg-purple-900 p-4 flex justify-between items-center">
                <h3 class="text-xl font-bold text-white">Create Account</h3>
                <button onclick="closeSignupModal()" class="text-white hover:text-gray-300 text-2xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-6">
                <form method="POST">
                    <input type="hidden" name="action" value="signup">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-gray-300 mb-2">First Name</label>
                            <input type="text" name="first_name" class="form-input w-full" placeholder="First Name" required>
                        </div>
                        <div>
                            <label class="block text-gray-300 mb-2">Last Name</label>
                            <input type="text" name="last_name" class="form-input w-full" placeholder="Last Name" required>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-300 mb-2">Phone Number</label>
                        <input type="tel" name="phone" class="form-input w-full" placeholder="Phone Number" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-300 mb-2">Your Name In Game</label>
                        <input type="text" name="game_name" class="form-input w-full" placeholder="Your Name In Game" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-300 mb-2">Game UID</label>
                        <input type="text" name="game_uid" class="form-input w-full" placeholder="Game UID" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-300 mb-2">Create Password</label>
                        <input type="password" name="password" class="form-input w-full" placeholder="Create Password" required>
                    </div>
                    <div class="mb-6">
                        <label class="block text-gray-300 mb-2">Referral Code (Optional)</label>
                        <input type="text" name="referral_code" class="form-input w-full" placeholder="Enter referral code if you have one" maxlength="6">
                        <p class="text-gray-400 text-sm mt-1">Get ₹5 bonus when your referred friend adds money for the first time!</p>
                    </div>
                    <button type="submit" class="btn-primary w-full py-3 rounded-lg text-white font-medium">Create Account</button>
                </form>
                <p class="text-gray-400 text-center mt-4">
                    Already have an account? <a href="#" class="text-purple-400" onclick="document.getElementById('signupModal').classList.remove('active'); document.getElementById('loginModal').classList.add('active');">Login</a>
                </p>
            </div>
        </div>
    </div>

    <!-- Forgot Password Modal -->
    <div id="forgotPasswordModal" class="modal">
        <div class="modal-content max-w-md mx-auto">
            <div class="bg-purple-900 p-4 flex justify-between items-center">
                <h3 class="text-xl font-bold text-white">Forgot Password</h3>
                <button onclick="closeForgotPasswordModal()" class="text-white hover:text-gray-300 text-2xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-6">
                <form method="POST">
                    <input type="hidden" name="action" value="forgot_password">
                    <div class="mb-4">
                        <label class="block text-gray-300 mb-2">Mobile Number</label>
                        <input type="tel" name="phone" class="form-input w-full" placeholder="Enter your mobile number" required>
                    </div>
                    <div class="mb-6">
                        <label class="block text-gray-300 mb-2">Game UID</label>
                        <input type="text" name="game_uid" class="form-input w-full" placeholder="Enter your Game UID" required>
                    </div>
                    <button type="submit" class="btn-primary w-full py-3 rounded-lg text-white font-medium">Verify</button>
                </form>
                <p class="text-gray-400 text-center mt-4">
                    Remember your password? <a href="#" class="text-purple-400" onclick="document.getElementById('forgotPasswordModal').classList.remove('active'); document.getElementById('loginModal').classList.add('active');">Login</a>
                </p>
            </div>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div id="resetPasswordModal" class="modal <?php echo isset($_SESSION['show_reset_modal']) ? 'active' : ''; ?>">
        <div class="modal-content max-w-md mx-auto">
            <div class="bg-purple-900 p-4 flex justify-between items-center">
                <h3 class="text-xl font-bold text-white">Reset Password</h3>
                <button onclick="closeResetPasswordModal()" class="text-white hover:text-gray-300 text-2xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-6">
                <form method="POST">
                    <input type="hidden" name="action" value="reset_password">
                    <div class="mb-6">
                        <label class="block text-gray-300 mb-2">New Password</label>
                        <input type="password" name="new_password" class="form-input w-full" placeholder="Enter your new password" required>
                    </div>
                    <button type="submit" class="btn-primary w-full py-3 rounded-lg text-white font-medium">Update Password</button>
                </form>
                <p class="text-gray-400 text-center mt-4">
                    <a href="#" class="text-purple-400" onclick="document.getElementById('resetPasswordModal').classList.remove('active'); document.getElementById('loginModal').classList.add('active');">Back to Login</a>
                </p>
            </div>
        </div>
    </div>
    
    <?php 
    // Clear the show_reset_modal session variable after displaying
    if (isset($_SESSION['show_reset_modal'])) {
        unset($_SESSION['show_reset_modal']);
    }
    ?>

    <!-- Add Money Modal -->
    <div id="addMoneyModal" class="modal">
        <div class="modal-content max-w-md mx-auto">
            <div class="bg-purple-900 p-4 flex justify-between items-center">
                <h3 class="text-xl font-bold text-white">Add ₹25 to Wallet</h3>
                <button onclick="closeAddMoneyModal()" class="text-white hover:text-gray-300 text-2xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-6">
                <div class="text-center mb-6">
                    <p class="text-gray-300 mb-4">Scan this QR code to pay ₹25</p>
                    <img src="upi_fixed_25.png" alt="UPI QR Code for ₹25" class="mx-auto max-w-xs border-2 border-gray-600 rounded-lg">
                </div>

                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_money">
                    <input type="hidden" name="amount" value="25">

                    <div class="mb-6">
                        <label class="block text-gray-300 mb-2">Upload Payment Screenshot</label>
                        <input type="file" name="payment_screenshot" id="payment_screenshot" class="form-input w-full" accept="image/*" required>
                        <p class="text-gray-400 text-sm mt-1">Please upload a clear screenshot of your payment</p>
                    </div>

                    <button type="submit" class="btn-primary w-full py-3 rounded-lg text-white font-medium">Submit Request</button>
                </form>
                <p class="text-gray-400 text-center mt-4">
                    After submission, your request will be manually verified and approved by admin
                </p>
            </div>
        </div>
    </div>

    <!-- Withdraw Money Modal -->
    <div id="withdrawModal" class="modal">
        <div class="modal-content max-w-md mx-auto">
            <div class="bg-purple-900 p-4 flex justify-between items-center">
                <h3 class="text-xl font-bold text-white">Withdraw Money</h3>
                <button onclick="closeWithdrawModal()" class="text-white hover:text-gray-300 text-2xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-6">
                <form method="POST" id="withdrawForm">
                    <input type="hidden" name="action" value="withdraw_money">
                    <input type="hidden" name="amount" id="selectedAmount" value="100">
                    
                    <!-- Amount Selection -->
                    <div class="mb-6">
                        <label class="block text-gray-300 mb-3">Select Withdrawal Amount</label>
                        <div class="grid grid-cols-3 gap-3">
                            <button type="button" class="amount-btn bg-gray-700 hover:bg-purple-600 text-white py-3 px-4 rounded-lg font-medium transition-colors" data-amount="100">
                                ₹100
                            </button>
                            <button type="button" class="amount-btn bg-gray-700 hover:bg-purple-600 text-white py-3 px-4 rounded-lg font-medium transition-colors" data-amount="150">
                                ₹150
                            </button>
                            <button type="button" class="amount-btn bg-gray-700 hover:bg-purple-600 text-white py-3 px-4 rounded-lg font-medium transition-colors" data-amount="200">
                                ₹200
                            </button>
                        </div>
                    </div>
                    
                    <!-- Fee Information -->
                    <div class="bg-red-900 bg-opacity-20 border border-red-500 rounded-lg p-4 mb-4">
                        <div class="flex items-center mb-2">
                            <i class="fas fa-exclamation-triangle text-red-400 mr-2"></i>
                            <span class="text-red-200 font-medium">Fee Information</span>
                        </div>
                        <div class="text-red-200 text-sm space-y-1">
                            <p>• 5% Platform Fee will be deducted</p>
                            <p>• 5% Management Fee will be deducted</p>
                        </div>
                    </div>
                    
                    <!-- Amount Breakdown -->
                    <div class="bg-gray-800 rounded-lg p-4 mb-6">
                        <h4 class="text-white font-medium mb-3">Amount Breakdown</h4>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-300">Withdrawal Amount:</span>
                                <span class="text-white" id="withdrawalAmount">₹100</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-300">Platform Fee (5%):</span>
                                <span class="text-red-400" id="platformFee">-₹5</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-300">Management Fee (5%):</span>
                                <span class="text-red-400" id="managementFee">-₹5</span>
                            </div>
                            <hr class="border-gray-600">
                            <div class="flex justify-between font-medium">
                                <span class="text-green-300">You will receive:</span>
                                <span class="text-green-400" id="finalAmount">₹90</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-300 mb-2">UPI ID</label>
                        <input type="text" name="upi_id" id="upi_id" class="form-input w-full" placeholder="Enter your UPI ID (e.g., user@paytm)" required>
                    </div>
                    <div class="mb-6">
                        <label class="block text-gray-300 mb-2">Confirm UPI ID</label>
                        <input type="text" name="confirm_upi_id" id="confirm_upi_id" class="form-input w-full" placeholder="Confirm your UPI ID" required>
                        <p class="text-red-400 text-sm mt-1 hidden" id="upiError">UPI IDs do not match</p>
                    </div>
                    <div class="bg-yellow-900 bg-opacity-20 border border-yellow-500 rounded-lg p-4 mb-6">
                        <div class="flex items-center">
                            <i class="fas fa-info-circle text-yellow-400 mr-2"></i>
                            <span class="text-yellow-200 text-sm" id="deductionInfo">₹100 will be deducted instantly from your wallet balance</span>
                        </div>
                    </div>
                    <button type="submit" class="btn-primary w-full py-3 rounded-lg text-white font-medium" id="withdrawBtn">Withdraw ₹100</button>
                </form>
                <p class="text-gray-400 text-center mt-4">
                    Withdrawal will be processed instantly. Please ensure your UPI ID is correct.
                </p>
            </div>
        </div>
    </div>

    <!-- Screenshot Modal -->
    <div id="screenshotModal" class="modal">
        <div class="modal-content">
            <div class="bg-purple-900 p-4 flex justify-between items-center">
                <h3 id="modalTitle" class="text-xl font-bold text-white">Payment Screenshot</h3>
                <button onclick="document.getElementById('screenshotModal').classList.remove('active')" class="text-white hover:text-gray-300">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div class="p-6">
                <div class="text-center">
                    <img id="modalScreenshot" src="" alt="Payment Screenshot" class="max-w-full max-h-96 mx-auto border-2 border-gray-600 rounded-lg">
                </div>
                <div class="mt-6 text-center">
                    <button onclick="document.getElementById('screenshotModal').classList.remove('active')" class="btn-primary px-6 py-2 rounded-lg text-white font-medium">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Generate Results Modal -->
    <div id="generateResultsModal" class="modal">
        <div class="modal-content max-w-4xl">
            <div class="bg-purple-900 p-4 flex justify-between items-center">
                <h3 class="text-xl font-bold text-white">Enter Match Results</h3>
                <button onclick="closeModalAndClear()" class="text-white hover:text-gray-300">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div class="p-6">
                <?php if (isset($_SESSION['generate_results_match']) && isset($_SESSION['generate_results_users'])): ?>
                    <?php 
                    $match = $_SESSION['generate_results_match'];
                    $users = $_SESSION['generate_results_users'];
                    ?>
                    <div class="mb-6">
                        <h4 class="text-lg font-bold text-white mb-2"><?php echo htmlspecialchars($match['title']); ?></h4>
                        <p class="text-gray-400">Date: <?php echo date('Y-m-d H:i', strtotime($match['match_date'])); ?></p>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="action" value="admin_save_publish_results">
                        <input type="hidden" name="match_id" value="<?php echo $match['id']; ?>">

                        <div class="overflow-auto max-h-96 border border-gray-600 rounded-lg">
                            <table class="w-full text-white">
                                <thead class="bg-gray-800 sticky top-0 z-10">
                                    <tr>
                                        <th class="px-4 py-3 text-left">Player Name</th>
                                        <th class="px-4 py-3 text-left">Kills</th>
                                        <th class="px-4 py-3 text-left">Booyah</th>
                                        <th class="px-4 py-3 text-left">Earnings (₹)</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-700">
                                    <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td class="px-4 py-3">
                                            <?php echo htmlspecialchars($user['game_name']); ?>
                                            <br><span class="text-gray-400 text-sm"><?php echo htmlspecialchars($user['phone']); ?></span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <input type="number" name="user_results[<?php echo $user['user_id']; ?>][kills]" 
                                                   value="<?php echo $user['kills']; ?>" 
                                                   min="0" class="form-input w-20" required>
                                        </td>
                                        <td class="px-4 py-3">
                                            <input type="checkbox" name="user_results[<?php echo $user['user_id']; ?>][booyah]" 
                                                   value="1" <?php echo $user['booyah'] ? 'checked' : ''; ?> 
                                                   class="form-checkbox">
                                        </td>
                                        <td class="px-4 py-3">
                                            <input type="number" name="user_results[<?php echo $user['user_id']; ?>][earnings]" 
                                                   value="<?php echo $user['earnings']; ?>" 
                                                   min="0" step="0.01" class="form-input w-24" required>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-6 flex justify-end space-x-4">
                            <button type="button" onclick="closeModalAndClear()" class="bg-gray-700 px-6 py-2 rounded-lg text-white font-medium hover:bg-gray-600 transition">Cancel</button>
                            <button type="submit" class="btn-primary px-6 py-2 rounded-lg text-white font-medium">Publish Results</button>
                        </div>
                    </form>
                <?php else: ?>
                    <p class="text-gray-400">No match data available. Please select a match first.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Mobile Menu Toggle
        document.getElementById('mobileMenuButton').addEventListener('click', function() {
            document.getElementById('mobileMenu').classList.add('open');
        });
        
        document.getElementById('closeMobileMenu').addEventListener('click', function() {
            document.getElementById('mobileMenu').classList.remove('open');
        });
        
        // User Menu Toggle
        document.getElementById('userMenuButton').addEventListener('click', function() {
            document.getElementById('userMenu').classList.toggle('hidden');
        });
        
        // Close user menu when clicking outside
        document.addEventListener('click', function(event) {
            const userMenu = document.getElementById('userMenu');
            const userMenuButton = document.getElementById('userMenuButton');
            
            if (userMenu && userMenuButton && !userMenu.contains(event.target) && !userMenuButton.contains(event.target)) {
                userMenu.classList.add('hidden');
            }
        });
        
        // Page Navigation
        document.querySelectorAll('[data-page]').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const pageId = this.getAttribute('data-page') + 'Page';
                
                // Hide all pages
                document.querySelectorAll('.page').forEach(page => {
                    page.classList.remove('active');
                });
                
                // Show selected page
                document.getElementById(pageId).classList.add('active');
                
                // Close mobile menu if open
                document.getElementById('mobileMenu').classList.remove('open');
            });
        });
        
        // Tab Switching
        const tabButtons = document.querySelectorAll('.tab-btn');
        const tabContents = document.querySelectorAll('.tab-content');
        
        tabButtons.forEach(button => {
            button.addEventListener('click', () => {
                const tabId = button.getAttribute('data-tab');
                
                // Update active tab button
                tabButtons.forEach(btn => {
                    btn.classList.remove('text-white', 'border-purple-500');
                    btn.classList.add('text-gray-400');
                });
                
                button.classList.add('text-white', 'border-purple-500');
                button.classList.remove('text-gray-400');
                
                // Show active tab content
                tabContents.forEach(content => {
                    content.classList.remove('active');
                });
                
                document.getElementById(tabId).classList.add('active');
            });
        });
        
        // Close modals when clicking outside
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });
        
        // Auto-hide toast messages
        const toasts = document.querySelectorAll('.toast');
        toasts.forEach(toast => {
            if (toast.classList.contains('show')) {
                setTimeout(() => {
                    toast.classList.remove('show');
                }, 3000);
            }
        });

        // Show screenshot modal
        function showScreenshotModal(imagePath, userName) {
            const modal = document.getElementById('screenshotModal');
            const modalImage = document.getElementById('modalScreenshot');
            const modalTitle = document.getElementById('modalTitle');

            modalImage.src = imagePath;
            modalTitle.textContent = `Payment Screenshot - ${userName}`;
            modal.classList.add('active');
        }

        // Copy referral code to clipboard
        function copyReferralCode(code) {
            navigator.clipboard.writeText(code).then(function() {
                // Show success message
                const clickedElement = event.target;
                
                // If clicked on the code itself
                if (clickedElement.classList.contains('cursor-pointer')) {
                    const originalText = clickedElement.textContent;
                    const originalColor = clickedElement.classList.contains('text-purple-400') ? 'text-purple-400' : 'text-purple-300';
                    
                    clickedElement.textContent = 'Copied!';
                    clickedElement.classList.remove('text-purple-400', 'text-purple-300');
                    clickedElement.classList.add('text-green-400');
                    
                    setTimeout(function() {
                        clickedElement.textContent = originalText;
                        clickedElement.classList.remove('text-green-400');
                        clickedElement.classList.add(originalColor);
                    }, 2000);
                } else {
                    // If clicked on button
                    const originalText = clickedElement.textContent;
                    clickedElement.textContent = 'Copied!';
                    clickedElement.classList.add('bg-green-600');
                    clickedElement.classList.remove('btn-primary');
                    
                    setTimeout(function() {
                        clickedElement.textContent = originalText;
                        clickedElement.classList.remove('bg-green-600');
                        clickedElement.classList.add('btn-primary');
                    }, 2000);
                }
            }).catch(function(err) {
                console.error('Could not copy text: ', err);
                alert('Failed to copy referral code. Please copy manually: ' + code);
            });
        }

        // Navigate to referral tab
        function navigateToReferral() {
            // Hide all pages
            document.querySelectorAll('.page').forEach(page => {
                page.classList.remove('active');
            });
            
            // Show dashboard page
            document.getElementById('dashboardPage').classList.add('active');
            
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('text-white', 'border-b-2', 'border-purple-500');
                btn.classList.add('text-gray-400');
            });
            
            // Show referral tab content
            document.getElementById('referral').classList.add('active');
            
            // Activate referral tab button
            const referralBtn = document.querySelector('[data-tab="referral"]');
            referralBtn.classList.remove('text-gray-400');
            referralBtn.classList.add('text-white', 'border-b-2', 'border-purple-500');
            
            // Close user menu
            document.getElementById('userMenu').classList.add('hidden');
        }

        // Modal close functions
        function closeLoginModal() {
            document.getElementById('loginModal').classList.remove('active');
        }

        function closeSignupModal() {
            document.getElementById('signupModal').classList.remove('active');
        }

        function closeForgotPasswordModal() {
            document.getElementById('forgotPasswordModal').classList.remove('active');
        }

        function closeResetPasswordModal() {
            document.getElementById('resetPasswordModal').classList.remove('active');
        }

        function closeAddMoneyModal() {
            document.getElementById('addMoneyModal').classList.remove('active');
        }

        function closeWithdrawModal() {
            document.getElementById('withdrawModal').classList.remove('active');
        }

        // Close modals when clicking outside
        document.addEventListener('click', function(event) {
            const modals = ['loginModal', 'signupModal', 'forgotPasswordModal', 'resetPasswordModal', 'addMoneyModal', 'withdrawModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    modal.classList.remove('active');
                }
            });
        });

        // Prevent browser back button from exiting website
        window.addEventListener('popstate', function(event) {
            // If user tries to go back, just close any open modals instead
            const openModals = document.querySelectorAll('.modal.active');
            if (openModals.length > 0) {
                openModals.forEach(modal => {
                    modal.classList.remove('active');
                });
                // Push current state to prevent actual back navigation
                history.pushState(null, null, window.location.href);
            }
        });

        // Push initial state to prevent back navigation
        history.pushState(null, null, window.location.href);

        // Add back button functionality for in-app navigation
        function goBack() {
            // Close any open modals first
            const openModals = document.querySelectorAll('.modal.active');
            if (openModals.length > 0) {
                openModals.forEach(modal => {
                    modal.classList.remove('active');
                });
                return;
            }
            
            // If on a specific page, go back to dashboard
            const currentPage = document.querySelector('.page.active');
            if (currentPage && currentPage.id !== 'dashboardPage') {
                // Hide all pages
                document.querySelectorAll('.page').forEach(page => {
                    page.classList.remove('active');
                });
                
                // Show dashboard page
                document.getElementById('dashboardPage').classList.add('active');
                
                // Reset tabs to upcoming matches
                document.querySelectorAll('.tab-content').forEach(tab => {
                    tab.classList.remove('active');
                });
                
                document.querySelectorAll('.tab-btn').forEach(btn => {
                    btn.classList.remove('text-white', 'border-b-2', 'border-purple-500');
                    btn.classList.add('text-gray-400');
                });
                
                document.getElementById('upcoming').classList.add('active');
                const upcomingBtn = document.querySelector('[data-tab="upcoming"]');
                upcomingBtn.classList.remove('text-gray-400');
                upcomingBtn.classList.add('text-white', 'border-b-2', 'border-purple-500');
            }
        }

        // Withdrawal amount selection and fee calculation
        document.addEventListener('DOMContentLoaded', function() {
            const amountButtons = document.querySelectorAll('.amount-btn');
            const selectedAmountInput = document.getElementById('selectedAmount');
            const withdrawalAmountSpan = document.getElementById('withdrawalAmount');
            const platformFeeSpan = document.getElementById('platformFee');
            const managementFeeSpan = document.getElementById('managementFee');
            const finalAmountSpan = document.getElementById('finalAmount');
            const deductionInfoSpan = document.getElementById('deductionInfo');
            const withdrawBtn = document.getElementById('withdrawBtn');

            function updateWithdrawalCalculation(amount) {
                const platformFee = amount * 0.05; // 5%
                const managementFee = amount * 0.05; // 5%
                const totalFees = platformFee + managementFee;
                const finalAmount = amount - totalFees;

                // Update display
                withdrawalAmountSpan.textContent = `₹${amount}`;
                platformFeeSpan.textContent = `-₹${platformFee}`;
                managementFeeSpan.textContent = `-₹${managementFee}`;
                finalAmountSpan.textContent = `₹${finalAmount}`;
                deductionInfoSpan.textContent = `₹${amount} will be deducted instantly from your wallet balance`;
                withdrawBtn.textContent = `Withdraw ₹${amount}`;

                // Update hidden input
                selectedAmountInput.value = amount;
            }

            // Add click event listeners to amount buttons
            amountButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Remove active class from all buttons
                    amountButtons.forEach(btn => {
                        btn.classList.remove('bg-purple-600');
                        btn.classList.add('bg-gray-700');
                    });
                    
                    // Add active class to clicked button
                    this.classList.remove('bg-gray-700');
                    this.classList.add('bg-purple-600');
                    
                    // Get amount and update calculation
                    const amount = parseInt(this.getAttribute('data-amount'));
                    updateWithdrawalCalculation(amount);
                });
            });

            // Set default selection (₹100)
            if (amountButtons.length > 0) {
                amountButtons[0].click();
            }
        });

        // Generate Results Modal functions
        function closeGenerateResultsModal() {
            const modal = document.getElementById('generateResultsModal');
            if (modal) {
                modal.classList.remove('active');
            }
        }

        function closeModalAndClear() {
            // Close the modal
            closeGenerateResultsModal();
            // Submit a form to clear session data
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = window.location.href;
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'action';
            input.value = 'clear_generate_results_session';
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        }

        // Show generate results modal when form is submitted
        <?php if (isset($_SESSION['generate_results_match']) && isset($_SESSION['generate_results_users'])): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('generateResultsModal').classList.add('active');
        });
        <?php endif; ?>

        // Close modal when clicking outside of it
        document.addEventListener('click', function(event) {
            const modal = document.getElementById('generateResultsModal');
            if (event.target === modal) {
                closeModalAndClear();
            }
        });

        // Profile Modal functions
        function openProfileModal() {
            document.getElementById('profileModal').classList.add('active');
        }

        function closeProfileModal() {
            document.getElementById('profileModal').classList.remove('active');
        }

        // Transaction History Toggle
        const viewMoreBtn = document.getElementById('viewMoreBtn');
        const viewLessBtn = document.getElementById('viewLessBtn');

        if (viewMoreBtn && viewLessBtn) {
            viewMoreBtn.addEventListener('click', function() {
                // Show all hidden transaction rows
                const hiddenRows = document.querySelectorAll('.transaction-row.hidden');
                hiddenRows.forEach(row => {
                    row.classList.remove('hidden');
                });

                // Toggle button visibility
                viewMoreBtn.style.display = 'none';
                viewLessBtn.style.display = 'inline-block';
            });

            viewLessBtn.addEventListener('click', function() {
                // Hide all transaction rows except the first 3
                const allRows = document.querySelectorAll('.transaction-row');
                allRows.forEach((row, index) => {
                    if (index >= 3) {
                        row.classList.add('hidden');
                    }
                });

                // Toggle button visibility
                viewLessBtn.style.display = 'none';
                viewMoreBtn.style.display = 'inline-block';
            });
        }
    </script>
    <script>
  if ("serviceWorker" in navigator) {
    window.addEventListener("load", () => {
      navigator.serviceWorker
        .register("/service-worker.js")
        .then(reg => console.log("Service Worker registered:", reg))
        .catch(err => console.log("Service Worker registration failed:", err));
    });
  }
</script>

</body>
</html>

