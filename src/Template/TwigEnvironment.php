<?hh // partial

namespace Drupal\hackutils\Template;

use Drupal\Core\Template\TwigEnvironment as DrupalTwig;

class TwigEnvironment extends DrupalTwig {

  use AsyncCompiler;

}
