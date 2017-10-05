<?php

namespace Drutiny\Check\D7;

use Drutiny\Check\Check;
use Drutiny\Executor\DoesNotApplyException;

/**
 * @Drutiny\Annotation\CheckInfo(
 *  title = "Acquia Search Auto Switch",
 *  description = "Using Site Factory and Acquia Search, the auto core selector needs to be disabled in order to work.",
 *  remediation = "Set the variable <code>acquia_search_disable_auto_switch</code> to be <code>1</code>.",
 *  not_available = "Search API Acquia is not enabled.",
 *  success = "Auto switch is disabled.",
 *  failure = "Auto switch is enabled.",
 *  exception = "Could not determine Search settings.",
 * )
 */
class AcquiaSearchDisableAutoSwitch extends Check {

  /**
   * @inheritDoc
   */
  public function check() {
    if (!$this->context->drush->moduleEnabled('search_api_acquia')) {
      throw new DoesNotApplyException();
    }

    $acquia_search_disable_auto_switch = $this->context->drush->getVariable('acquia_search_disable_auto_switch', NULL);
    return $acquia_search_disable_auto_switch == 1;
  }

}
