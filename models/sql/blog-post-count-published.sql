SELECT COUNT(*) AS total
FROM {TABLE} p
JOIN {PREFIX}pages pg ON pg.id = p.page_id AND pg.deleted_at IS NULL
WHERE p.is_published = 1
