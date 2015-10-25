<?hh // partial

namespace Drupal\hackutils\Template;

use Drupal\Core\Template\TwigEnvironment;

trait AsyncCompiler {
  require extends TwigEnvironment;

  public function compileSource($source, $name = NULL) {
    $compiled = parent::compileSource($source, $name);
    // Replace all calls to renderVar and escapeFilter with async versions.
    $async = preg_replace_callback(
      '/^([\s]*)echo \$this->env->getExtension\(\'drupal_core\'\)->(renderVar|escapeFilter)/gm',
      $matches ==> $matches[1] . 'echo await $this->env->getExtension(\'hackutils\')->async' . strtoupper($matches[2]),
      $compiled
    );
    // ..and change the class to a Hack file.
    $hackificated = preg_replace('/^<?php/', '<?hh // partial', $async);

    return $hackificated;
  }

}

