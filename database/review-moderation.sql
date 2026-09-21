-- Review moderation: why a review was rejected, and who decided.
-- Safe to re-run: every ALTER is guarded by IF NOT EXISTS.

-- Shown to the admin on the Reviews list so a rejection can be explained and
-- revisited later, rather than the decision being a status with no context.
ALTER TABLE reviews
    ADD COLUMN IF NOT EXISTS rejection_reason VARCHAR(255) NULL AFTER status;

-- When the review was last approved or rejected. NULL while still pending.
ALTER TABLE reviews
    ADD COLUMN IF NOT EXISTS moderated_at DATETIME NULL AFTER rejection_reason;

-- Reviews decided before this column existed keep their status but have no
-- timestamp to show; fall back to when the review was posted.
UPDATE reviews
   SET moderated_at = created_at
 WHERE moderated_at IS NULL
   AND status <> 'pending';
