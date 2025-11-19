<?php
/**
 * ProfileManager Class
 * 
 * Centralized, secure profile management system for Blood Konnector
 * Handles user role management, profile switching, and access control
 * 
 * @version 2.0
 * @author Blood Konnector Team
 */

class ProfileManager {
    private $conn;
    private $user_id;
    
    /**
     * Constructor
     * @param mysqli $conn Database connection
     */
    public function __construct($conn) {
        $this->conn = $conn;
        $this->user_id = $_SESSION['user_id'] ?? null;
    }
    
    /**
     * Check if user is logged in
     * @return bool
     */
    public function isLoggedIn() {
        return !empty($this->user_id);
    }
    
    /**
     * Get all roles/profiles for current user
     * @return array ['is_donor' => bool, 'is_recipient' => bool]
     */
    public function getUserRoles() {
        if (!$this->isLoggedIn()) {
            return ['is_donor' => false, 'is_recipient' => false];
        }
        
        $roles = ['is_donor' => false, 'is_recipient' => false];
        
        // Check donor role
        $stmt = $this->conn->prepare("SELECT donor_id FROM donors WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("s", $this->user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $roles['is_donor'] = $result->num_rows > 0;
        $stmt->close();
        
        // Check recipient role
        $stmt = $this->conn->prepare("SELECT recipient_id FROM recipients WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("s", $this->user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $roles['is_recipient'] = $result->num_rows > 0;
        $stmt->close();
        
        return $roles;
    }
    
    /**
     * Check if user has a specific role
     * @param string $role 'donor' or 'recipient'
     * @return bool
     */
    public function hasRole($role) {
        $roles = $this->getUserRoles();
        
        if ($role === 'donor') {
            return $roles['is_donor'];
        } elseif ($role === 'recipient') {
            return $roles['is_recipient'];
        }
        
        return false;
    }
    
    /**
     * Get current viewing profile (session-based only, NO database changes)
     * @return string|null 'donor', 'recipient', or null
     */
    public function getCurrentProfile() {
        return $_SESSION['viewing_as'] ?? null;
    }
    
    /**
     * Set viewing profile (session-based only)
     * @param string $profile 'donor' or 'recipient'
     * @return bool Success status
     */
    public function setViewingProfile($profile) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        // Validate profile type
        if (!in_array($profile, ['donor', 'recipient'])) {
            return false;
        }
        
        // Check if user has this role
        if (!$this->hasRole($profile)) {
            return false;
        }
        
        // Set session variable ONLY (no database changes)
        $_SESSION['viewing_as'] = $profile;
        return true;
    }
    
    /**
     * Clear viewing profile from session
     */
    public function clearViewingProfile() {
        unset($_SESSION['viewing_as']);
    }
    
    /**
     * Require user to be logged in (redirect if not)
     * @param string $redirect_url Where to redirect if not logged in
     */
    public function requireLogin($redirect_url = 'sign-in') {
        if (!$this->isLoggedIn()) {
            $_SESSION['error'] = "Please login to view this page!";
            header("Location: $redirect_url");
            exit();
        }
    }
    
    /**
     * Require user to have a specific role (redirect if not)
     * @param string $role 'donor' or 'recipient'
     * @param string $redirect_url Where to redirect if role not found
     */
    public function requireRole($role, $redirect_url = 'profile') {
        $this->requireLogin();
        
        if (!$this->hasRole($role)) {
            $_SESSION['error'] = "You don't have permission to view this page!";
            header("Location: $redirect_url");
            exit();
        }
        
        // Auto-set viewing profile if accessing role-specific page
        $this->setViewingProfile($role);
    }
    
    /**
     * Get donor availability status (for visibility to recipients)
     * @return string|null 'available', 'busy', 'inactive', or null
     */
    public function getDonorAvailability() {
        if (!$this->isLoggedIn() || !$this->hasRole('donor')) {
            return null;
        }
        
        $stmt = $this->conn->prepare("SELECT availability_status FROM donors WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("s", $this->user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $data = $result->fetch_assoc();
            $stmt->close();
            return $data['availability_status'] ?? 'available';
        }
        
        $stmt->close();
        return null;
    }
    
    /**
     * Set donor availability status (separate from viewing profile)
     * @param string $status 'available', 'busy', or 'inactive'
     * @return bool Success status
     */
    public function setDonorAvailability($status) {
        if (!$this->isLoggedIn() || !$this->hasRole('donor')) {
            return false;
        }
        
        // Validate status
        $valid_statuses = ['available', 'busy', 'inactive'];
        if (!in_array($status, $valid_statuses)) {
            return false;
        }
        
        $stmt = $this->conn->prepare("UPDATE donors SET availability_status = ? WHERE user_id = ?");
        $stmt->bind_param("ss", $status, $this->user_id);
        $success = $stmt->execute();
        $stmt->close();
        
        return $success;
    }
    
    /**
     * Get recipient request status
     * @return string|null 'active', 'fulfilled', 'cancelled', or null
     */
    public function getRecipientStatus() {
        if (!$this->isLoggedIn() || !$this->hasRole('recipient')) {
            return null;
        }
        
        $stmt = $this->conn->prepare("SELECT request_status FROM recipients WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("s", $this->user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $data = $result->fetch_assoc();
            $stmt->close();
            return $data['request_status'] ?? 'active';
        }
        
        $stmt->close();
        return null;
    }
    
    /**
     * Set recipient request status
     * @param string $status 'active', 'fulfilled', or 'cancelled'
     * @return bool Success status
     */
    public function setRecipientStatus($status) {
        if (!$this->isLoggedIn() || !$this->hasRole('recipient')) {
            return false;
        }
        
        // Validate status
        $valid_statuses = ['active', 'fulfilled', 'cancelled'];
        if (!in_array($status, $valid_statuses)) {
            return false;
        }
        
        $stmt = $this->conn->prepare("UPDATE recipients SET request_status = ? WHERE user_id = ?");
        $stmt->bind_param("ss", $status, $this->user_id);
        $success = $stmt->execute();
        $stmt->close();
        
        return $success;
    }
    
    /**
     * Update user's last activity timestamp
     */
    public function updateLastActivity() {
        if (!$this->isLoggedIn()) {
            return;
        }
        
        $stmt = $this->conn->prepare("UPDATE users SET last_activity = NOW() WHERE user_id = ?");
        $stmt->bind_param("s", $this->user_id);
        $stmt->execute();
        $stmt->close();
    }
    
    /**
     * Check if user is currently online (within last 5 minutes)
     * @param string|null $check_user_id User ID to check (null = current user)
     * @return bool
     */
    public function isUserOnline($check_user_id = null) {
        $user_to_check = $check_user_id ?? $this->user_id;
        
        if (empty($user_to_check)) {
            return false;
        }
        
        $stmt = $this->conn->prepare("SELECT last_activity FROM users WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("s", $user_to_check);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $data = $result->fetch_assoc();
            $stmt->close();
            
            $last_activity = strtotime($data['last_activity']);
            $current_time = time();
            
            // Online if activity within last 5 minutes (300 seconds)
            return ($current_time - $last_activity) < 300;
        }
        
        $stmt->close();
        return false;
    }
    
    /**
     * Get user basic info
     * @return array|null User data or null
     */
    public function getUserInfo() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        $stmt = $this->conn->prepare("SELECT user_id, first_name, last_name, email, profile_pic FROM users WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("s", $this->user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $data = $result->fetch_assoc();
            $stmt->close();
            return $data;
        }
        
        $stmt->close();
        return null;
    }
    
