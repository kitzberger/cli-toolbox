# TYPO3 Extension cli_toolbox

## Find records

Find all records (of a given table, type and subtype) within the pagetree of a given root uid:

```bash
bin/typo3 toolbox:find 123 [type] [subtype] [--columns=uid,pid,...] [--order=pid,CType] [--table=tt_content] [--depth=10] [--languages=0] [--count]
```

## Pagetree

Determine uids of all children in the pagetree of a given root uid:

```bash
bin/typo3 toolbox:tree 123 [--table=pages] [--depth=10] [--separator=,] [--languages=0]
```

A site identifier can be used instead of the root uid:

```bash
bin/typo3 toolbox:tree my-site-identifier
```

## Categorytree

Determine uids of all children in the categorytree of a given root uid:

```bash
bin/typo3 toolbox:tree 321 --table=sys_category
```

## Recursive delete

(!) Use with caution and backup!

```bash
bin/typo3 toolbox:delete --source=123 [--table=pages] [--memory-limit=512M]
```

## Copy/move records

See [TYPO3 datahandler](https://docs.typo3.org/typo3cms/CoreApiReference/ApiOverview/Typo3CoreEngine/Database/Index.html) for details on behaviour of positive/negative `--target` parameter

```bash
# Copy tt_content:123 to page:234
bin/typo3 toolbox:copy --table=tt_content --source=123 --target=234 [--be-user=1] [--memory-limit=512M]

# Copy tt_content:123 right behind tt_content:-234
bin/typo3 toolbox:copy --table=tt_content --source=123 --target=-234

# Move pages:123 to page:234
bin/typo3 toolbox:move --source=123 --target=234
```

## Move FAL folders across storages

To move a folder from one storage (`fileadmin`) to another you can use this command to

* Move all files within the given "source" to the given "target" folder
* Recalculate the file hashes

```bash
bin/typo3 toolbox:move-fal-folder 1:/folder/subfolder/ 2:/different-folder/subfolder
```
