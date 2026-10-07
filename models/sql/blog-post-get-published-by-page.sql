SELECT
    p.id,
    p.page_id,
    p.excerpt,
    p.body,
    p.hero_image,
    p.social_image,
    p.author_user_id,
    p.published_at,
    p.updated_at,
    u.first_name,
    u.last_name
FROM {TABLE} p
LEFT JOIN {PREFIX}users u ON u.id = p.author_user_id
WHERE p.page_id = :page_id
  AND p.is_published = 1
LIMIT 1
