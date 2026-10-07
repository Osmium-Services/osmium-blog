SELECT
    pg.id,
    pg.url,
    pg.title,
    CASE
        WHEN bp.id IS NOT NULL THEN 'post'
        WHEN pp.page_id IS NOT NULL THEN 'product'
        WHEN cp.page_id IS NOT NULL THEN 'category'
        ELSE 'page'
    END AS type
FROM {PREFIX}pages pg
LEFT JOIN {PREFIX}products_pages pp ON pp.page_id = pg.id AND pp.is_qr = 0
LEFT JOIN {PREFIX}productcategories_pages cp ON cp.page_id = pg.id AND cp.deleted_at IS NULL
LEFT JOIN {TABLE} bp ON bp.page_id = pg.id
WHERE pg.deleted_at IS NULL
  AND pg.url <> 'error'
  AND pg.id NOT IN (SELECT page_id FROM {PREFIX}products_pages WHERE is_qr = 1)
  AND (bp.id IS NULL OR bp.is_published = 1)
ORDER BY type, pg.title, pg.url
