<?hh

namespace Drupal\hackutils\Entity\Storage;

use Drupal\hackutils\Entity\Sql\MysqlAsyncContentEntityStorageLoader;
use Drupal\node\NodeStorage;
use Drupal\node\NodeStorageInterface;

class AsyncNodeStorage extends NodeStorage implements NodeStorageInterface {
  use MysqlAsyncContentEntityStorageLoader;
}

