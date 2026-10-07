SELECT
    p.id,
    p.excerpt,
    p.hero_image,
    p.published_at,
    pg.url,
    pg.title,
    u.first_name,
    u.last_name
FROM {TABLE} p
JOIN {PREFIX}pages pg ON pg.id = p.page_id AND pg.deleted_at IS NULL
LEFT JOIN {PREFIX}users u ON u.id = p.author_user_id
WHERE p.is_published = 1
  AND (p.published_at IS NULL OR p.published_at <= NOW())
ORDER BY p.published_at DESC, p.id DESC
LIMIT :limit OFFSET :offset
