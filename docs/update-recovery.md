# Native update recovery and diagnostics

The Rescue Plugin Suite updater leaves WordPress core responsible for unpacking,
replacing plugin files, temporary backups, rollback, maintenance mode, and the
plugin activation state. It does not delete, copy, rename, activate, deactivate,
or load the replacement plugin bootstrap.

## Capturing a failed disposable update

Before removing or changing any files, capture the following with timestamps:

```sh
find wp-content/plugins/rescue-plugin-suite -printf '%TY-%Tm-%Td %TT %p\n' | sort
find wp-content/upgrade -maxdepth 2 -printf '%TY-%Tm-%Td %TT %p\n' | sort
stat wp-content/.maintenance || true
```

Also record the `Version:` header from
`wp-content/plugins/rescue-plugin-suite/plugin-ui-suite.php`, the value WordPress
shows on Plugins, activation status, and any sibling `rescue-plugin-suite*`
directories. WordPress core's temporary backup location and rollback result must
be recorded as observed; this plugin does not remove either.

## Updater diagnostic log

For native update requests, lifecycle records are appended (best effort) to:

```
wp-content/uploads/rescue-plugin-suite-update.log
```

Entries contain only updater stage, version, package/destination context and
activation state. A shutdown handler adds `error_get_last()` details for fatal,
parse, core, compile, and user errors. The logger suppresses its own filesystem
errors so diagnostics cannot break an update.

## Callback inventory

| Hook | Callback | Priority / arguments | Behaviour |
| --- | --- | --- | --- |
| `upgrader_package_options` | `Plugin_UI_Suite_Updater::package_options` | 10 / 1 | Logs before/after only; no file or activation changes. |
| `upgrader_pre_install` | `Plugin_UI_Suite_Updater::pre_install` | 10 / 3 | Logs before/after only; no file or activation changes. |
| `upgrader_process_complete` | `Plugin_UI_Suite_Updater::after_upgrade` | 10 / 2 | Parses the installed header as text and logs it; no migration, cache clearing, or activation change. |
| `shutdown` | `Plugin_UI_Suite_Updater::capture_shutdown_error` | PHP shutdown handler / 0 | Best-effort fatal diagnostic logging only. |
| activation | `Plugin_UI_Suite_Plugin::activate` | WordPress activation hook / 0 | Normal activation only; it is not called by the updater. |

There is intentionally no `upgrader_source_selection`,
`update_plugins_complete_actions`, or deactivation callback. The release ZIP has
the canonical `rescue-plugin-suite/` root, so `Plugin_Upgrader` performs the
replacement without custom file handling.
