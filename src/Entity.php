<?php

/**
 * The base Entity class for database tables with the core ORM logic.
 *
 * @author Jahidul Pabel Islam <me@jahidulpabelislam.com>
 * @copyright 2012-2022 JPI
 */

namespace JPI\ORM;

use DateTime;
use Exception;
use JPI\Database\Collection as DBCollection;
use JPI\Database\Connection;
use JPI\Database\Query;
use JPI\ORM\Entity\Collection;

abstract class Entity {

    /**
     * @var int|null
     */
    protected $identifier = null;

    /**
     * @var array
     */
    protected $columns;

    /**
     * @var bool
     */
    protected $deleted = false;

    /**
     * @var string
     */
    protected static $table = "";

    /**
     * Mapping of database column to default value.
     *
     * @var array
     */
    protected static $defaultColumns = [];

    /**
     * @var string[]
     */
    protected static $intColumns = [];

    /**
     * @var string[]
     */
    protected static $dateTimeColumns = [];

    /**
     * @var string[]
     */
    protected static $dateColumns = [];

    /**
     * @var string[]
     */
    protected static $arrayColumns = [];

    /**
     * @var string
     */
    protected static $arrayColumnSeparator = ",";

    /**
     * @var string
     */
    protected static $orderByColumn = "id";

    /**
     * @var bool
     */
    protected static $orderByASC = true;

    /**
     * @return string
     */
    public static function getTable(): string {
        return static::$table;
    }

    /**
     * @return string[]
     */
    public static function getIntColumns(): array {
        return static::$intColumns;
    }

    /**
     * @return string[]
     */
    public static function getDateTimeColumns(): array {
        return static::$dateTimeColumns;
    }

    /**
     * @return string[]
     */
    public static function getDateColumns(): array {
        return static::$dateColumns;
    }

    /**
     * @return string[]
     */
    public static function getArrayColumns(): array {
        return static::$arrayColumns;
    }

    /**
     * @return \JPI\Database\Connection
     */
    abstract public static function getDatabaseConnection(): Connection;

    /**
     * @return \JPI\Database\Query
     */
    public static function newQuery(): Query {
        return new Query(static::getDatabaseConnection(), static::getTable());
    }

    /**
     * Select row(s) from the database.
     *
     * @param $columns string[]|string|null
     * @param $where string[]|string|int|null
     * @param $params array|null
     * @param $orderBy string[]|string|null
     * @param $limit int|null
     * @param $page int|string|null
     * @return \JPI\Database\Collection|array|null Collection if paginated/limited, array if not or if limit 1 and null if limit 1 but not found
     */
    public static function select(
        $columns = "*",
        $where = null,
        ?array $params = null,
        $orderBy = null,
        ?int $limit = null,
        $page = null
    ) {
        return static::newQuery()->select($columns, $where, $params, $orderBy, $limit, $page);
    }

    /**
     * Used to get a total count of rows using a where clause.
     *
     * @param $where string[]|string|null
     * @param $params array|null
     * @return int
     */
    public static function getCount($where = null, ?array $params = null): int {
        return static::newQuery()->count($where, $params);
    }

    /**
     * @param $id int|null
     * @return void
     */
    private function setId(?int $id): void {
        $this->identifier = $id;
    }

    /**
     * @return int|null
     */
    public function getId(): ?int {
        return $this->identifier;
    }

