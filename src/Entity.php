<?php

declare(strict_types=1);

namespace JPI\ORM;

use DateTime;
use Exception;
use JPI\Database;
use JPI\ORM\Entity\QueryBuilder;

/**
 * The base Entity class for database tables with the core ORM logic.
 */
abstract class Entity {

    protected ?int $identifier = null;

    protected array $columns;

    protected bool $deleted = false;

    protected static string $table;

    /**
     * Some database designers like to have their table columns with a prefix, this adds support for that.
     *
     * e.g `users` table will have column names like `user_id` & `user_email` instead of `id` & `email`
     *
     * Note: the first underscore is required.
     */
    protected static ?string $columnPrefix = null;

    /**
     * Mapping of database column to default value.
     */
    protected static array $defaultColumns = [];

    protected static array $intColumns = [];

    protected static array $dateTimeColumns = [];

    protected static array $dateColumns = [];

    protected static array $arrayColumns = [];

    protected static string $arrayColumnSeparator = ",";

    public static string $defaultOrderByColumn = "id";

    public static bool $defaultOrderByASC = true;

    public static function getTable(): string {
        return static::$table;
    }

    public static function getColumns(): array {
        return array_keys(static::$defaultColumns);
    }

    public static function getIntColumns(): array {
        return static::$intColumns;
    }

    public static function getDateTimeColumns(): array {
        return static::$dateTimeColumns;
    }

    public static function getDateColumns(): array {
        return static::$dateColumns;
    }

    public static function getArrayColumns(): array {
        return static::$arrayColumns;
    }

    public static function hasColumn(string $column): bool {
        return $column === "id" || in_array($column, static::getColumns());
    }

    public static function getFullColumnName(string $column): string {
        return (static::$columnPrefix ?: "") . $column;
    }

    abstract public static function getDatabase(): Database;

    public static function newQuery(): QueryBuilder {
        return new QueryBuilder(static::getDatabase(), new static());
    }

    private function setId(?int $id = null): void {
        $this->identifier = $id;
    }

    public function getId(): ?int {
        return $this->identifier;
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param bool $fromDB
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

    public function setValues(array $values, bool $fromDB = false): void {
        $columns = array_keys($this->columns);
        foreach ($columns as $column) {
            if ($fromDB) {
                $value = $values[static::getFullColumnName($column)];
            } else {
                $value = $values[$column];
            }

            $this->setValue($column, $value, $fromDB);
        }
    }

    /**
     * @param string $column
     * @param mixed $value
     * @return void
     */
    public function __set(string $column, $value): void {
        if (array_key_exists($column, $this->columns)) {
            $this->setValue($column, $value);
        }
    }

    /**
     * @param string $column
     * @return mixed
     */
    public function __get(string $column) {
        if ($column === "id") {
            return $this->getId();
        }

        return $this->columns[$column] ?? null;
    }

    public function __isset(string $column): bool {
        if ($column === "id") {
            return isset($this->identifier);
        }

        return isset($this->columns[$column]);
    }

    public function __construct() {
        $this->columns = static::$defaultColumns;
    }

    public function isLoaded(): bool {
        return !is_null($this->getId());
    }

    /**
     * Whether this item has been deleted from the database.
     */
    public function isDeleted(): bool {
        return $this->deleted;
    }

    /**
     * Simple factory method to create a new entity instance and optionally set the values.
     */
    public static function factory(?array $data = null): Entity {
        $entity = new static();

        if (!empty($data)) {
            $entity->setValues($data);
        }

        return $entity;
    }

    public static function populateFromDB(array $row): Entity {
        $entity = new static();
        $entity->setValues($row, true);
        $entity->setId((int)$row[static::getFullColumnName("id")]);
        return $entity;
    }

    /**
     * @param \JPI\Database\Collection|array $rows
     * @return static[]
     */
    public static function populateEntitiesFromDB($rows): array {
        $entities = [];

        foreach ($rows as $row) {
            $entities[] = static::populateFromDB($row);
        }

        return $entities;
    }

    public static function getById(int $id): ?Entity {
        return static::newQuery()->where("id", "=", $id)->limit(1)->select();
    }

    public function reload(): void {
        if (!$this->isLoaded() || $this->isDeleted()) {
            return;
        }

        $rawQuery = (new \JPI\Database\Query\Builder(static::getDatabase(), static::getTable()))
            ->where(static::getFullColumnName("id"), "=", $this->getId())
            ->limit(1)
        ;

        $row = $rawQuery->select();
        if ($row) {
            $this->setValues($row, true);
            return;
        }

        $this->setId(null);
    }

    /**
     * Transform the entity values for database query.
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
            $values[static::getFullColumnName($column)] = $value;
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
            if ($this->isDeleted()) {
                return false;
            }

            $rowsAffected = static::newQuery()->where("id", "=", $this->getId())->update($this->getValuesToSave());
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
     */
    public static function insert(array $data): Entity {
        $entity = static::factory($data);
        $entity->save();

        return $entity;
    }

    /**
     * Delete this entity/row from the database.
     *
     * @return bool Whether or not deletion was successful.
     */
    public function delete(): bool {
        if ($this->isLoaded() && !$this->isDeleted()) {
            $rowsAffected = static::newQuery()->where("id", "=", $this->getId())->delete();
            $this->deleted = $rowsAffected > 0;
        }

        return $this->deleted;
    }
}
