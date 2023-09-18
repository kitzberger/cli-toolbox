# TYPO3 Extension cli_toolbox

## Pagetree

Determine uids of all children in the pagetree of a given site identifier or root uid:

```bash
bin/typo3 toolbox:tree my-site-identifier
bin/typo3 toolbox:tree 123
```

## Recursive delete

(!) Use with caution and backup!

```bash
bin/typo3 toolbox:delete --pid=123 [--dry-run] [--memory-limit=512M]
```

## Copy/move records

See [TYPO3 datahandler](https://docs.typo3.org/typo3cms/CoreApiReference/ApiOverview/Typo3CoreEngine/Database/Index.html) for details on behaviour of positive/negative --pid parameter

```bash
# Copy tt_content:123 to page:234
bin/typo3 toolbox:copy --be-user=1 --uid=123 --pid=234 --table=tt_content [--dry-run] [--memory-limit=512M]

# Copy tt_content:123 right behind tt_content:-234
bin/typo3 toolbox:copy --be-user=1 --uid=123 --pid=-234 --table=tt_content [--dry-run] [--memory-limit=512M]

# Move tt_content:123 to page:234
bin/typo3 toolbox:move --be-user=1 --uid=123 --pid=234 --table=tt_content [--dry-run] [--memory-limit=512M]
```
