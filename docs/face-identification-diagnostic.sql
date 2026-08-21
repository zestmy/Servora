-- Face identification health — read-only diagnostic
--
-- Answers plan §7 Q1: "why did fingerprint come up — is face identification
-- failing, and where?" Run against production MySQL 8 BEFORE spending anything
-- on punch hardware. Every statement is a SELECT; nothing here writes.
--
--   mysql -u <user> -p <database> < docs/face-identification-diagnostic.sql
--
-- READ THIS FIRST — the numbers below are a FLOOR, not a measure.
--
-- KioskController::identify() records nothing. It is a read-only endpoint by
-- design, so an UNKNOWN, NO_FACES or BAD_INPUT outcome is returned to the
-- screen and never persisted. Somebody who tries the camera three times, gives
-- up and keys their PIN leaves ONE trace: the `pin_fallback` flag on the punch
-- that eventually happened. Somebody who tries and walks away leaves none.
--
-- So `pin_fallback` is the honest proxy for "the camera did not name them",
-- and every rate here understates the problem rather than overstating it. If
-- these come back bad, they are bad. If they come back clean, that is weaker
-- evidence than it looks — see the note at the end.

-- ---------------------------------------------------------------------------
-- 1. WHERE. Per outlet and kiosk: how often did the camera fail to name
--    somebody? Sort by pct_pin_fallback and read the top row first.
-- ---------------------------------------------------------------------------
SELECT
    o.name                                        AS outlet,
    COALESCE(d.name, '(device removed)')          AS kiosk,
    COUNT(*)                                      AS punches,
    COALESCE(SUM(JSON_CONTAINS(e.flags, '"pin_fallback"')), 0)   AS pin_fallback,
    COALESCE(SUM(JSON_CONTAINS(e.flags, '"face_ambiguous"')), 0) AS ambiguous,
    COALESCE(SUM(JSON_CONTAINS(e.flags, '"not_enrolled"')), 0)   AS not_enrolled,
    COALESCE(SUM(JSON_CONTAINS(e.flags, '"no_face"')), 0)        AS no_face,
    COALESCE(SUM(JSON_CONTAINS(e.flags, '"face_mismatch"')), 0)  AS mismatch,
    ROUND(100 * COALESCE(SUM(JSON_CONTAINS(e.flags, '"pin_fallback"')), 0)
              / NULLIF(COUNT(*), 0), 1)           AS pct_pin_fallback
FROM clock_events e
JOIN outlets o           ON o.id = e.outlet_id
LEFT JOIN clock_devices d ON d.id = e.clock_device_id
WHERE e.deleted_at IS NULL
  AND e.source     = 'kiosk'
  AND e.happened_at >= NOW() - INTERVAL 90 DAY
GROUP BY o.name, kiosk
ORDER BY pct_pin_fallback DESC;

-- ---------------------------------------------------------------------------
-- 2. WHEN. The same rate by hour. This is the query that tests the "6am in a
--    steamy prep area, hairnet and fogged glasses" hypothesis — if the
--    fallback rate spikes at one end of the day, the problem is light or
--    conditions, not the model.
-- ---------------------------------------------------------------------------
SELECT
    HOUR(e.happened_at)                           AS hour_of_day,
    COUNT(*)                                      AS punches,
    COALESCE(SUM(JSON_CONTAINS(e.flags, '"pin_fallback"')), 0)   AS pin_fallback,
    ROUND(100 * COALESCE(SUM(JSON_CONTAINS(e.flags, '"pin_fallback"')), 0)
              / NULLIF(COUNT(*), 0), 1)           AS pct_pin_fallback
FROM clock_events e
WHERE e.deleted_at IS NULL
  AND e.source     = 'kiosk'
  AND e.happened_at >= NOW() - INTERVAL 90 DAY
GROUP BY hour_of_day
ORDER BY hour_of_day;

