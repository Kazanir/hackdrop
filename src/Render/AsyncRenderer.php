<?hh // partial

namespace Drupal\hackutils\Render;

use Drupal\Core\Render\Renderer;

class AsyncRenderer extends Renderer {
  use AsyncRenderingTree;
}

