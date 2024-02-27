# TYPO3 Extension cli_toolbox

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
bin/typo3 toolbox:delete --pid=123 [--memory-limit=512M]
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
