# TYPO3 Extension cli_toolbox

## Recursive delete

(!) Use with caution and backup!

```
typo3/cli_dispatch.phpsh extbase cleanup:delete --pid=123 [--dry-run] [--memory-limit=512M]
```

## Copy/move records

See [TYPO3 datahandler](https://docs.typo3.org/typo3cms/CoreApiReference/ApiOverview/Typo3CoreEngine/Database/Index.html) for details on behaviour of positive/negative --pid parameter

```
# Copy tt_content:123 to page:234
typo3/cli_dispatch.phpsh extbase tcemain:copy --be-user=1 --uid=123 --pid=234 --table=tt_content [--dry-run] [--memory-limit=512M]

# Copy tt_content:123 right behind tt_content:-234
typo3/cli_dispatch.phpsh extbase tcemain:copy --be-user=1 --uid=123 --pid=-234 --table=tt_content [--dry-run] [--memory-limit=512M]

# Move tt_content:123 to page:234
typo3/cli_dispatch.phpsh extbase tcemain:move --be-user=1 --uid=123 --pid=234 --table=tt_content [--dry-run] [--memory-limit=512M]
```
