# Magento 2 EE → CE Migration Tool

A CLI tool by [Deploy Ecommerce](https://www.deploy.co.uk) to migrate a Magento 2/Adobe Commerce **Enterprise Edition** installation to **Magento Open Source (Community Edition)**.

---

## Beta

This is still very much a beta tool and is currently being tested with Magento EE versions 2.4.6 and 2.4.7.

Feature requests, comments and feedback are all very much welcome.


## Disclaimer & Data Loss Warning



> **THIS TOOL MAKES IRREVERSIBLE CHANGES TO YOUR DATABASE AND PROJECT FILES.**

```
╔══════════════════════════════════════════════════════════════════╗
║        ⚠  DESTRUCTIVE OPERATION — READ BEFORE PROCEEDING  ⚠      ║
╠══════════════════════════════════════════════════════════════════╣
║                                                                  ║
║  This tool will permanently modify your Magento installation:    ║
║                                                                  ║
║  • Drops approximately 90 EE-only database tables                ║
║  • Rewrites primary keys (row_id → entity_id) across core tables ║
║  • Removes EE staging sequence tables                            ║
║  • Modifies your project's composer.json                         ║
║                                                                  ║
║  DATA LOSS IS POSSIBLE. These changes cannot be automatically    ║
║  rolled back. You MUST take a full database backup (mysqldump)   ║
║  before running this tool. No backup = no recovery.              ║
║                                                                  ║
╠══════════════════════════════════════════════════════════════════╣
║  DISCLAIMER                                                      ║
║                                                                  ║
║  This software is provided "as is", without warranty of any      ║
║  kind, express or implied. Deploy Ecommerce Ltd and its          ║
║  contributors shall not be liable for any direct, indirect,      ║
║  incidental, special, or consequential damages (including but    ║
║  not limited to data loss, system downtime, or loss of           ║
║  business) arising from the use of or inability to use this      ║
║  tool, even if advised of the possibility of such damages.       ║
║                                                                  ║
║  Use of this tool is entirely at your own risk. By proceeding    ║
║  you confirm you have taken an appropriate backup and accept      ║
║  full responsibility for the outcome.                            ║
╚══════════════════════════════════════════════════════════════════╝
```

This disclaimer is displayed every time the tool runs. You must select **"I Agree"** to proceed, or pass `--accept-terms` to skip the prompt (for automated/scripted use).

---

## What It Does

The tool runs three steps in sequence:

1. **Database migration** — drops ~90 EE-only tables, rewrites `row_id` → `entity_id` primary keys across all core entity tables, and removes EE-specific staging sequence tables
2. **Composer migration** — swaps `magento/product-enterprise-edition` for `magento/product-community-edition` and removes the EE `replace` block from `composer.json`
3. **Verification** — takes a post-migration snapshot and compares it against the pre-migration baseline to confirm everything was cleaned up correctly

---

## Requirements

- PHP 8.2+
- Access to the Magento database (credentials read from `app/etc/env.php`)
- Run from a directory where the tool can write snapshot and log files

---

## Installation

Download the latest `magento2-ee-to-ce` PHAR from the releases page and make it executable:

```bash
chmod +x magento2-ee-to-ce
```

Place it anywhere on your `PATH` or run it directly from any directory.

---

## Usage

### Full migration (recommended)

Runs all three steps — database migration, composer migration, then verification:

```bash
./magento2-ee-to-ce migrate --path=/var/www/magento
```

On success, the tool prints the post-migration steps you need to complete manually (composer update, setup:upgrade, etc.).

### Options

| Option | Description |
|---|---|
| `--path=<dir>` | Path to the Magento root directory. Defaults to the current working directory. |
| `--dry-run` | Parse and analyse without making any changes to the database or `composer.json`. |
| `--accept-terms` | Skip the interactive disclaimer prompt. Useful for scripted/CI use. |

---

## Commands

### `migrate`

The main entrypoint. Runs `db:migrate`, `composer:migrate`, and `verify` in order. If any step fails it stops and reports which step to resume from.

```bash
./magento2-ee-to-ce migrate --path=/var/www/magento
./magento2-ee-to-ce migrate --path=/var/www/magento --dry-run
./magento2-ee-to-ce migrate --path=/var/www/magento --accept-terms
```

---

### `db:migrate`

Runs the 12 SQL migration files against the Magento database. Before executing any SQL, it saves a pre-migration snapshot (`snapshot-before-{timestamp}.json`) to the current working directory for use by `verify` later.

```bash
./magento2-ee-to-ce db:migrate --path=/var/www/magento
```

**Resuming a failed migration**

If the migration stops mid-way (e.g. a SQL error at file 7), fix the issue and resume from that file number:

```bash
./magento2-ee-to-ce db:migrate --path=/var/www/magento --from=7
```

**Options**

| Option | Description |
|---|---|
| `--path=<dir>` | Path to the Magento root directory |
| `--dry-run` | Capture a snapshot and log the SQL files without executing them |
| `--from=<N>` | Start from SQL file number N (default: 1) |
| `--accept-terms` | Skip the disclaimer prompt |

**SQL files executed**

| # | File | What it does |
|---|---|---|
| 01 | `attributes.sql` | Removes EE-specific EAV entity type records |
| 02 | `ee.sql` | Drops ~90 EE-only tables |
| 03 | `cms.sql` | Rewrites CMS page/block `row_id` → `entity_id` |
| 04 | `catalogrule.sql` | Rewrites catalog rule `row_id` → `rule_id` |
| 05 | `salesrule.sql` | Rewrites sales rule `row_id` → `rule_id` |
| 06 | `category.sql` | Rewrites category `row_id` → `entity_id` |
| 07 | `product.sql` | Rewrites product `row_id` → `entity_id` (most complex) |
| 08 | `cataloginventory.sql` | Drops EE columns from inventory tables |
| 09 | `customer.sql` | Drops EE columns and indexes from customer tables |
| 10 | `quote.sql` | Drops EE columns from quote tables |
| 11 | `sales.sql` | Drops EE columns from order/invoice/creditmemo tables |
| 12 | `wishlist.sql` | Drops EE columns from wishlist, fixes unique key |

All SQL executed is logged to `ee-to-ce-migration-{timestamp}.sql.log` in the current working directory.

---

### `composer:migrate`

Updates `composer.json` to switch from EE to CE:

- Removes `magento/product-enterprise-edition`
- Adds `magento/product-community-edition` at the equivalent version (patch suffix stripped)
- Removes `magento/*` entries from the `replace` section (non-Magento replace entries are preserved)

```bash
./magento2-ee-to-ce composer:migrate --path=/var/www/magento
./magento2-ee-to-ce composer:migrate --path=/var/www/magento --dry-run
```

**Conflict and repository detection**

The tool will warn you about:

- **EE-dependent packages** — packages like `magento/module-staging` that won't be available in CE
- **Enterprise repositories** — repository URLs like `composer.amasty.com/enterprise/` that may need switching to community equivalents

Example warning output:

```
Enterprise repositories detected:
  ⚠ Found in composer.json: composer.amasty.com/enterprise/
    Amasty Enterprise repository - may need to switch to community version

  ℹ These repositories may need to be updated to their community equivalents.
```

**Options**

| Option | Description |
|---|---|
| `--path=<dir>` | Path to the Magento root directory |
| `--dry-run` | Report what would change without writing to `composer.json` |
| `--accept-terms` | Skip the disclaimer prompt |

---

### `verify`

Takes a post-migration snapshot of the database and compares it against the pre-migration baseline. Prints a summary of schema changes and data integrity checks, and exits with a non-zero code if anything looks wrong.

```bash
./magento2-ee-to-ce verify --path=/var/www/magento
```

The tool automatically finds the most recent `snapshot-before-*.json` file in the current working directory. To specify a different snapshot:

```bash
./magento2-ee-to-ce verify --path=/var/www/magento --snapshot=/path/to/snapshot-before-20260101-120000.json
```

**Pass criteria** — all three must be true:

1. No EE-specific tables remain
2. No `row_id` columns remain (excluding flat catalog tables, which are regenerated by the indexer)
3. No EE-specific staging sequence tables remain

The verification report also shows before/after row counts for key tables (`catalog_product_entity`, `catalog_category_entity`, `cms_page`, `cms_block`, `customer_entity`, `sales_order`, `quote`, `catalogrule`) and flags any tables that lost rows.

**Options**

| Option | Description |
|---|---|
| `--path=<dir>` | Path to the Magento root directory |
| `--snapshot=<path>` | Path to a specific before-snapshot JSON file |
| `--accept-terms` | Skip the disclaimer prompt |

---

### `scan:row-id`

Scans your codebase for `row_id` references that may need updating after migration. The EE staging/versioning system uses `row_id` as a primary key pattern — any custom or third-party extensions referencing `row_id` will break after migration to CE.

```bash
# Scan both app/code and vendor directories
./magento2-ee-to-ce scan:row-id --path=/var/www/magento

# Scan only custom extensions (app/code)
./magento2-ee-to-ce scan:row-id:custom --path=/var/www/magento

# Scan only third-party extensions (vendor, excluding magento/*)
./magento2-ee-to-ce scan:row-id:vendor --path=/var/www/magento
```

**Output formats**

The default output groups results by extension with file:line references:

```
┌────────────────────────────────────────────────────────────────┐
│  Custom Extensions (app/code)                                  │
└────────────────────────────────────────────────────────────────┘

⚠ Vendor_CustomModule (3 references)
   → Model/Product.php:45
   → Plugin/StagingPlugin.php:23
   → Plugin/StagingPlugin.php:67

┌────────────────────────────────────────────────────────────────┐
│  Third-Party Extensions (vendor/)                              │
└────────────────────────────────────────────────────────────────┘

⚠ amasty/module-special-promo (2 references)
   → Model/Rule.php:112
   → Model/Rule.php:156
```

Use `--json` or `--markdown` for machine-readable or documentation-friendly output:

```bash
# JSON output (for piping to jq or other tools)
./magento2-ee-to-ce scan:row-id --path=/var/www/magento --json --accept-terms

# Markdown output (for reports/documentation)
./magento2-ee-to-ce scan:row-id --path=/var/www/magento --markdown --accept-terms > row_id_report.md
```

**JSON output format:**

```json
[
    {
        "file": "/var/www/magento/app/code/Vendor/Module/Model/Product.php",
        "line": 45,
        "extension": "Vendor_Module"
    }
]
```

**Markdown output format:**

```markdown
# row_id References

Found **2** references that may need updating after EE to CE migration.

| Extension | File | Line |
|-----------|------|------|
| Vendor_Module | `/var/www/magento/app/code/Vendor/Module/Model/Product.php` | 45 |
| amasty/promo | `/var/www/magento/vendor/amasty/promo/Rule.php` | 112 |
```

**Options**

| Option | Description |
|---|---|
| `--path=<dir>` | Path to the Magento root directory |
| `--json` | Output results as JSON |
| `--markdown` | Output results as Markdown |
| `--accept-terms` | Skip the disclaimer prompt |

---

## After a Successful Migration

Once the tool reports **VERIFICATION PASSED**, complete the migration by running the following in your Magento root:

```bash
composer update --no-dev
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy
bin/magento cache:flush
```

> On Adobe Commerce Cloud, do not run `setup:upgrade` directly. Push to your Cloud environment and let ECE-Tools handle the deploy.

---

## Output Files

Two files are written to the directory from which you run the tool:

| File | Description |
|---|---|
| `snapshot-before-{timestamp}.json` | Pre-migration database snapshot (EE table presence, row counts, checksums) |
| `ee-to-ce-migration-{timestamp}.sql.log` | Full log of every SQL statement executed, with affected row counts and timing |

---

## Acknowledgements

This tool was inspired by and uses SQL queries derived from [opengento/magento2-downgrade-ee-ce](https://github.com/opengento/magento2-downgrade-ee-ce). Many thanks to the contributors of that project for their foundational work.

---

## License

MIT — see [LICENSE.md](LICENSE.md)
