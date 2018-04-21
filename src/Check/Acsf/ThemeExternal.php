<?php

namespace Drutiny\Check\Acsf;

use Drutiny\Check\Check;
use Drutiny\AuditResponse\AuditResponse;
use Drutiny\Executor\DoesNotApplyException;

/**
 * @Drutiny\Annotation\CheckInfo(
 *  title = "ACSF theme external resources",
 *  description = "Some basic checks to ensure that the theme is not doing any seriously bad things. Note this is not supposed to be perfect, but used as an aid in code review.",
 *  remediation = "Carefully consider whether to trust the source of the external script.",
 *  success = "No external scripts found in the theme.",
 *  failure = "External Script:plural :prefix found - <ul><li><code>:issues</code></li></ul>",
 *  exception = "Could not determine external scripts in the theme.",
 *  not_available = "No custom theme is linked.",
 * )
 */
class ThemeExternal extends Check {

  /**
   *
   */
  public function check() {
    $root = $this->context->drush->getCoreStatus('root');
    $site = $this->context->drush->getCoreStatus('site');
    $themes = $this->context->drush->getCoreStatus('themes');
    $theme = $this->context->drush->getCoreStatus('theme');
    
    $look_out_for = [
        "preconnect",
        "javascript\[",
        "crossorigin",
        "external",
        "script defer src",
//        "javascript['misc",
//        "<link rel=\"preconnect\"",
    ];

    // This command is probably more complex then it should be due to wanting to
    // remove the main theme folder prefix.
    //
    // Yields something like:
    //
    // ./zen/template.php:159:    $path = drupal_get_path_alias($_GET['q']);
    // ./zen/template.php:162:    $arg = explode('/', $_GET['q']);.
    $command = "if [ -d '{$root}/{$themes}/{$theme}/' ]; then cd {$root}/{$themes}/{$theme}/ ; grep -nrI --include=*.php --include=*.inc '" . implode('\|', $look_out_for) . "' . || echo 'nosecissues' ; else echo 'nope'; fi";
    $output = (string) $this->context->remoteExecutor->execute($command);

    // The ACSF site can have no custom theme repo linked, in which case we
    // will see a "du: cannot access ... No such file or directory" error
    // response. For now, we force this command to not fail using an or
    // statement.
    if (preg_match('/^nope$/', $output)) {
      throw new DoesNotApplyException();
    }

    if (preg_match('/^nosecissues/', $output)) {
      return AuditResponse::AUDIT_SUCCESS;
    }

    // Output from find is a giant string with newlines to seperate the files.
    $rows = explode("\n", $output);
    $rows = array_map('trim', $rows);
    $rows = array_map('strip_tags', $rows);
    $rows = array_filter($rows);

    // Ignore certain paths that are known to be safe.
    foreach ($rows as $index => $row) {
      if (strpos($row, './bootstrap/includes/cdn.inc:') === 0) {
        unset($rows[$index]);
      }
      if (strpos($row, './bootstrap/includes/bootstrap.drush.inc:') === 0) {
        unset($rows[$index]);
      }
    }

    $this->setToken('issues', implode('</code></li><li><code>', $rows));
    $this->setToken('plural', count($rows) > 1 ? 's' : '');
    $this->setToken('prefix', count($rows) > 1 ? 'were' : 'was');

    return count($rows) === 0;
  }

}
