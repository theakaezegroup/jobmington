-- Forum Tables Migration
DROP TABLE IF EXISTS forum_replies;
DROP TABLE IF EXISTS forum_topics;
DROP TABLE IF EXISTS forum_categories;

CREATE TABLE forum_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(50) DEFAULT 'fas fa-comments',
    color VARCHAR(20) DEFAULT '#8b5cf6',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE forum_topics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    views INT DEFAULT 0,
    is_pinned TINYINT(1) DEFAULT 0,
    is_locked TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES forum_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE forum_replies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    topic_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    is_best_answer TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (topic_id) REFERENCES forum_topics(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

INSERT INTO forum_categories (name, description, icon, color) VALUES
('Career Advice', 'Get and share career tips, job search strategies, and professional growth advice', 'fas fa-briefcase', '#8b5cf6'),
('CV & Resume Help', 'Get feedback on your CV, share templates, and discuss best practices', 'fas fa-file-alt', '#22c55e'),
('Interview Tips', 'Share interview experiences and prepare for your next opportunity', 'fas fa-comments', '#f59e0b'),
('Job Market Insights', 'Discuss industry trends, salary expectations, and job market news', 'fas fa-chart-line', '#3b82f6'),
('Remote Work', 'Tips for working remotely, finding remote jobs, and work-life balance', 'fas fa-home', '#ec4899'),
('Freelancing', 'Discuss freelance opportunities, client management, and pricing', 'fas fa-laptop', '#14b8a6'),
('Success Stories', 'Share your wins! Got a job? Landed a client? Celebrate here!', 'fas fa-trophy', '#fbbf24'),
('General Discussion', 'Off-topic conversations and community bonding', 'fas fa-coffee', '#64748b');
