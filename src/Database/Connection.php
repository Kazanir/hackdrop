<?hh // partial

namespace Drupal\hh_async\Database;

use Drupal\Core\Database\Driver\mysql\Connection as MysqlConnection;
use Drupal\Core\Database;
use AsyncMysqlConnectionPool;
use AsyncMysqlQueryResult;
use AsyncMysqlConnection;
use PDO;

class Connection extends MysqlConnection {

  protected Awaitable<AsyncMysqlConnection> $asyncConnection;

  public function __construct(PDO $connection, array $opts) {
    parent::__construct($connection, $opts);

    $this->asyncConnectionPool = new AsyncMysqlConnectionPool([]);
  }

  protected async function getAsyncConnection(): Awaitable<?AsyncMysqlConnection> {
    $opts = $this->connectionOptions;
    return await $this->asyncConnectionPool->connect(
      $opts['host'],
      $opts['port'],
      $opts['database'],
      $opts['username'],
      $opts['password']
    );
  }

  /**
   * Wrapper for a properly constructed queryf. Gets a new connection from the
   * pool and returns the query result object for use.
   */
  public async function asyncMysqlQueryf(string $query, ...$args): Awaitable<AsyncMysqlQueryResult> {
    $conn = await $this->getAsyncConnection();

    return await $conn->queryf($query, ...$args);
  }

  /**
   * This function is an alternate for the normal query() function which is
   * invoked by Select->execute() and its ilk. This is the "intervention point"
   * of the async driver -- up until this point everything has gone through the
   * normal DBTNG process.
   */
  public async function asyncQuery(string $query, array<mixed> $args, array<mixed> $opts): Awaitable<AsyncMysqlQueryResult> {
    $opts += $this->defaultOptions();
    if ($opts['return'] != Database::RETURN_STATEMENT) {
      throw new \PDOException("Invalid return directive. Only Database::RETURN_STATEMENT is supported for asynchronous queries but $opts[return] was supplied.");
    }
    try {
      $this->expandArguments($query, $args);
      // We can skip the default driver's checks here; Hack's queryf blocks
      // semicolons for us.
      $query = rtrim($query, "; \t\n\r\0\x0B");

      // Transform our query into something queryf can understand.
      list($f_query, $f_args) = self::queryfTransform($query, $args);
      $result = await $this->asyncMysqlQueryf($f_query, ...$f_args);

      return $result;
    }
    catch {
      // @todo: Figure out how to catch async exceptions here...
    }
  }

  /**
   * Changes a parametrized query string and array of arguments into a queryf
   * string and array of arguments suitable for passing to queryf as a variadic.
   * Whether this sort of sorcery should actually be employed by mere mortals is
   * left as an exercise for the reader.
   */
  public static function queryfTransform(string $query, array<mixed> $args): (string, Vector<mixed>) {

  }

}

