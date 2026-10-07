SELECT
    p.id,
    p.page_id,
    p.excerpt,
    p.body,
    p.hero_image,
    p.social_image,
    p.author_user_id,
    p.is_published,
    p.published_at,
    pg.url,
    pg.title,
    pg.description
FROM {TABLE} p
JOIN {PREFIX}pages pg ON pg.id = p.page_id AND pg.deleted_at IS NULL
WHERE p.id = :id
LIMIT 1
