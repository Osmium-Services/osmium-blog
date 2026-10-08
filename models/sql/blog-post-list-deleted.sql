SELECT
    p.id,
    p.page_id,
    p.author_user_id,
    p.is_published,
    p.published_at,
    p.updated_at,
    pg.url,
    pg.title,
    pg.deleted_at,
    u.first_name,
    u.last_name
FROM {TABLE} p
JOIN {PREFIX}pages pg ON pg.id = p.page_id AND pg.deleted_at IS NOT NULL
LEFT JOIN {PREFIX}users u ON u.id = p.author_user_id
ORDER BY pg.deleted_at DESC, p.id DESC
