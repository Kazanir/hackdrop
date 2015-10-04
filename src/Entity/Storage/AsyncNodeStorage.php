<?hh

namespace Drupal\hh_async\Entity\Storage;

use Drupal\hh_async\Entity\Sql\MysqlAsyncContentEntityStorageLoader;
use Drupal\node\NodeStorage;
use Drupal\node\NodeStorageInterface;

class AsyncNodeStorage extends NodeStorage implements NodeStorageInterface {
  use MysqlAsyncContentEntityStorageLoader;
}

