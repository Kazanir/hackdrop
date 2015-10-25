<?hh // partial

namespace Drupal\hackutils\Template;

use Twig_Extension;
use Twig_Environment;

class TwigExtension extends Twig_Extension {
  public function getName(): string {
    return 'hackutils';
  }

  public async function asyncRenderVar(mixed $arg): Awaitable<string> {
    // Check for a numeric zero int or float.
    if ($arg === 0 || $arg === 0.0) {
      return 0;
    }

    // Return early for NULL and empty arrays.
    if ($arg == NULL) {
      return NULL;
    }

    // Optimize for scalars as it is likely they come from the escape filter.
    if (is_scalar($arg)) {
      return $arg;
    }

    if (is_object($arg)) {
      if ($arg instanceof RenderableInterface) {
        $arg = $arg->toRenderable();
      }
      elseif (method_exists($arg, '__toString')) {
        return (string) $arg;
      }
      // You can't throw exceptions in the magic PHP __toString methods, see
      // http://php.net/manual/en/language.oop5.magic.php#object.tostring so
      // we also support a toString method.
      elseif (method_exists($arg, 'toString')) {
        return $arg->toString();
      }
      else {
        throw new \Exception(t('Object of type "@class" cannot be printed.', array('@class' => get_class($arg))));
      }
    }

    // This is a render array, with special simple cases already handled.
    // Early return if this element was pre-rendered (no need to re-render).
    if (isset($arg['#printed']) && $arg['#printed'] == TRUE && isset($arg['#markup']) && strlen($arg['#markup']) > 0) {
      return $arg['#markup'];
    }
    $arg['#printed'] = FALSE;
    return await $this->renderer->render($arg);
  }

  public async function asyncEscapeFilter(
    Twig_Environment $env,
    mixed $arg,
    string $strategy = 'html',
    ?string $charset = NULL,
    bool $autoescape = FALSE
  ): Awaitable<mixed> {
    // Check for a numeric zero int or float.
    if ($arg === 0 || $arg === 0.0) {
      return 0;
    }

    // Return early for NULL and empty arrays.
    if ($arg == NULL) {
      return NULL;
    }

    // Keep Twig_Markup objects intact to support autoescaping.
    if ($autoescape && ($arg instanceof \Twig_Markup || $arg instanceof MarkupInterface)) {
      return $arg;
    }

    $return = NULL;

    if (is_scalar($arg)) {
      $return = (string) $arg;
    }
    elseif (is_object($arg)) {
      if ($arg instanceof RenderableInterface) {
        $arg = $arg->toRenderable();
      }
      elseif (method_exists($arg, '__toString')) {
        $return = (string) $arg;
      }
      // You can't throw exceptions in the magic PHP __toString methods, see
      // http://php.net/manual/en/language.oop5.magic.php#object.tostring so
      // we also support a toString method.
      elseif (method_exists($arg, 'toString')) {
        $return = $arg->toString();
      }
      else {
        throw new \Exception(t('Object of type "@class" cannot be printed.', array('@class' => get_class($arg))));
      }
    }

    // We have a string or an object converted to a string: Autoescape it!
    if (isset($return)) {
      if ($autoescape && SafeMarkup::isSafe($return, $strategy)) {
        return $return;
      }
      // Drupal only supports the HTML escaping strategy, so provide a
      // fallback for other strategies.
      if ($strategy == 'html') {
        return Html::escape($return);
      }
      return twig_escape_filter($env, $return, $strategy, $charset, $autoescape);
    }

    // This is a normal render array, which is safe by definition, with
    // special simple cases already handled.

    // Early return if this element was pre-rendered (no need to re-render).
    if (isset($arg['#printed']) && $arg['#printed'] == TRUE && isset($arg['#markup']) && strlen($arg['#markup']) > 0) {
      return $arg['#markup'];
    }
    $arg['#printed'] = FALSE;

    return await $this->renderer->render($arg);
  }

}

