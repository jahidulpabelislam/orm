<?php

declare(strict_types=1);

namespace JPI\ORM;

use DateTime;
use Exception;
use JPI\Database;
use JPI\ORM\Entity\Collection;
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
     * Allowed values for type are: `string`, `int`, `date_time`, `date`, `array`, `belongs_to`, `has_many` & `has_one`
     */
    protected static array $dataMapping;

    protected static string $arrayColumnSeparator = ",";

    public static string $defaultOrderByColumn = "id";
    public static bool $defaultOrderByASC = true;

    public static function getTable(): string {
        return static::$table;
    }

    public static function getRelationTypes(): array {
        return [
            "belongs_to",
            "has_many",
            "has_one",
        ];
    }

    public static function getColumns(): array {
        $columns = [];

        $relationTypes = static::getRelationTypes();

        foreach (static::$dataMapping as $key => $mapping) {
            if (!in_array($key, $relationTypes)) {
                $columns[] = $key;
            }
            else if ($mapping["type"] === "belongs_to") {
                $columns[] = $mapping["column"] ?? $key;
            }
        }

        return $columns;
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
        $mapping = static::$dataMapping[$key];
        $type = $mapping["type"];

        if ($type === "int") {
            if (is_numeric($value) && $value == (int)$value) {
                $value = (int)$value;
            }

            if (is_int($value) || $value === null) {
                $this->data[$key]["value"] = $value;
            }
        }
        else if ($type === "array") {
            if ($fromDB && is_string($value)) {
                $this->data[$key]["value"] = explode(static::$arrayColumnSeparator, $value);
            }
            else if (is_array($value) || $value === null) {
                $this->data[$key]["value"] = $value;
            }
        }
        else if (in_array($type, ["date_time", "date"])) {
            if (!empty($value) && (is_string($value) || is_numeric($value))) {
                try {
                    $this->data[$key]["value"] = new DateTime($value);
                }
                catch (Exception $exception) {
                }
            }
            else if ($value instanceof DateTime || $value === null) {
                $this->data[$key]["value"] = $value;
            }
        }
        else if ($type === "belongs_to") {
            if ($value instanceof Entity) {
                $this->data[$key]["value"] = $value;
                $this->data[$key]["database_value"] = $value->getId();
            }
            else {
                if (is_numeric($value) && $value == (int)$value) {
                    $value = (int)$value;
                }

                if (is_int($value) || $value === null) {
                    if ($value === null) {
                        $this->data[$key]["value"] = null;
                    }

                    $this->data[$key]["database_value"] = $value;
                }
            }
        }
        else if ($type === "has_many") {
            if (is_array($value) || is_null($value)) {
                $value = new Collection(is_null($value) ? [] : $value);
            }

            if ($value instanceof Collection) {
                foreach ($value as $linkedEntity) {
                     $linkedEntity->{$mapping["column"]} = $this;
                }

                $this->data[$key]["value"] = $value;
            }
        } else if ($type === "has_one") {
            if (is_numeric($value) && $value == (int)$value) {
                $id = (int)$value;

                $entity = $mapping["entity"]::newQuery()
                    ->where("id", "=", $id)
                    ->limit(1)
                    ->select()
                ;

                $entity->{$mapping["column"]} = $this;
                $this->data[$key]["value"] = $entity;
            }
            else if ($value === null) {
                $entity = $this->$key;

                if ($entity) {
                    $entity->{$mapping["column"]} = null;
                }
                $this->data[$key]["value"] = null;
            }
            else if ($value instanceof Entity) {
                $value->{$mapping["column"]} = $this;
                $this->data[$key]["value"] = $value;
            }
        }
        else if ($type === "string") {
            $this->data[$key]["value"] = $value;
        }
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

    protected function lazyLoadRelationshipData(string $key, bool $refresh = false): void {
        $mapping = static::$dataMapping[$key];

        if (
            $this->isLoaded()
            && $mapping["type"] === "has_many"
            && (!array_key_exists("value", $this->data[$key]) || $refresh)
        ) {
            $this->{$key} = $mapping["entity"]::newQuery()
                ->where($mapping["column"], "=", $this->getId())
                ->select()
            ;
        }

        if (
            $this->isLoaded()
            && $mapping["type"] === "has_one"
            && (!array_key_exists("value", $this->data[$key]) || $refresh)
        ) {
            $this->{$key} = $mapping["entity"]::newQuery()
                ->where($mapping["column"], "=", $this->getId())
                ->limit(1)
                ->select()
            ;
        }

        if (
            $mapping["type"] === "belongs_to"
            && $this->data[$key]["database_value"]
            && (!array_key_exists("value", $this->data[$key]) || $refresh)
        ) {
            $this->{$key} = $mapping["entity"]::newQuery()
                ->where("id", "=", $this->data[$key]["database_value"])
                ->limit(1)
                ->select()
            ;
        }
    }

    public function __get(string $key): mixed {
        if ($key === "id") {
            return $this->getId();
        }

        if (!array_key_exists($key, $this->data)) {
            return null;
        }

        $this->lazyLoadRelationshipData($key);

        return $this->data[$key]["value"] ?? null;
    }

    public function __isset(string $key): bool {
        if ($key === "id") {
            return isset($this->identifier);
        }

        return isset($this->data[$key]["value"]);
    }

    public function __construct() {
        $this->data = [];

        $relationTypes = static::getRelationTypes();

        foreach (static::$dataMapping as $key => $mapping) {
            $this->data[$key] = [];

            if (!in_array($mapping["type"], $relationTypes)) {
                $this->data[$key]["value"] = $mapping["default_value"] ?? null;
            }
            else if ($mapping["type"] === "belongs_to") {
                $this->data[$key]["database_value"] = $mapping["default_value"] ?? null;
            }
        }
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

        if (!$row) {
            $this->setId(null);
            return;
        }

        $dataBefore = $this->data;

        $this->setValues($row, true);

        $relationTypes = static::getRelationTypes();

        foreach ($dataBefore as $key => $data) {
            $type = static::$dataMapping[$key]["type"];

            if (
                !in_array($type, $relationTypes)
                || !array_key_exists("value", $data)
            ) {
                continue;
            }

            $this->lazyLoadRelationshipData($key, true);
        }
    }

    /**
     * Transform the entity values for database query.
     */
    protected function getValuesToSave(): array {
        $values = [];

        $relationTypes = static::getRelationTypes();

        foreach ($this->data as $key => $data) {
            $type = static::$dataMapping[$key]["type"];

            if (in_array($type, $relationTypes) && $type !== "belongs_to") {
                continue;
            }

            $value = $type === "belongs_to" ? $data["database_value"] : $data["value"];

            if ($type === "array") {
                $value = implode(static::$arrayColumnSeparator, $value);
            }
            else if ($value instanceof DateTime) {
                $value = $value->format($type === "date_time" ? "Y-m-d H:i:s" : "Y-m-d");
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
