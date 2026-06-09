-- =====================================================================
-- Dejar SOLO dos cuentas de tesoreria activas: BNC (Bs) y Caja en efectivo (USD).
-- =====================================================================
-- No borra cuentas (conserva el historial de cash_movements via FK): desactiva
-- todas y reutiliza dos filas existentes renombrandolas. Los codigos 1011302 y
-- 1011101 provienen de la semilla original (Transferencia VES y Caja USD).
--
-- Despues podras agregar/editar mas cuentas desde Ajustes > Cuentas de tesoreria.
-- Ejecutar:
--   docker exec -i bases_de_datos_mysql mysql -uroot -proot inventory_test < este_archivo.sql
-- En produccion: ajustar credenciales/base y hacer backup antes.
-- =====================================================================

START TRANSACTION;

-- 1) Desactivar todas las cuentas.
UPDATE cash_accounts SET is_active = 0;

-- 2) BNC en bolivares (reutiliza la fila de Transferencia VES, codigo 1011302).
UPDATE cash_accounts
SET account_name = 'BNC - Banco Nacional de Credito',
    account_type = 'bank',
    method_type  = 'bank_transfer',
    currency_code = 'VES',
    is_active = 1
WHERE account_code = '1011302';

-- 3) Caja en efectivo en dolares (reutiliza la fila Caja USD, codigo 1011101).
UPDATE cash_accounts
SET account_name = 'Caja en efectivo',
    account_type = 'cash',
    method_type  = 'cash',
    currency_code = 'USD',
    is_active = 1
WHERE account_code = '1011101';

-- 4) Si por alguna razon esos codigos no existian, crear las cuentas.
INSERT INTO cash_accounts (account_code, account_name, account_type, method_type, currency_code, opening_balance, is_active)
SELECT '1011302', 'BNC - Banco Nacional de Credito', 'bank', 'bank_transfer', 'VES', 0, 1
WHERE NOT EXISTS (SELECT 1 FROM cash_accounts WHERE account_name = 'BNC - Banco Nacional de Credito');

INSERT INTO cash_accounts (account_code, account_name, account_type, method_type, currency_code, opening_balance, is_active)
SELECT '1011101', 'Caja en efectivo', 'cash', 'cash', 'USD', 0, 1
WHERE NOT EXISTS (SELECT 1 FROM cash_accounts WHERE account_name = 'Caja en efectivo');

-- 5) Revisar antes de confirmar.
SELECT account_code, account_name, account_type, currency_code, is_active
FROM cash_accounts ORDER BY is_active DESC, account_code;

COMMIT;  -- si algo se ve mal: ROLLBACK;
