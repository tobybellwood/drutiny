<?php

namespace Drutiny\Driver;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 *
 */
trait DrushTrait {

  protected $drushOptions = [];

  protected $globalDefaults = [];

  /**
   * Converts into method into a Drush command.
   */
  public function __call($method, $args) {
    $this->setDrushOptions($this->getGlobalDefaults());

    // Convert method from camelCase to Drush hyphen based method naming.
    // E.g. PmInfo will become pm-info.
    preg_match_all('/((?:^|[A-Z])[a-z]+)/', $method, $matches);
    $method = implode('-', array_map('strtolower', $matches[0]));
    try {
      $output = $this->runCommand($method, $args);
    }
    catch (ProcessFailedException $e) {
      $this->sandbox()->logger()->info($e->getProcess()->getOutput());
      throw new DrushFormatException("Drush command failed.", $e->getProcess()->getOutput());
    }

    if (in_array("--format='json'", $this->drushOptions)) {
      if (!$json = json_decode($output, TRUE)) {
        throw new DrushFormatException("Cannot parse json output from drush: $output", $output);
      }
      $output = $json;
    }

    // Reset drush options.
    $this->drushOptions = [];

    return $output;
  }

  /**
   * Override config-set to allow better value setting.
   */
  public function configSet($collection, $key, $value) {
    $value = base64_encode(Yaml::dump($value));

    if ($index = array_search('--format=json', $this->drushOptions)) {
      unset($this->drushOptions[$index]);
    }
    $this->drushOptions[] = '--format=yaml';
    $this->drushOptions[] = '-y';

    $pipe = "echo '$value' | base64 --decode |";

    $output = $this->runCommand('config-set', [
      $collection, $key, '-'
    ], $pipe);

    $this->drushOptions = [];
    return TRUE;
  }

  /**
   * Override for drush command 'sqlq'.
   */
  public function sqlq($sql) {
    $args = ['--db-prefix', '"' . $sql . '"'];
    return trim($this->__call('sqlq', $args));
  }

  /**
   * Override for drush command 'sql-query'.
   */
  public function sqlQuery($sql) {
    return $this->sqlq($sql);
  }

  /**
   *
   */
  public function runCommand($method, $args, $pipe = '') {
    return $this->sandbox()->exec('@pipe drush @options @method @args', [
      '@method' => $method,
      '@args' => implode(' ', $args),
      '@options' => implode(' ', $this->drushOptions),
    ]);
  }

  /**
   *
   */
  public function setDrushOptions(array $options) {
    foreach ($options as $key => $value) {
      if (is_int($key)) {
        $option  = '--' . $value;
      }
      elseif (strlen($key) == 1) {
        $option = '-' . $key;
        if (!empty($value)) {
          $option .= ' ' . escapeshellarg($value);
        }
      }
      else {
        $option = '--' . $key;
        if (!empty($value)) {
          $option .= '=' . escapeshellarg($value);
        }
      }
      if (!in_array($option, $this->drushOptions)) {
        $this->drushOptions[] = $option;
      }
    }
    return $this;
  }

  /**
   * Set an option that will be presented on every drush command.
   */
  public function setGlobalDefaultOption($key, $value)
  {
    $this->globalDefaults[$key] = $value;
    return $this;
  }

  /**
   * Remove global option.
   */
  public function removeGlobalDefaultOption($key)
  {
    unset($this->globalDefaults[$key]);
    return $this;
  }

  /**
   * Retrieve global defaults.
   */
  public function getGlobalDefaults()
  {
    return $this->globalDefaults;
  }

  /**
   * This function takes PHP in this execution scope (Closure) and executes it
   * against the Drupal target using Drush php-script.
   */
  public function evaluate(\Closure $callback, Array $args = []) {
    $args = array_values($args);
    $func = new \ReflectionFunction($callback);
    $filename = $func->getFileName();
    $start_line = $func->getStartLine() - 1; // it's actually - 1, otherwise you wont get the function() block
    $end_line = $func->getEndLine();
    $length = $end_line - $start_line;

    $source = file($filename);
    $body = array_slice($source, $start_line, $length);

    $col = strpos($body[0], 'function');
    $body[0] = substr($body[0], $col);

    $last = count($body) - 1;
    $col = strpos($body[$last], '}') + 1;
    $body[$last] = substr($body[$last], 0, $col);

    $code = [];
    $calling_args = [];
    foreach ($func->getParameters() as $i => $param) {
      $code[] = '$' . $param->name . ' = ' . var_export($args[$i], TRUE) . ';';
      $calling_args[] = '$' . $param->name;
    }

    $code[] = '$evaluation = ' . implode("", $body) . ';';
    $code[] = '$response = $evaluation(' . implode(', ', $calling_args) . ');';
    $code[] = 'echo json_encode($response);';

    $transfer = base64_encode(implode(PHP_EOL, $code));
    $php_code = "echo $transfer | base64 --decode";
    $php_code = '"`' . $php_code . '`"';
    return $this->sandbox()->drush(['format' => 'json'])->ev($php_code);
  }

}
