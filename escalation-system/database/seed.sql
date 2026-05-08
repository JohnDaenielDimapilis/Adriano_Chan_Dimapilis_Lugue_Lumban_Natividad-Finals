USE escalation_system;

INSERT INTO roles (id, name, code, created_at) VALUES
(1, 'Administrator / IT Manager', 'ADMIN', NOW()),
(2, 'Requester / Regular User', 'REQUESTER', NOW()),
(3, 'Level 1 Support Agent', 'LEVEL1', NOW()),
(4, 'Level 2 Support Agent', 'LEVEL2', NOW()),
(5, 'Level 3 Support Agent / Technical Specialist', 'LEVEL3', NOW())
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO support_levels (id, name, code, level_order, created_at) VALUES
(1, 'Level 1 Support', 'LEVEL1', 1, NOW()),
(2, 'Level 2 Support', 'LEVEL2', 2, NOW()),
(3, 'Level 3 Support', 'LEVEL3', 3, NOW())
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO teams (id, name, description, is_active, created_at) VALUES
(1, 'Service Desk', 'Handles first-line and end-user requests.', 1, NOW()),
(2, 'Applications Support', 'Handles functional and system issues.', 1, NOW()),
(3, 'Infrastructure & Security', 'Handles critical platform and security incidents.', 1, NOW())
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO categories (id, name, description, is_active, created_at) VALUES
(1, 'Account & Access', 'Login, password, and provisioning issues.', 1, NOW()),
(2, 'Application', 'Business system defects and functional incidents.', 1, NOW()),
(3, 'Network', 'Connectivity and performance concerns.', 1, NOW()),
(4, 'Hardware', 'Workstation, printer, and device support.', 1, NOW()),
(5, 'Security', 'Security alerts and data protection incidents.', 1, NOW())
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO priorities (id, code, name, description, sort_order) VALUES
(1, 'P1', 'P1 Critical', 'Critical outage or security-impacting event.', 1),
(2, 'P2', 'P2 Medium', 'Department disruption or important issue with workaround.', 2),
(3, 'P3', 'P3 Low', 'Minor issue or low-impact request.', 3)
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO sla_rules (priority_id, first_response_minutes, resolution_minutes, escalation_target_level_id, warning_before_minutes, is_active, created_at)
SELECT 1, 30, 360, 3, 15, 1, NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM sla_rules WHERE priority_id = 1);

INSERT INTO sla_rules (priority_id, first_response_minutes, resolution_minutes, escalation_target_level_id, warning_before_minutes, is_active, created_at)
SELECT 2, 60, 720, 2, 30, 1, NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM sla_rules WHERE priority_id = 2);

INSERT INTO sla_rules (priority_id, first_response_minutes, resolution_minutes, escalation_target_level_id, warning_before_minutes, is_active, created_at)
SELECT 3, 240, 1440, 2, 60, 1, NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM sla_rules WHERE priority_id = 3);

INSERT INTO users (id, role_id, full_name, email, password_hash, is_active, created_at) VALUES
(1, 1, 'System Administrator', 'admin@example.com', '$2y$10$mGHLRnW5eKeKtiiZsr2MIeva1VET7fUeEohbEdxdmEsc2XUIHT/PC', 1, NOW()),
(2, 3, 'Level One Agent', 'level1@example.com', '$2y$10$mGHLRnW5eKeKtiiZsr2MIeva1VET7fUeEohbEdxdmEsc2XUIHT/PC', 1, NOW()),
(3, 4, 'Level Two Agent', 'level2@example.com', '$2y$10$mGHLRnW5eKeKtiiZsr2MIeva1VET7fUeEohbEdxdmEsc2XUIHT/PC', 1, NOW()),
(4, 5, 'Level Three Specialist', 'level3@example.com', '$2y$10$mGHLRnW5eKeKtiiZsr2MIeva1VET7fUeEohbEdxdmEsc2XUIHT/PC', 1, NOW()),
(5, 2, 'Demo Requester', 'user@example.com', '$2y$10$mGHLRnW5eKeKtiiZsr2MIeva1VET7fUeEohbEdxdmEsc2XUIHT/PC', 1, NOW())
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name);

INSERT INTO agent_profiles (user_id, support_level_id, team_id, skills, specialization, agent_status, is_active, updated_at)
SELECT 2, 1, 1, 'Password reset, account unlock, onboarding', 'Service Desk', 'Available', 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM agent_profiles WHERE user_id = 2);

INSERT INTO agent_profiles (user_id, support_level_id, team_id, skills, specialization, agent_status, is_active, updated_at)
SELECT 3, 2, 2, 'ERP issues, integrations, reporting', 'Applications Support', 'Available', 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM agent_profiles WHERE user_id = 3);

INSERT INTO agent_profiles (user_id, support_level_id, team_id, skills, specialization, agent_status, is_active, updated_at)
SELECT 4, 3, 3, 'Database, infrastructure, incident response', 'Infrastructure & Security', 'Available', 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM agent_profiles WHERE user_id = 4);

INSERT INTO settings (setting_key, setting_value, updated_at)
SELECT 'company_name', 'Capstone IT Services', NOW()
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE setting_key = 'company_name');
