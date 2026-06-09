-- =====================================================================
-- Limpieza de cuentas de tesoreria con combinacion metodo x moneda imposible
-- =====================================================================
-- Contexto: las cuentas se sembraban como (metodo de pago x moneda), lo que
-- genero cuentas sin sentido (ej. "Pago movil USD"): el pago movil siempre es
-- en Bs. Este script:
--   1) Reubica los movimientos mal clasificados de "Pago movil USD" (id 7) a
--      "Pago movil VES" (id 8), convirtiendo su valor a Bs (amount_converted).
--   2) Desactiva las cuentas imposibles (pago movil/transferencia/punto de
--      venta en USD, y USDT/Zelle en VES).
--
-- Respaldo previo: database/backup_tesoreria_pre_migracion.sql
-- Ejecutar una sola vez. Es seguro re-ejecutarlo (idempotente por filtros).
-- =====================================================================

START TRANSACTION;

-- 1) Reubicar movimientos de "Pago movil USD" -> "Pago movil VES" (en Bs).
--    Se identifican por la cuenta de pago movil + moneda USD (imposible).
UPDATE cash_movements m
JOIN cash_accounts a ON a.id = m.cash_account_id
SET m.cash_account_id = (
        SELECT id FROM (SELECT * FROM cash_accounts) t
        WHERE t.method_type = 'mobile_payment' AND t.currency_code = 'VES'
        ORDER BY t.is_active DESC, t.id ASC LIMIT 1
    ),
    m.currency_code = 'VES',
    m.amount_original = m.amount_converted,
    m.exchange_rate = 1
WHERE a.method_type = 'mobile_payment'
  AND a.currency_code = 'USD';

-- 2) Desactivar todas las cuentas con moneda imposible para su metodo.
--    (pago movil/transferencia/punto de venta deben ser Bs; usdt/zelle deben ser USD)
UPDATE cash_accounts
SET is_active = 0
WHERE (method_type IN ('mobile_payment', 'bank_transfer', 'point_of_sale') AND currency_code = 'USD')
   OR (method_type IN ('usdt', 'zelle') AND currency_code = 'VES');

COMMIT;
