CREATE DATABASE IF NOT EXISTS escalation_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE escalation_system;

CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(50) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL
);

CREATE TABLE support_levels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(50) NOT NULL UNIQUE,
    level_order INT NOT NULL,
    created_at DATETIME NOT NULL
);

CREATE TABLE teams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    description TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL
);

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (role_id) REFERENCES roles(id)
);

CREATE TABLE agent_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    support_level_id INT NOT NULL,
    team_id INT NULL,
    skills TEXT NULL,
    specialization VARCHAR(150) NULL,
    agent_status ENUM('Available','Busy','Inactive') NOT NULL DEFAULT 'Available',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (support_level_id) REFERENCES support_levels(id),
    FOREIGN KEY (team_id) REFERENCES teams(id)
);

CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    description TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL
);

CREATE TABLE priorities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    sort_order INT NOT NULL
);

CREATE TABLE sla_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    priority_id INT NOT NULL,
    first_response_minutes INT NOT NULL,
    resolution_minutes INT NOT NULL,
    escalation_target_level_id INT NOT NULL,
    warning_before_minutes INT NOT NULL DEFAULT 30,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (priority_id) REFERENCES priorities(id),
    FOREIGN KEY (escalation_target_level_id) REFERENCES support_levels(id)
);

CREATE TABLE tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_number VARCHAR(50) NOT NULL UNIQUE,
    requester_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    category_id INT NOT NULL,
    priority_id INT NOT NULL,
    problem_intensity ENUM('Critical','High','Medium','Low') NOT NULL DEFAULT 'Low',
    affected_user_type VARCHAR(100) NOT NULL,
    number_of_users_affected INT NOT NULL DEFAULT 1,
    total_number_of_users INT NOT NULL DEFAULT 1,
    affected_user_percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    business_impact ENUM(
        'No major impact',
        'Minor inconvenience',
        'Department-level disruption',
        'Major operation affected',
        'System-wide outage',
        'Security or data risk'
    ) NOT NULL DEFAULT 'Minor inconvenience',
    attachment_name VARCHAR(255) NULL,
    current_status VARCHAR(50) NOT NULL DEFAULT 'Open',
    current_level_id INT NOT NULL,
    assigned_agent_id INT NULL,
    response_due_at DATETIME NOT NULL,
    resolution_due_at DATETIME NOT NULL,
    first_response_at DATETIME NULL,
    resolved_at DATETIME NULL,
    closed_at DATETIME NULL,
    last_updated_at DATETIME NOT NULL,
    priority_overridden_by INT NULL,
    priority_override_reason TEXT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (requester_id) REFERENCES users(id),
    FOREIGN KEY (category_id) REFERENCES categories(id),
    FOREIGN KEY (priority_id) REFERENCES priorities(id),
    FOREIGN KEY (current_level_id) REFERENCES support_levels(id),
    FOREIGN KEY (assigned_agent_id) REFERENCES users(id),
    FOREIGN KEY (priority_overridden_by) REFERENCES users(id)
);

CREATE TABLE ticket_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    support_level_id INT NOT NULL,
    assigned_agent_id INT NULL,
    assigned_by INT NOT NULL,
    assignment_reason TEXT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id),
    FOREIGN KEY (support_level_id) REFERENCES support_levels(id),
    FOREIGN KEY (assigned_agent_id) REFERENCES users(id),
    FOREIGN KEY (assigned_by) REFERENCES users(id)
);

CREATE TABLE ticket_updates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    user_id INT NOT NULL,
    update_type VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE escalation_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    from_level_id INT NULL,
    to_level_id INT NOT NULL,
    from_agent_id INT NULL,
    to_agent_id INT NULL,
    escalation_reason TEXT NOT NULL,
    escalated_by INT NOT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id),
    FOREIGN KEY (from_level_id) REFERENCES support_levels(id),
    FOREIGN KEY (to_level_id) REFERENCES support_levels(id),
    FOREIGN KEY (from_agent_id) REFERENCES users(id),
    FOREIGN KEY (to_agent_id) REFERENCES users(id),
    FOREIGN KEY (escalated_by) REFERENCES users(id)
);

CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_user_id INT NOT NULL,
    ticket_id INT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    notification_type VARCHAR(100) NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (recipient_user_id) REFERENCES users(id),
    FOREIGN KEY (ticket_id) REFERENCES tickets(id)
);

CREATE TABLE resolution_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL UNIQUE,
    root_cause TEXT NOT NULL,
    action_taken TEXT NOT NULL,
    solution_applied TEXT NOT NULL,
    resolution_notes TEXT NOT NULL,
    resolved_at DATETIME NOT NULL,
    resolved_by INT NOT NULL,
    resolved_level_id INT NOT NULL,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id),
    FOREIGN KEY (resolved_by) REFERENCES users(id),
    FOREIGN KEY (resolved_level_id) REFERENCES support_levels(id)
);

CREATE TABLE audit_trails (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action_type VARCHAR(100) NOT NULL,
    ticket_id INT NULL,
    old_value TEXT NULL,
    new_value TEXT NULL,
    ip_address VARCHAR(64) NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (ticket_id) REFERENCES tickets(id)
);

CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(120) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    updated_at DATETIME NOT NULL
);
