SELECT
    p.id,
    p.page_id,
    p.author_user_id,
    p.is_published,
    p.published_at,
    p.created_at,
    p.updated_at,
    pg.url,
    pg.title,
    u.first_name,
    u.last_name
FROM {TABLE} p
JOIN {PREFIX}pages pg ON pg.id = p.page_id AND pg.deleted_at IS NULL
LEFT JOIN {PREFIX}users u ON u.id = p.author_user_id
WHERE p.created_at >= NOW() - INTERVAL 24 HOUR OR p.updated_at >= NOW() - INTERVAL 24 HOUR
ORDER BY GREATEST(COALESCE(p.updated_at, p.created_at), p.created_at) DESC, p.id DESC
LIMIT 10
