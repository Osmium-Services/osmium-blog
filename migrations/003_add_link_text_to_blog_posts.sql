ALTER TABLE {PREFIX}blog_posts ADD COLUMN link_text VARCHAR(100) DEFAULT NULL COMMENT 'Wording of the post link on theme post lists, such as the home page' AFTER excerpt;
