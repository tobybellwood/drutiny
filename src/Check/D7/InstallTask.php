<?php

namespace Drutiny\Check\D7;

use Drutiny\Check\Check;

/**
 * @Drutiny\Annotation\CheckInfo(
 *  title = "Install task",
 *  description = "If you fail to set this variable correctly, it can leave your <code>install.php</code> script open to the general public.",
 *  remediation = "Set the variable <code>install_task</code> to be <code>done</code>.",
 *  success = "Install.php is protected.",
 *  failure = "Install.php is not protected. The variable <code>install_task</code> is currently set to <code>:install_task</code>.",
 *  exception = "Could not determine install task settings.",
 * )
 */
class InstallTask extends Check {

  /**
   * @inheritDoc
   */
  public function check() {
    $install_task = $this->context->drush->getVariable('install_task', '');
    $this->setToken('install_task', $install_task);
    return $install_task === 'done';
  }

  /**
   * @inheritDoc
   */
  public function remediate() {
    $res = $this->context->drush->setVariable('install_task', 'done');
    return $res->isSuccessful();
  }

}
