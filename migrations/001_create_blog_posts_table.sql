CREATE TABLE IF NOT EXISTS {PREFIX}blog_posts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    page_id INT UNSIGNED NOT NULL,
    excerpt TEXT DEFAULT NULL,
    body MEDIUMTEXT NOT NULL,
    author_user_id INT UNSIGNED DEFAULT NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    published_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_blog_posts_page (page_id),
    KEY idx_blog_posts_published (is_published, published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
