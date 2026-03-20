# Architecture: magento-zend-db

## Purpose

Magento's fork of Zend Framework 1's `Zend_Db` database abstraction layer. Provides a unified API across multiple database backends (MySQL/MySQLi, PDO-MySQL, PDO-SQLite, Oracle, IBM DB2, MS SQL Server) with a fluent query builder, Table Data Gateway, and query profiling.

## Directory Structure

```
library/Zend/Db/
  Db.php                  — Factory: Zend_Db::factory($adapter, $config) creates adapter instances
  Adapter/
    Abstract.php          — Core adapter: connect, query, fetchAll, fetchRow, insert, update, delete
    Pdo/Abstract.php      — PDO-based base adapter
    Pdo/Mysql.php         — PDO MySQL adapter
    Pdo/Sqlite.php        — PDO SQLite adapter
    Pdo/Pgsql.php         — PDO PostgreSQL adapter
    Pdo/Mssql.php         — PDO MSSQL adapter
    Mysqli.php            — Native MySQLi adapter
    Oracle.php            — Native OCI8 adapter
    Sqlsrv.php            — Native SQLSRV (Microsoft) adapter
  Select.php              — Fluent SELECT query builder (from, join, where, order, limit, group)
  Expr.php                — Raw SQL expression wrapper (prevents parameter binding)
  Statement.php           — Base statement wrapper
  Statement/Pdo.php       — PDO statement
  Statement/Interface.php — Statement interface
  Table/
    Abstract.php          — Table Data Gateway: find, fetchAll, fetchRow, insert, update, delete
    Row/Abstract.php      — Active Record row; save() and delete() delegate to Table
    Rowset/Abstract.php   — Iterable collection of Row objects
    Select.php            — Table-aware Select (auto-adds FROM clause)
  Profiler.php            — Query profiler: records SQL, bind params, and elapsed time
```

## Key Design Decisions

- **Adapter pattern**: `Zend_Db_Adapter_Abstract` defines the contract; each backend overrides only the database-specific methods (quoting, connection, describe table)
- **Table Data Gateway + Row Data Gateway**: `Table_Abstract` manages the table; `Row_Abstract` represents a single row and knows its parent table for save/delete operations
- **Fluent Select**: `Zend_Db_Select` uses method chaining and builds SQL lazily in `__toString()`
- **Parameter binding**: All user values are bound via `?` or `:name` placeholders in `query()`, preventing SQL injection

## Extension Points

- Extend `Zend_Db_Adapter_Abstract` to add a new database backend
- Extend `Zend_Db_Table_Abstract` to add custom find methods and business logic
- Set a custom `Zend_Db_Profiler` on the adapter to record queries for debugging

## Dependency Flow

```
Zend_Db::factory('Pdo_Mysql', $config)
  → Adapter (connects to DB)
  → Select (builds SQL)
  → Statement (executes + fetches)
  → Table / Row / Rowset (Active Record layer)
```
