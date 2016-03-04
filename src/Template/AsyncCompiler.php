<?hh // partial

namespace Drupal\hackutils\Template;

use Drupal\Core\Template\TwigEnvironment;

trait AsyncCompiler {
  require extends TwigEnvironment;

  public function compileSource($source, $name = NULL) {
    $compiled = parent::compileSource($source, $name);

    // Replace all calls to renderVar and escapeFilter with async versions.
    $async = preg_replace_callback(
      '/\$this->env->getExtension\(\'drupal_core\'\)->(renderVar|escapeFilter)/m',
      $matches ==> 'await $this->env->getExtension(\'hackutils\')->async' . ucwords($matches[1]),
      $compiled
    );
    // ..and change the class to a Hack file.
    $hackificated = preg_replace('/<\?php/', '<?hh // decl', $async);

    return $hackificated;
  }


  /**
   * Loads a template by name.
   *
   * @param string $name  The template name
   * @param int    $index The index if it is an embedded template
   *
   * @return Twig_TemplateInterface A template instance representing the given template name
   *
   * @throws Twig_Error_Loader When the template cannot be found
   * @throws Twig_Error_Syntax When an error occurred during compilation
   */
  public function loadTemplate($name, $index = null) {
    $cls = $this->getTemplateClass($name, $index);

    if (isset($this->loadedTemplates[$cls])) {
      return $this->loadedTemplates[$cls];
    }

    if (!class_exists($cls, false)) {
      if ($this->bcGetCacheFilename) {
        $key = $this->getCacheFilename($name);
      }
      else {
        $key = $this->cache->generateKey($name, $cls);
      }

      if (!$this->isAutoReload() || $this->isTemplateFresh($name, $this->cache->getTimestamp($key))) {
        $this->cache->load($key);
      }

      if (!class_exists($cls, false)) {
        $content = $this->compileSource($this->getLoader()->getSource($name), $name);
        if ($this->bcWriteCacheFile) {
          $this->writeCacheFile($key, $content);
        }
        else {
          $this->cache->write($key, $content);
        }
        if (substr($content, 0, 4) == "<?hh") {
          eval(substr($content, 12));
        }
        else {
          eval('?>'.$content);
        }
      }
    }

    if (!$this->runtimeInitialized) {
      $this->initRuntime();
    }

    return $this->loadedTemplates[$cls] = new $cls($this);
  }

}