    /**
     * Generate profile switcher HTML (for header/navigation)
     * @return string HTML for profile switcher dropdown
     */
    public function getProfileSwitcherHTML() {
        if (!$this->isLoggedIn()) {
            return '';
        }
        
        $roles = $this->getUserRoles();
        $current_profile = $this->getCurrentProfile();
        
        // If user has no profiles, return nothing
        if (!$roles['is_donor'] && !$roles['is_recipient']) {
            return '';
        }
        
        // If user has only one role, show simple badge
        if ($roles['is_donor'] && !$roles['is_recipient']) {
            return '<span class="profile-badge donor-badge">Donor Profile</span>';
        }
        
        if (!$roles['is_donor'] && $roles['is_recipient']) {
            return '<span class="profile-badge recipient-badge">Recipient Profile</span>';
        }
        
        // User has both roles - show switcher dropdown
        $current_label = $current_profile === 'donor' ? 'Donor' : ($current_profile === 'recipient' ? 'Recipient' : 'Select Profile');
        
        $html = '<div class="profile-switcher-dropdown">';
        $html .= '<button class="profile-switcher-btn" id="profileSwitcherBtn">';
        $html .= '<i class="fas fa-user-circle"></i> ';
        $html .= '<span class="current-profile-label">' . htmlspecialchars($current_label) . '</span>';
        $html .= ' <i class="fas fa-chevron-down"></i>';
        $html .= '</button>';
        $html .= '<div class="profile-switcher-menu" id="profileSwitcherMenu">';
        
        if ($roles['is_donor']) {
            $active_class = $current_profile === 'donor' ? 'active' : '';
            $html .= '<a href="donor-profile" class="profile-option ' . $active_class . '">';
            $html .= '<i class="fas fa-hand-holding-heart"></i> Donor Profile';
            if ($current_profile === 'donor') {
                $html .= ' <i class="fas fa-check-circle"></i>';
            }
            $html .= '</a>';
        }
        
        if ($roles['is_recipient']) {
            $active_class = $current_profile === 'recipient' ? 'active' : '';
            $html .= '<a href="recipient-profile" class="profile-option ' . $active_class . '">';
            $html .= '<i class="fas fa-hospital-user"></i> Recipient Profile';
            if ($current_profile === 'recipient') {
                $html .= ' <i class="fas fa-check-circle"></i>';
            }
            $html .= '</a>';
        }
        
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
}

// Helper functions for backward compatibility
// Only declare if they don't already exist (prevent redeclaration errors)

if (!function_exists('is_donor')) {
    function is_donor($user_id) {
        global $conn;
        $stmt = $conn->prepare("SELECT donor_id FROM donors WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("s", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $is_donor = $result->num_rows > 0;
        $stmt->close();
        return $is_donor;
    }
}

if (!function_exists('is_recipient')) {
    function is_recipient($user_id) {
        global $conn;
        $stmt = $conn->prepare("SELECT recipient_id FROM recipients WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("s", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $is_recipient = $result->num_rows > 0;
        $stmt->close();
        return $is_recipient;
    }
}