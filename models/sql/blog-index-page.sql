SELECT id
FROM {PREFIX}pages
WHERE url = :url AND deleted_at IS NULL
LIMIT 1
