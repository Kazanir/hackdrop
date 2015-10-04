<?hh

namespace Drupal\hh_async\Database;

use Drupal\Core\Database\Driver\mysql\Select as MysqlSelect;
use AsyncMysqlQueryResult;

class Select extends MysqlSelect {


  public async function asyncExecute(): ?Awaitable<AsyncMysqlQueryResult> {
    if (!$this->preExecute()) {
      return NULL;
    }

    $args = $this->getArguments();
    return await $this->connection->asyncQuery((string) $this, $args, $this->queryOptions);
  }
}

