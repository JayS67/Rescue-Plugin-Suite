<?php
/** Fixture only: wp eval-file tests/update-preferences.php */
if (!defined('ABSPATH') || !class_exists('Plugin_UI_Suite_Updater')) throw new RuntimeException('Run with the suite active in a disposable WordPress installation.');
$old_native = get_site_option('auto_update_plugins', []);
$old_legacy = get_option(Plugin_UI_Suite_Updater::AUTO_UPDATES_KEY, null);
try {
  $file = Plugin_UI_Suite_Updater::plugin_file();
  update_site_option('auto_update_plugins', ['other/other.php']);
  update_option(Plugin_UI_Suite_Updater::AUTO_UPDATES_KEY, 1);
  Plugin_UI_Suite_Updater::migrate_auto_update_preference();
  $native = get_site_option('auto_update_plugins');
  if (!in_array($file, $native, true) || !in_array('other/other.php', $native, true)) throw new RuntimeException('Migration lost an existing native preference.');
  update_option(Plugin_UI_Suite_Updater::AUTO_UPDATES_KEY, 0);
  Plugin_UI_Suite_Updater::migrate_auto_update_preference();
  if (!in_array($file, get_site_option('auto_update_plugins'), true)) throw new RuntimeException('Legacy off state overrides a later native on state.');
  $item = (object)['plugin'=>$file];
  if (Plugin_UI_Suite_Updater::filter_auto_update(true, $item) !== true || Plugin_UI_Suite_Updater::filter_auto_update(false, $item) !== false) throw new RuntimeException('Native auto-update choice is overridden.');
  echo "PASS update preferences: native on/off choice preserved, legacy enabled choice migrated without touching other plugins.\n";
} finally {
  update_site_option('auto_update_plugins', $old_native);
  if ($old_legacy === null) delete_option(Plugin_UI_Suite_Updater::AUTO_UPDATES_KEY); else update_option(Plugin_UI_Suite_Updater::AUTO_UPDATES_KEY, $old_legacy);
}
