-- User Settings Table
CREATE TABLE IF NOT EXISTS user_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    email_job_alerts BOOLEAN DEFAULT 1,
    email_applications BOOLEAN DEFAULT 1,
    email_messages BOOLEAN DEFAULT 1,
    email_newsletter BOOLEAN DEFAULT 1,
    profile_visibility ENUM('public', 'employers', 'private') DEFAULT 'public',
    show_email BOOLEAN DEFAULT 0,
    show_phone BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);
