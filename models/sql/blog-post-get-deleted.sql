SELECT
    p.id,
    p.page_id,
    pg.url,
    pg.title
FROM {TABLE} p
JOIN {PREFIX}pages pg ON pg.id = p.page_id AND pg.deleted_at IS NOT NULL
WHERE p.id = :id
LIMIT 1
