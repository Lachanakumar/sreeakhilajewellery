-- Refund status sync: orders left reporting a pre-refund payment state.
-- Safe to re-run: the statement is idempotent and only touches orders that are
-- already marked Refunded.

-- An order moved to Refunded before updateOrderStatus() also carried the
-- payment column across kept reporting its old payment state, so the customer's
-- order read "Refunded" with a payment of "Cancelled" or "Pending" beside it,
-- and the admin order view showed the same disagreement. Bring those rows onto
-- the value the same admin action writes today.
UPDATE orders
   SET payment_status = 'refunded'
 WHERE order_status = 'refunded'
   AND payment_status <> 'refunded';
