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
    pg.deleted_at,
    u.first_name,
    u.last_name,
    CL.user_name AS changed_by,
    CU.avatar AS changed_by_avatar
FROM {TABLE} p
JOIN {PREFIX}pages pg ON pg.id = p.page_id
LEFT JOIN {PREFIX}users u ON u.id = p.author_user_id
LEFT JOIN {PREFIX}changelog AS CL ON CL.id = (SELECT MAX(id) FROM {PREFIX}changelog WHERE record_type = 'blog_post' AND record_id = p.id)
LEFT JOIN {PREFIX}users AS CU ON CU.id = CL.user_id
WHERE p.created_at >= NOW() - INTERVAL 24 HOUR OR p.updated_at >= NOW() - INTERVAL 24 HOUR OR pg.deleted_at >= NOW() - INTERVAL 24 HOUR
ORDER BY GREATEST(COALESCE(p.updated_at, p.created_at), p.created_at, COALESCE(pg.deleted_at, p.created_at)) DESC, p.id DESC
LIMIT 10
