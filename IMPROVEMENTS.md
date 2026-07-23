# Improvements & Refactoring Suggestions

This document tracks potential improvements to the codebase based on SOLID principles and clean code practices.

---

## High Priority

### 1. Silent Exception Catching
**Location:** `app/Services/Snapshot/DatabaseSnapshot.php` (lines 154-159, 165-177, 197-205)

**Problem:** Empty catch blocks swallow errors with no logging, making debugging impossible.

```php
// Current - bad
catch (\Throwable) {
    // silently fails
}

// Suggested
catch (\Throwable $e) {
    \Log::warning('Failed to find EE tables', ['error' => $e->getMessage()]);
}
```

---

### 2. VerifyCommand Does Too Much (SRP Violation)
**Location:** `app/Commands/VerifyCommand.php` (169 lines)

**Problem:** Mixes orchestration, presentation, file loading, and complex ternary formatting.

**Suggestion:** Extract into separate classes:
- `VerificationReporter` - handles rendering verification results
- `SnapshotLoader` - handles finding and loading snapshot files

---

### 3. DatabaseSnapshot Violates SRP
**Location:** `app/Services/Snapshot/DatabaseSnapshot.php` (209 lines)

**Problem:** Does 8 things: captures counts, checksums, EE tables, row_id columns, sequence tables, saves files, loads files, and coordinates.

**Suggestion:** Split into:
- `DatabaseSnapshotCapture` - handles all data capture logic
- `DatabaseSnapshotStorage` - handles file I/O (save/load)
- `DatabaseSnapshot` - pure value object representing the data
- `DatabaseSnapshotService` - thin coordinator

---

## Medium Priority

### 4. Hardcoded Instance Creation (DIP Violation)
**Location:** `app/Services/Composer/ComposerMigrator.php:20`

**Problem:** Creates `new ComposerAnalyser()` instead of injecting `ComposerAnalyserInterface`.

```php
// Current - bad
$analyser = new ComposerAnalyser();

// Suggested - inject via constructor
public function __construct(
    private ComposerAnalyserInterface $analyser
) {}
```

---

### 5. DisclaimerService Uses exit()
**Location:** `app/Services/DisclaimerService.php:32`

**Problem:** Calls `exit(1)` directly, making unit testing impossible.

```php
// Current - bad
if ($choice !== 'I Agree') {
    $command->error('You did not agree to the terms. Exiting.');
    exit(1);
}

// Suggested - throw exception
if ($choice !== 'I Agree') {
    throw new DisclaimerNotAcceptedException('User did not accept terms.');
}
```

Let the command catch and handle the exception.

---

### 6. Commands Include Config Files Directly
**Location:** `app/Commands/DatabaseMigrateCommand.php:42`, `app/Commands/VerifyCommand.php:37`

**Problem:** Direct `include` of `env.php` mixes I/O with business logic.

```php
// Current - bad
$envPhp = include $magentoPath . '/app/etc/env.php';

// Suggested - inject service
interface MagentoConfigReaderInterface {
    public function loadDatabaseConfig(string $magentoPath): DatabaseConfig;
}
```

---

### 7. SqlFileRunner Mixes Concerns (SRP Violation)
**Location:** `app/Services/Database/SqlFileRunner.php` (169 lines)

**Problem:** File system operations, SQL execution, and SQL parsing all in one class.

**Suggestion:** Extract:
- `SqlFileLocator` - `getSqlFiles()` method
- `SqlStatementParser` - `splitStatements()` method
- `SqlFileRunner` - focused on executing files

---

## Low Priority

### 8. Duplicate Error Messages
**Location:** `app/Services/Database/SqlFileRunner.php:43-56`

**Problem:** Same message for "directory missing" vs "no SQL files".

```php
// Current - confusing
throw new \RuntimeException("No SQL files found in [{$this->sqlDirectory}]");

// Suggested - distinct messages
throw new \RuntimeException("SQL directory does not exist: [{$this->sqlDirectory}]");
throw new \RuntimeException("No .sql files found in [{$this->sqlDirectory}]");
```

---

### 9. ComposerAnalysis Stores Raw Array
**Location:** `app/ValueObjects/ComposerAnalysis.php`

**Problem:** `$data` property exposes entire composer.json array, defeating encapsulation.

**Suggestion:** Remove raw `$data` property and only expose specific needed properties through dedicated getters.

---

### 10. Generic Array Types
**Location:** `app/ValueObjects/SnapshotReport.php`

**Problem:** No docblocks for array shapes - no IDE autocomplete or type safety.

```php
// Current
public array $tableCounts,

// Suggested
/** @param array<string, int|null> $tableCounts */
public array $tableCounts,
```

---

### 11. Public Method Not in Interface
**Location:** `app/Services/Database/SqlFileRunner.php:129`

**Problem:** `splitStatements()` is public but not declared in `SqlRunnerInterface`.

**Suggestion:** Either make it `private` (if internal) or add to interface (if clients need it).

---

### 12. Inconsistent Error Handling
**Location:** `app/Services/Snapshot/DatabaseSnapshot.php`

**Problem:** Methods handle exceptions differently:
- `captureTableCounts()` returns `null`
- `findEeTables()` returns empty array silently
- `findRowIdColumns()` returns empty array
- `findSequenceTables()` returns empty array

**Suggestion:** Standardize - all methods should log errors consistently.

---

## Checklist

- [ ] Add exception logging to DatabaseSnapshot
- [ ] Extract VerificationReporter from VerifyCommand
- [ ] Split DatabaseSnapshot into separate classes
- [ ] Inject ComposerAnalyserInterface in ComposerMigrator
- [ ] Replace exit() with exception in DisclaimerService
- [ ] Create MagentoConfigReaderInterface
- [ ] Extract SqlFileLocator and SqlStatementParser
- [ ] Fix duplicate error messages in SqlFileRunner
- [ ] Remove raw $data from ComposerAnalysis
- [ ] Add docblocks for array shapes
- [ ] Decide on splitStatements() visibility
- [ ] Standardize error handling in DatabaseSnapshot