    /**
     * @param $column string
     * @param $value mixed
     * @param $fromDB bool
     * @return void
     */
    protected function setValue(string $column, $value, bool $fromDB = false): void {
        if (in_array($column, static::getIntColumns())) {
            if (is_numeric($value) && $value == (int)$value) {
                $value = (int)$value;
            }
            else if (!is_null($value)) {
                $value = null;
            }
        }
        else if (in_array($column, static::getArrayColumns())) {
            if ($fromDB && is_string($value)) {
                $value = explode(static::$arrayColumnSeparator, $value);
            }
            else if (!is_array($value) && !is_null($value)) {
                $value = null;
            }
        }
        else if (in_array($column, static::getDateColumns()) || in_array($column, static::getDateTimeColumns())) {
            if (!empty($value) && (is_string($value) || is_numeric($value))) {
                try {
                    $value = new DateTime($value);
                }
                catch (Exception $exception) {
                    $value = null;
                }
            }
            else if (!($value instanceof DateTime) && !is_null($value)) {
                $value = null;
            }
        }

        $this->columns[$column] = $value;
    }

    /**
     * @param $values array
     * @param $fromDB bool
     * @return void
     */
    public function setValues(array $values, bool $fromDB = false): void {
        $columns = array_keys($this->columns);
        foreach ($columns as $column) {
            if (array_key_exists($column, $values)) {
                $this->setValue($column, $values[$column], $fromDB);
            }
        }
    }

    /**
     * @param $column string
     * @param $value mixed
     * @return void
     */
    public function __set(string $column, $value): void {
        if (array_key_exists($column, $this->columns)) {
            $this->setValue($column, $value);
        }
    }

    /**
     * @param $column string
     * @return mixed
     */
    public function __get(string $column) {
        if ($column === "id") {
            return $this->getId();
        }

        return $this->columns[$column] ?? null;
    }

    /**
     * @param $column string
     * @return bool
     */
    public function __isset(string $column): bool {
        if ($column === "id") {
            return isset($this->identifier);
        }

        return isset($this->columns[$column]);
    }

    public function __construct() {
        $this->columns = static::$defaultColumns;
    }

    /**
     * @return bool
     */
    public function isLoaded(): bool {
        return !is_null($this->getId());
    }

    /**
     * Simple factory method to create a new entity instance and optionally set the values.
     *
     * @param $data array|null
     * @return static
     */
    public static function factory(?array $data = null): Entity {
        $entity = new static();

        if (!empty($data)) {
            $entity->setValues($data);
        }

        return $entity;
    }

    /**
     * @param $row array
     * @return static
     */
    private static function populateFromDB(array $row): Entity {
        $entity = new static();
        $entity->setValues($row, true);
        $entity->setId($row["id"]);
        return $entity;
    }

    /**
     * @param $rows \JPI\Database\Collection|array
     * @return static[]
     */
    private static function populateEntitiesFromDB($rows): array {
        $entities = [];

        foreach ($rows as $row) {
            $entities[] = static::populateFromDB($row);
        }

        return $entities;
    }

    /**
     * Get the LIMIT to use for the SELECT query.
     *
     * @param $limit int|string|null
     * @return int|null
     */
    protected static function getLimit($limit = null): ?int {
        if (is_numeric($limit)) {
            $limit = (int)$limit;
        } else {
            $limit = null;
        }

        return $limit;
    }

    /**
     * Get the ORDER BY to use for the SELECT query.
     *
     * @return string[]
     */
    protected static function getOrderBy(): array {
        $orderBys = [];
        if (static::$orderByColumn) {
            $orderBys[] = static::$orderByColumn . " " . (static::$orderByASC ? "ASC" : "DESC");
        }

        // Sort by id if not already to stop any randomness on rows with same value on above
        if (static::$orderByColumn !== "id") {
            $orderBys[] = "id ASC";
        }

        return $orderBys;
    }

    /**
     * Load row(s) from the database and load into entity instance(s).
     *
     * @param $where string[]|string|int|null
     * @param $params array|null
     * @param $limit int|string|null
     * @param $page int|string|null
     * @return \JPI\ORM\Entity\Collection|array|static|null
     */
    public static function get($where = null, ?array $params = null, $limit = null, $page = null) {
        $orderBy = static::getOrderBy();
        $limit = static::getLimit($limit);

        $rows = static::select("*", $where, $params, $orderBy, $limit, $page);

        if (is_null($rows)) {
            return null;
        }

        if (($where && is_numeric($where)) || $limit === 1) {
            return static::populateFromDB($rows);
        }

        $entities = static::populateEntitiesFromDB($rows);

        if (!$rows instanceof DBCollection) {
            return $entities;
        }

        return new Collection($entities, $rows->getTotalCount(), $rows->getLimit(), $rows->getPage());
    }

