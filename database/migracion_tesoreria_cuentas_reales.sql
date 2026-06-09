-- =====================================================================
-- Tesoreria: pasar de cuentas auto-generadas (metodo x moneda) a CUENTAS REALES
-- =====================================================================
-- Cambios de esquema (idempotentes, seguros de re-ejecutar):
--   (a) Quitar el UNIQUE(method_type, currency_code) que impedia tener dos bancos
--       con el mismo metodo+moneda (ej. BNC y Banesco en transferencia Bs).
--   (b) Agregar columna account_type (cash=caja, bank=banco, wallet=billetera).
--   (c) Backfill de account_type desde method_type.
--
-- Ejecutar:
--   docker exec -i bases_de_datos_mysql mysql -uroot -proot inventory_test < este_archivo.sql
-- En produccion: ajustar credenciales/base. Hacer backup antes.
-- =====================================================================

-- (a) Quitar UNIQUE si existe
SET @idx := (SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = 'cash_accounts'
               AND index_name = 'uniq_cash_account_method_currency');
SET @sql := IF(@idx > 0,
  'ALTER TABLE cash_accounts DROP INDEX uniq_cash_account_method_currency',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- (b) Agregar account_type si no existe
SET @col := (SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'cash_accounts'
               AND column_name = 'account_type');
SET @sql := IF(@col = 0,
  "ALTER TABLE cash_accounts ADD COLUMN account_type VARCHAR(20) NOT NULL DEFAULT 'bank' AFTER account_name",
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- (c) Backfill account_type desde method_type (solo filas aun en el default)
UPDATE cash_accounts
SET account_type = CASE
    WHEN LOWER(method_type) = 'cash' THEN 'cash'
    WHEN LOWER(method_type) IN ('usdt', 'zelle') THEN 'wallet'
    ELSE 'bank' END
WHERE account_type = 'bank' OR account_type = '' OR account_type IS NULL;
