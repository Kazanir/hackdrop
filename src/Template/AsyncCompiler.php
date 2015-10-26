<?hh // partial

namespace Drupal\hackutils\Template;

use Drupal\Core\Template\TwigEnvironment;

trait AsyncCompiler {
  require extends TwigEnvironment;

  public function compileSource($source, $name = NULL) {
    error_log("Hello, world!");
    $compiled = parent::compileSource($source, $name);

    error_log($compiled);
    // Replace all calls to renderVar and escapeFilter with async versions.
    $async = preg_replace_callback(
      '/^([\s]*)echo \$this->env->getExtension\(\'drupal_core\'\)->(renderVar|escapeFilter)/gm',
      $matches ==> $matches[1] . 'echo await $this->env->getExtension(\'hackutils\')->async' . strtoupper($matches[2]),
      $compiled
    );
    error_log($async);
    // ..and change the class to a Hack file.
    $hackificated = preg_replace('/^<?php/', '<?hh // partial', $async);
    error_log($hackificated);

    return $hackificated;
  }

}