    /**
     * Load Entity(ies) from the Database where a column equals/in $value.
     *
     * @param $column string
     * @param $value string|int|array
     * @param $limit int|string|null
     * @param $page int|string|null
     * @return \JPI\ORM\Entity\Collection|static|null
     */
    public static function getByColumn(string $column, $value, $limit = null, $page = null) {
        if (is_array($value)) {
            $values = $value;
            $params = [];
            $ins = [];
            foreach ($values as $i => $value) {
                $key = "{$column}_" . ($i + 1);
                $ins[] = ":$key";
                $params[$key] = $value;
            }

            $where = "$column in (" . implode(", ", $ins) . ")";
        }
        else {
            $where = "$column = :$column";
            $params = [$column => $value];
        }

        return static::get($where, $params, $limit, $page);
    }

    /**
     * Load Entity(ies) from the Database where Id column equals/in $id.
     *
     * @param $id int[]|string[]|int|string
     * @return \JPI\ORM\Entity\Collection|static|null
     */
    public static function getById($id) {
        if (is_numeric($id) || is_array($id)) {
            return static::getByColumn("id", $id, !is_array($id) ? 1 : null);
        }

        return null;
    }

    /**
     * @return void
     */
    public function reload(): void {
        if ($this->isLoaded()) {
            $row = static::select("*", $this->getId());
            if ($row) {
                $this->setValues($row, true);
                return;
            }

            $this->setId(null);
        }
    }

    /**
     * Transform the entity values for database query.
     *
     * @return array
     */
    protected function getValuesToSave(): array {
        $values = [];

        $arrayColumns = static::getArrayColumns();
        $dateColumns = static::getDateColumns();
        $dateTimeColumns = static::getDateTimeColumns();

        foreach ($this->columns as $column => $value) {
            if (in_array($column, $arrayColumns)) {
                $value = implode(static::$arrayColumnSeparator, $value);
            }
            else if ($value instanceof DateTime) {
                if (in_array($column, $dateColumns)) {
                    $value = $value->format("Y-m-d");
                }
                else if (in_array($column, $dateTimeColumns)) {
                    $value = $value->format("Y-m-d H:i:s");
                }
            }
            $values[$column] = $value;
        }

        return $values;
    }

    /**
     * Save the current values to the database.
     * Either a new insert a new row or a update to an existing row.
     *
     * @return bool Whether the save was successful.
     */
    public function save(): bool {
        if ($this->isLoaded()) {
            $rowsAffected = static::newQuery()->update($this->getValuesToSave(), $this->getId());
            if ($rowsAffected === 0) {
                // Updating failed so reset id
                $this->setId(null);
            }
        }
        else {
            $newId = static::newQuery()->insert($this->getValuesToSave());
            $this->setId($newId);
        }

        return $this->isLoaded();
    }

    /**
     * Create a new entity with passed column values and save to the database.
     *
     * @param $data array
     * @return static
     */
    public static function insert(array $data): Entity {
        $entity = static::factory($data);
        $entity->save();

        return $entity;
    }

    /**
     * Whether this item has been deleted from the database.
     *
     * @return bool
     */
    public function isDeleted(): bool {
        return $this->deleted;
    }

    /**
     * Delete this entity/row from the database.
     *
     * @return bool Whether or not deletion was successful.
     */
    public function delete(): bool {
        if ($this->isLoaded()) {
            $rowsAffected = static::newQuery()->delete($this->getId());
            $this->deleted = $rowsAffected > 0;
        }

        return $this->deleted;
    }
}
