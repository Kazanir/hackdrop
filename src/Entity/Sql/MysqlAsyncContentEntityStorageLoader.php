<?hh // partial

namespace Drupal\hackutils\Entity\Sql;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use AsyncMysqlQueryResult;
use function HH\Asio\mmk;

trait MysqlAsyncContentEntityStorageLoader {

  protected function loadFromDedicatedTables(array &$values, bool $load_from_revision): void {
    $this->asyncLoadFromDedicatedTables($values, $load_from_revision)->join();
  }

  protected async function asyncloadFromDedicatedTables(array &$values, bool $load_from_revision): Awaitable<void> {
    if (empty($values)) {
      return;
    }

    // Collect entities ids, bundles and languages.
    $bundles = array();
    $ids = array();
    $default_langcodes = array();
    foreach ($values as $key => $entity_values) {
      $bundles[$this->bundleKey ? $entity_values[$this->bundleKey][LanguageInterface::LANGCODE_DEFAULT] : $this->entityTypeId] = TRUE;
      $ids[] = !$load_from_revision ? $key : $entity_values[$this->revisionKey][LanguageInterface::LANGCODE_DEFAULT];
      if ($this->langcodeKey && isset($entity_values[$this->langcodeKey][LanguageInterface::LANGCODE_DEFAULT])) {
        $default_langcodes[$key] = $entity_values[$this->langcodeKey][LanguageInterface::LANGCODE_DEFAULT];
      }
    }

    // Collect impacted fields.
    $storage_definitions = array();
    $definitions = array();
    $table_mapping = $this->getTableMapping();
    foreach ($bundles as $bundle => $v) {
      $definitions[$bundle] = $this->entityManager->getFieldDefinitions($this->entityTypeId, $bundle);
      foreach ($definitions[$bundle] as $field_name => $field_definition) {
        $storage_definition = $field_definition->getFieldStorageDefinition();
        if ($table_mapping->requiresDedicatedTableStorage($storage_definition)) {
          $storage_definitions[$field_name] = $storage_definition;
        }
      }
    }

    // Load field data.
    $langcodes = array_keys($this->languageManager->getLanguages(LanguageInterface::STATE_ALL));

    $completion_map = await mmk($storage_definitions, async (string $field_name, FieldStorageDefinitionInterface $storage) ==> {
      \drupal_set_message("Logging pre-query time for $field_name at " . microtime());
      $query_result = await $this->asyncLoadFromDedicatedTable($storage, $ids, $langcodes, $load_from_revision);
      \drupal_set_message("Logging post-query time for $field_name at " . microtime());

      $results = $query_result->mapRowsTyped();
      foreach ($results as $row) {
        $etid = $row['entity_id'];
        // Field values in default language are stored with
        // LanguageInterface::LANGCODE_DEFAULT as key.
        $langcode = LanguageInterface::LANGCODE_DEFAULT;
        if ($this->langcodeKey && isset($default_langcodes[$etid]) && $row['langcode'] != $default_langcodes[$etid]) {
          $langcode = $row['langcode'];
        }

        if (!isset($values[$etid][$field_name][$langcode])) {
          $values[$etid][$field_name][$langcode] = array();
        }

        // Ensure that records for non-translatable fields having invalid
        // languages are skipped.
        if ($langcode == LanguageInterface::LANGCODE_DEFAULT || $definitions[$bundle][$field_name]->isTranslatable()) {
          if (
              $storage->getCardinality() == FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED
              ||
              count($values[$etid][$field_name][$langcode]) < $storage->getCardinality()
             ) {
            $item = array();
            // For each column declared by the field, populate the item from the
            // prefixed database column.
            foreach ($storage->getColumns() as $column => $attributes) {
              $column_name = $table_mapping->getFieldColumnName($storage, $column);
              // Unserialize the value if specified in the column schema.
              $item[$column] = (!empty($attributes['serialize'])) ? unserialize($row[$column_name]) : $row[$column_name];
            }

            // Add the item to the field values for the entity.
            $values[$etid][$field_name][$langcode][] = $item;
          }
        }
      }
      \drupal_set_message("Logging post-mapping time for $field_name at " . microtime());
    });
  }

  protected async function asyncLoadFromDedicatedTable(
    FieldStorageDefinitionInterface $definition,
    array<int> $ids,
    array<int> $langcodes,
    bool $load_from_revision
  ): Awaitable<AsyncMysqlQueryResult> {
    $table_mapping = $this->getTableMapping();
    $table = !$load_from_revision ? $table_mapping->getDedicatedDataTableName($definition) : $table_mapping->getDedicatedRevisionTableName($definition);

    $entity_key = !$load_from_revision ? 'entity_id' : 'revision_id';
    $id_placeholders = implode(",", array_fill(0, count($ids), '%d'));
    $langcode_placeholders = implode(",", array_fill(0, count($langcodes), '%s'));
    // @todo: We don't have the luxury of {} table prefixing here, so prepend it
    // manually.
    $prefixed_table = $table;
    $query = "SELECT * FROM %T WHERE %C = %d AND %C IN ($id_placeholders) AND %C IN($langcode_placeholders) ORDER BY %C";
    $args = array_merge(array($prefixed_table, 'deleted', 0, $entity_key), $ids, array('langcode'), $langcodes, array('delta'));

    return await $this->database->asyncMysqlQueryf($query, ...$args); // UNSAFE
  }

}
