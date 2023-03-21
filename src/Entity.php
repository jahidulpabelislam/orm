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

    protected array $data;

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
     * Set up for data this entity should have.
     * Key is the data/property name and value is an array with `type` and default_value` as keys.
     *
     * Allowed values for type are: `string`, `int`, `date_time`, `date` & `array`.
     */
    protected static array $dataMapping;

    protected static ?array $defaultData = null;
    protected static ?array $columns = null;

    protected static ?array $intColumns = null;

    protected static ?array $dateTimeColumns = null;

    protected static ?array $dateColumns = [];

    protected static ?array $arrayColumns = null;
    protected static string $arrayColumnSeparator = ",";

    public static string $defaultOrderByColumn = "id";
    public static bool $defaultOrderByASC = true;

    public static function getTable(): string {
        return static::$table;
    }

    public static function getDefaultData(): array {
        if (static::$defaultData === null) {
            static::$defaultData = [];

            foreach (static::$dataMapping as $key => $mapping) {
                static::$defaultData[$key] = $mapping["default_value"];
            }
        }
        return static::$defaultData;
    }

    public static function getColumns(): array {
        if (static::$columns === null) {
            static::$columns = [];

            foreach (static::$defaultData as $key => $datum) {
                // Currently all types are in db
                static::$columns[] = $key;
            }
        }
        return static::$columns;
    }

    public static function getIntColumns(): array {
        if (static::$intColumns === null) {
            static::$intColumns = [];

            foreach (static::$dataMapping as $key => $mapping) {
                if ($mapping["type"] === "int") {
                    static::$intColumns[] = $key;
                }
            }
        }

        return static::$intColumns;
    }

    public static function getDateTimeColumns(): array {
        if (static::$dateTimeColumns === null) {
            static::$dateTimeColumns = [];

            foreach (static::$dataMapping as $key => $mapping) {
                if ($mapping["type"] === "date_time") {
                    static::$dateTimeColumns[] = $key;
                }
            }
        }

        return static::$dateTimeColumns;
    }

    public static function getDateColumns(): array {
        if (static::$dateColumns === null) {
            static::$dateColumns = [];

            foreach (static::$dataMapping as $key => $mapping) {
                if ($mapping["type"] === "date") {
                    static::$dateColumns[] = $key;
                }
            }
        }

        return static::$dateColumns;
    }

    public static function getArrayColumns(): array {
        if (static::$arrayColumns === null) {
            static::$arrayColumns = [];

            foreach (static::$dataMapping as $key => $mapping) {
                if ($mapping["type"] === "array") {
                    static::$arrayColumns[] = $key;
                }
            }
        }

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

    protected function setValue(string $key, mixed $value, bool $fromDB = false): void {
        if (in_array($key, static::getIntColumns())) {
            if (is_numeric($value) && $value == (int)$value) {
                $value = (int)$value;
            }
            else if (!is_null($value)) {
                $value = null;
            }
        }
        else if (in_array($key, static::getArrayColumns())) {
            if ($fromDB && is_string($value)) {
                $value = explode(static::$arrayColumnSeparator, $value);
            }
            else if (!is_array($value) && !is_null($value)) {
                $value = null;
            }
        }
        else if (in_array($key, static::getDateColumns()) || in_array($key, static::getDateTimeColumns())) {
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

        $this->data[$key] = $value;
    }

    public function setValues(array $values, bool $fromDB = false): void {
        $data = array_keys($this->data);
        foreach ($data as $key) {
            if ($fromDB) {
                $valueKey = static::getFullColumnName($key);
            } else {
                $valueKey = $key;
            }

            if (array_key_exists($valueKey, $values)) {
                $this->setValue($key, $values[$valueKey], $fromDB);
            }
        }
    }

    public function __set(string $key, mixed $value): void {
        if (array_key_exists($key, $this->data)) {
            $this->setValue($key, $value);
        }
    }

    public function __get(string $key): mixed {
        if ($key === "id") {
            return $this->getId();
        }

        return $this->data[$key] ?? null;
    }

    public function __isset(string $key): bool {
        if ($key === "id") {
            return isset($this->identifier);
        }

        return isset($this->data[$key]);
    }

    public function __construct() {
        $this->data = static::getDefaultData();
    }

    public function isLoaded(): bool {
        return !is_null($this->getId());
    }

    public function isDeleted(): bool {
        return $this->deleted;
    }

    /**
     * Simple factory method to create a new entity instance and optionally set the values.
     */
    public static function factory(?array $data = null): static {
        $entity = new static();

        if (!empty($data)) {
            $entity->setValues($data);
        }

        return $entity;
    }

    public static function populateFromDB(array $row): static {
        $entity = new static();
        $entity->setValues($row, true);
        $entity->setId((int)$row[static::getFullColumnName("id")]);
        return $entity;
    }

    /**
     * @param \JPI\Database\Query\Result|array $rows
     * @return static[]
     */
    public static function populateEntitiesFromDB(Database\Query\Result|array $rows): array {
        $entities = [];

        foreach ($rows as $row) {
            $entities[] = static::populateFromDB($row);
        }

        return $entities;
    }

    public static function getById(int $id): ?static {
        return static::newQuery()
            ->where("id", "=", $id)
            ->limit(1)
            ->select();
    }

    public function reload(): void {
        if (!$this->isLoaded() || $this->isDeleted()) {
            return;
        }

        $row = (new \JPI\Database\Query\Builder(static::getDatabase(), static::getTable()))
            ->where(static::getFullColumnName("id"), "=", $this->getId())
            ->limit(1)
            ->select();

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

        foreach ($this->data as $key => $value) {
            if (in_array($key, $arrayColumns)) {
                $value = implode(static::$arrayColumnSeparator, $value);
            }
            else if ($value instanceof DateTime) {
                if (in_array($key, $dateColumns)) {
                    $value = $value->format("Y-m-d");
                }
                else if (in_array($key, $dateTimeColumns)) {
                    $value = $value->format("Y-m-d H:i:s");
                }
            }
            $values[static::getFullColumnName($key)] = $value;
        }

        return $values;
    }

    public function save(): bool {
        if ($this->isLoaded()) {
            if ($this->isDeleted()) {
                return false;
            }

            $rowsAffected = static::newQuery()
                ->where("id", "=", $this)
                ->update($this->getValuesToSave());
            return $rowsAffected > 0;
        }

        $newId = static::newQuery()->insert($this->getValuesToSave());
        $this->setId($newId);

        return $this->isLoaded();
    }

    public static function insert(array $data): static {
        $entity = static::factory($data);
        $entity->save();

        return $entity;
    }

    public function delete(): bool {
        if ($this->isLoaded() && !$this->isDeleted()) {
            $rowsAffected = static::newQuery()
                ->where("id", "=", $this)
                ->delete();
            $this->deleted = $rowsAffected > 0;
        }

        return $this->deleted;
    }
}