-- ---------------------------------------------------------------------------
-- 3. HOW CLOSE. Successful matches only. face_distance is the distance to the
--    winner — SMALLER IS BETTER, and it must beat kiosk_face_threshold
--    (default 0.450). A kiosk whose p90 is crowding the threshold is one bad
--    bulb away from failing, and will look fine in query 1 until it does.
-- ---------------------------------------------------------------------------
SELECT
    o.name                                        AS outlet,
    COALESCE(d.name, '(device removed)')          AS kiosk,
    COUNT(e.face_distance)                        AS matched_punches,
    ROUND(MIN(e.face_distance), 3)                AS best,
    ROUND(AVG(e.face_distance), 3)                AS mean,
    ROUND(MAX(e.face_distance), 3)                AS worst
FROM clock_events e
JOIN outlets o            ON o.id = e.outlet_id
LEFT JOIN clock_devices d ON d.id = e.clock_device_id
WHERE e.deleted_at IS NULL
  AND e.source        = 'kiosk'
  AND e.face_distance IS NOT NULL
  AND e.happened_at  >= NOW() - INTERVAL 90 DAY
GROUP BY o.name, kiosk
ORDER BY mean DESC;

-- The bar those numbers have to clear, per company:
SELECT c.name AS company,
       s.kiosk_face_threshold,
       s.kiosk_face_margin,
       s.kiosk_allow_pin,
       s.face_threshold        AS byod_1to1_threshold
FROM clock_settings s
JOIN companies c ON c.id = s.company_id;

-- ---------------------------------------------------------------------------
-- 4. WHO. Enrolment coverage. The cheapest possible explanation for a bad
--    identification rate is that people were never enrolled — check this
--    before concluding anything about cameras or hardware.
-- ---------------------------------------------------------------------------
SELECT
    o.name                                        AS outlet,
    COUNT(*)                                      AS active_staff,
    SUM(CASE WHEN fd.employee_id IS NOT NULL THEN 1 ELSE 0 END) AS enrolled,
    COUNT(*) - SUM(CASE WHEN fd.employee_id IS NOT NULL THEN 1 ELSE 0 END) AS not_enrolled,
    ROUND(100 * SUM(CASE WHEN fd.employee_id IS NOT NULL THEN 1 ELSE 0 END)
              / NULLIF(COUNT(*), 0), 1)           AS pct_enrolled
FROM employees em
JOIN outlets o ON o.id = em.outlet_id
LEFT JOIN (SELECT DISTINCT employee_id FROM employee_face_descriptors) fd
       ON fd.employee_id = em.id
WHERE em.deleted_at IS NULL
  AND em.is_active = 1
GROUP BY o.name
ORDER BY pct_enrolled ASC;

-- ---------------------------------------------------------------------------
-- 5. CONTEXT. Are kiosks even being used? A high pin_fallback rate at an
--    outlet nobody punches at is noise; a low one at an outlet where everyone
--    is on BYOD anyway says nothing about the camera.
-- ---------------------------------------------------------------------------
SELECT
    o.name                                        AS outlet,
    e.source,
    COUNT(*)                                      AS punches
FROM clock_events e
JOIN outlets o ON o.id = e.outlet_id
WHERE e.deleted_at IS NULL
  AND e.happened_at >= NOW() - INTERVAL 90 DAY
GROUP BY o.name, e.source
ORDER BY o.name, punches DESC;

-- ---------------------------------------------------------------------------
-- IF THESE COME BACK CLEAN
--
-- That is not proof the camera is fine — it is the absence of evidence that it
-- is not, and the blind spot at the top is why. The cheap fix, and it is far
-- cheaper than any hardware in the plan: persist the identify() outcome
-- (UNKNOWN / NO_FACES / BAD_INPUT / AMBIGUOUS) with its device and timestamp.
-- One small table, no change to how punching works, and a fortnight later this
-- question has a real answer instead of a floor.
--
-- Do that before buying anything if query 1 is ambiguous.
-- ---------------------------------------------------------------------------
