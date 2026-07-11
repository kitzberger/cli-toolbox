# cli_toolbox

TYPO3 CMS extension providing CLI commands for DataHandler operations (copy/move/delete) and record queries.

## Project Type

TYPO3 extension (`typo3-cms-extension`), not a standalone PHP application. Requires a running TYPO3 instance to function.

## Code Structure

- `Classes/Command/` - Symfony Console commands (registered via `Configuration/Services.yaml`)
- `Classes/Database/QueryGenerator.php` - Fork of deprecated TYPO3 QueryGenerator for recursive tree traversal
- `Configuration/Services.yaml` - Command registration with `console.command` tags

## Key Architecture

- `MoveCommand` extends `CopyCommand` (only overrides `$action` property)
- All commands extend `AbstractCommand` which provides `$io` (SymfonyStyle) and `outputLine()` helper
- Commands use TYPO3's DataHandler API for copy/move/delete operations
- `FindCommand` and `TreeCommand` use QueryGenerator for pagetree traversal

## Commands

All commands are prefixed with `toolbox:` and run via `bin/typo3 toolbox:<command>`:
- `toolbox:find` - Query records with filtering/grouping/pagination
- `toolbox:tree` - Get page/category tree UIDs
- `toolbox:copy` - Copy records via DataHandler
- `toolbox:move` - Move records via DataHandler
- `toolbox:delete` - Delete records (requires confirmation)
- `toolbox:move-fal-folder` - Move FAL folders across storages

## Code Style

- PSR-4 autoloading: `Kitzberger\CliToolbox\` → `Classes/`
- 4-space indentation, LF line endings (`.editorconfig`)
- No strict_types declaration in most files (except QueryGenerator.php)
- TYPO3 coding conventions (GeneralUtility::makeInstance for DI, TCA access via `$GLOBALS['TCA']`)

## Compatibility

- TYPO3: 11.5, 12.4, 13.4
- PHP: No explicit constraint in composer.json (relies on TYPO3 requirements)

## Testing

No test suite exists in this repository.

## Gotchas

- `QueryGenerator.php` is a fork of deprecated TYPO3 core class - avoid modifying unless updating the fork
- Commands bypass access checks (`$dataHandler->bypassAccessCheckForRecords = true`) - this is intentional for CLI operations
- `FindCommand` has hardcoded table aliases (e.g., `news` → `tx_news_domain_model_news`)
- `FindCommand` has hardcoded extra columns for powermail tables
- `FindCommand` has a custom SQL-like WHERE parser (`--where`/`-w`) in private methods `parseWhereConditions()` and `parseWhereValue()` — regex-based, not a full SQL parser; supports `=`, `!=`, `<>`, `>`, `>=`, `<`, `<=`, `LIKE`, `NOT LIKE`, `IN`, `NOT IN`, `IS NULL`, `IS NOT NULL` with AND/OR conjunctions
