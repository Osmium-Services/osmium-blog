SELECT p.page_id
FROM {TABLE} p
WHERE p.is_published = 1
  AND p.published_at > :now
