-- Read-only MySQL 8 preflight. Run with booking writes paused before migrations.
SELECT readable_id, COUNT(*) AS duplicate_count FROM bookings
WHERE readable_id IS NOT NULL GROUP BY readable_id HAVING COUNT(*) > 1 ORDER BY readable_id;
SELECT MAX(readable_id) AS largest_existing_reference,
       SUM(readable_id IS NULL) AS missing_reference_count FROM bookings;
