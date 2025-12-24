<?php

declare(strict_types=1);

namespace JPI\ORM;

use ArrayIterator;
use DateTime;
use Exception;
use LogicException;
use JPI\Database;
use JPI\Database\Query\ResultInterface as DatabaseResultInterface;
use JPI\ORM\Entity\Collection;
use JPI\ORM\Entity\QueryBuilder;
use JPI\ORM\Entity\InvalidValueException;
use OutOfBoundsException;
use Stringable;

/**
 * The base Entity class for database tables with the core ORM logic.
 */
abstract class Entity implements DatabaseResultInterface {

    private ?int $identifier = null;

    protected array $data;

    private bool $deleted = false;

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
     * Allowed values for type are: `string`, `float`, `int`, `date_time`, `date`, `array`, `belongs_to`, `has_many` & `has_one`
     */
    protected static array $dataMapping;

    protected static string $arrayColumnSeparator = ",";

    public static string $defaultOrderByColumn = "id";
    public static bool $defaultOrderByASC = true;

    private static array $registry = [];

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

    public static function getDataMapping(): array {
        foreach (static::$dataMapping as $key => $mapping) {
            if ($mapping["type"] === "belongs_to" && !isset($mapping["column"])) {
                static::$dataMapping[$key]["column"] = $key . "_id";
            }
        }

        return static::$dataMapping;
    }

    public static function getColumns(): array {
        $columns = [];

        $relationTypes = static::getRelationTypes();

        foreach (static::getDataMapping() as $key => $mapping) {
            if (!in_array($mapping["type"], $relationTypes)) {
                $columns[] = $key;
            }
            else if ($mapping["type"] === "belongs_to") {
                $columns[] = $mapping["column"];
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

    private function setIntValue(string $key, mixed $value): void {
        if (is_numeric($value) && $value == (int)$value) {
            $value = (int)$value;
        }

        if (!is_int($value) && $value !== null) {
            throw new InvalidValueException("`$key` must be a integer or null.");
        }

        $this->data[$key]["value"] = $value;
    }

    private function setFloatValue(string $key, mixed $value): void {
        if (is_numeric($value) && $value == (float)$value) {
            $value = (float)$value;
        }

        if (!is_float($value) && $value !== null) {
            throw new InvalidValueException("`$key` must be a float or null.");
        }

        $this->data[$key]["value"] = $value;
    }

    private function setArrayValue(string $key, mixed $value, bool $fromDB = false): void {
        if ($fromDB && is_string($value)) {
            $value = explode(static::$arrayColumnSeparator, $value);
        }

        if (!is_array($value) && $value !== null) {
            throw new InvalidValueException("`$key` must be an array or null.");
        }

        $this->data[$key]["value"] = $value;
    }

    private function setDateValue(string $key, mixed $value): void {
        if (!empty($value) && (is_string($value) || is_numeric($value))) {
            try {
                $value = new DateTime($value);
            }
            catch (Exception $exception) {
            }
        }

        if (!$value instanceof DateTime && $value !== null) {
            throw new InvalidValueException("`$key` must be instance of \DateTime or valid format for creation or null.");
        }

        $this->data[$key]["value"] = $value;
    }

    private function setBelongsToValue(string $key, mixed $value): void {
        $mapping = static::getDataMapping()[$key];

        if ($value instanceof $mapping["entity"]) {
            $this->data[$key]["value"] = $value;
            $this->data[$key]["database_value"] = $value->getId();
            return;
        }

        if (is_numeric($value) && $value == (int)$value) {
            $value = (int)$value;
        }

        if (!is_int($value) && $value !== null) {
            throw new InvalidValueException("`$key` must be a \\" . $mapping["entity"] . " instance, integer or null.");
        }

        unset($this->data[$key]["value"]);
        $this->data[$key]["database_value"] = $value;
    }

    private function setHasManyValue(string $key, mixed $value, bool $fromDB): void {
        if (is_array($value) || is_null($value)) {
            $value = new Collection(is_null($value) ? [] : $value);
        }

        if (!$value instanceof Collection) {
            throw new InvalidValueException("`$key` must be an array, EntityCollection or null.");
        }

        $mapping = static::getDataMapping()[$key];

        $oldLinkedEntities = !$fromDB ? $this->$key : [];
        foreach ($oldLinkedEntities as $oldLinkedEntity) {
            $oldLinkedEntity->{$mapping["column"]} = null;
        }

        foreach ($value as $linkedEntity) {
            $linkedEntity->{$mapping["column"]} = $this;
        }

        $this->data[$key]["value"] = $value;
    }

    private function setHasOneValue(string $key, mixed $value, bool $fromDB): void {
        $mapping = static::getDataMapping()[$key];

        if (is_numeric($value) && $value == (int)$value) {
            $oldEntity = $this->$key;
            if ($oldEntity) {
                $oldEntity->{$mapping["column"]} = null;
            }

            $newEntity = $mapping["entity"]::getById((int)$value);

            $newEntity->{$mapping["column"]} = $this;
            $this->data[$key]["value"] = $newEntity;
        }
        else if ($value instanceof $mapping["entity"] || $value === null) {
            $oldEntity = !$fromDB ? $this->$key : null;
            if ($oldEntity) {
                $oldEntity->{$mapping["column"]} = null;
            }

            if ($value instanceof self) {
                $value->{$mapping["column"]} = $this;
            }
            $this->data[$key]["value"] = null;
        }
        else {
            throw new InvalidValueException("`$key` must be a \\" . $mapping["entity"] . " instance , integer or null.");
        }
    }

    /**
     * @throws \JPI\ORM\Entity\InvalidValueException
     */
    protected function setValue(string $key, mixed $value, bool $fromDB = false): void {
        $mapping = static::getDataMapping()[$key];
        $type = $mapping["type"];

        if ($type === "int") {
            $this->setIntValue($key, $value);
        }
        else if ($type === "float") {
            $this->setFloatValue($key, $value);
        }
        else if ($type === "array") {
            $this->setArrayValue($key, $value, $fromDB);
        }
        else if (in_array($type, ["date_time", "date"])) {
            $this->setDateValue($key, $value);
        }
        else if ($type === "belongs_to") {
            $this->setBelongsToValue($key, $value);
        }
        else if ($type === "has_many") {
            $this->setHasManyValue($key, $value, $fromDB);
        }
        else if ($type === "has_one") {
            $this->setHasOneValue($key, $value, $fromDB);
        }
        else if ($type === "string") {
            if ($value instanceof Stringable) {
                $value = (string)$value;
            }

            if (!is_string($value) && !is_null($value)) {
                throw new InvalidValueException("`$key` must be a string or null.");
            }

            $this->data[$key]["value"] = $value;
        }
    }

    public function setValues(array $values, bool $fromDB = false): void {
        foreach (static::getDataMapping() as $key => $mapping) {
            $valueKey = $key;

            if ($fromDB) {
                if ($mapping["type"] === "belongs_to") {
                    $valueKey = $mapping["column"];
                }

                $valueKey = static::getFullColumnName($valueKey);
            }

            if (array_key_exists($valueKey, $values)) {
                $this->setValue($key, $values[$valueKey], $fromDB);
            }
        }
    }

    public function __set(string $key, mixed $value): void {
        if (!array_key_exists($key, $this->data)) {
            throw new OutOfBoundsException("`$key` isn't valid.");
        }

        $this->setValue($key, $value);
    }

    protected function lazyLoadRelationshipData(string $key, bool $refresh = false): void {
        $mapping = static::getDataMapping()[$key];

        if (
            $mapping["type"] === "has_many"
            && (!array_key_exists("value", $this->data[$key]) || $refresh)
        ) {
            if ($this->isLoaded()) {
                $otherEntity = $mapping["entity"];
                $otherEntityMap = $otherEntity::getDataMapping()[$mapping["column"]];

                $this->setValue(
                    $key,
                    $otherEntity::newQuery()
                        ->where($otherEntityMap["column"], "=", $this->getId())
                        ->select(),
                    true
                );
            }
            else {
                $this->setValue($key, [], true);
            }
        }

        if (
            $this->isLoaded()
            && $mapping["type"] === "has_one"
            && (!array_key_exists("value", $this->data[$key]) || $refresh)
        ) {
            $this->setValue(
                $key,
                $mapping["entity"]::newQuery()
                    ->where($mapping["column"], "=", $this->getId())
                    ->limit(1)
                    ->select(),
                true
            );
        }

        if (
            $mapping["type"] === "belongs_to"
            && $this->data[$key]["database_value"]
            && (!array_key_exists("value", $this->data[$key]) || $refresh)
        ) {
            $this->setValue(
                $key,
                $mapping["entity"]::getById($this->data[$key]["database_value"]),
                true
            );
        }
    }

    public function getValue(string $key): mixed {
        if ($key === "id") {
            return $this->getId();
        }

        if (!array_key_exists($key, $this->data)) {
            foreach (static::getDataMapping() as $mappingKey => $mapping) {
                if ($mapping["type"] !== "belongs_to" || $mapping["column"] !== $key) {
                    continue;
                }

                return $this->data[$mappingKey]["database_value"];
            }

            throw new OutOfBoundsException("`$key` isn't valid.");
        }

        $this->lazyLoadRelationshipData($key);

        return $this->data[$key]["value"] ?? null;
    }

    public function __get(string $column): mixed {
        return $this->getValue($column);
    }

    public function __isset(string $key): bool {
        if ($key === "id") {
            return isset($this->identifier);
        }

        return isset($this->data[$key]["value"]);
    }

    public function __construct() {
        $this->data = [];

        $validTypes = [
            "string",
            "int",
            "float",
            "array",
            "date",
            "date_time",
            "belongs_to",
            "has_many",
            "has_one",
        ];

        $relationTypes = static::getRelationTypes();

        foreach (static::getDataMapping() as $key => $mapping) {
            $type = $mapping["type"];
            if (!in_array($type, $validTypes)) {
                throw new LogicException("Invalid type `$type` for `$key`.");
            }

            $this->data[$key] = [];

            if (!in_array($type, $relationTypes)) {
                $this->data[$key]["value"] = $mapping["default_value"] ?? null;
            }
            else if ($type === "belongs_to") {
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

    public static function loadFromDatabaseRow(array $row): static {
        $id = (int)$row[static::getFullColumnName("id")];

        $registryKey = static::class . $id;

        if (!array_key_exists($registryKey, static::$registry)) {
            $entity = new static();
            $entity->setId($id);
            static::$registry[$registryKey] = $entity;
        }
        else {
            $entity = static::$registry[$registryKey];
        }

        $entity->setValues($row, true);

        return $entity;
    }

    public static function getById(int $id): ?static {
        $registryKey = static::class . $id;

        if (array_key_exists($registryKey, static::$registry)) {
            return static::$registry[$registryKey];
        }

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

        $this->setValues($row->toArray(), true);

        $relationTypes = static::getRelationTypes();

        foreach ($dataBefore as $key => $data) {
            $type = static::getDataMapping()[$key]["type"];

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

        $mapping = static::getDataMapping();
        $relationTypes = static::getRelationTypes();

        foreach ($this->data as $key => $data) {
            $type = $mapping[$key]["type"];

            if (in_array($type, $relationTypes) && $type !== "belongs_to") {
                continue;
            }

            if ($type === "belongs_to") {
                $key = $mapping[$key]["column"];
                $value = $data["database_value"];
            }
            else {
                $value = $data["value"];
            }

            if ($type === "array" && $value !== null) {
                $value = implode(static::$arrayColumnSeparator, $value);
            }
            else if ($value instanceof DateTime) {
                $value = $value->format($type === "date_time" ? "Y-m-d H:i:s" : "Y-m-d");
            }

            $values[static::getFullColumnName($key)] = $value;
        }

        return $values;
    }

    protected function cascadeSave(): void {
        foreach ($this->data as $key => $data) {
            $mapping = static::getDataMapping()[$key];
            $type = $mapping["type"];

            if ($type === "has_one" && array_key_exists("value", $data)) {
                $data["value"]->{$mapping["column"]} = $this;
                $data["value"]->save();
            }
            else if ($type === "has_many" && array_key_exists("value", $data)) {
                foreach ($data["value"] as $linkedEntity) {
                    $linkedEntity->{$mapping["column"]} = $this;
                    $linkedEntity->save();
                }
            }
        }
    }

    public function save(): bool {
        // Need to insert all belongs_to entities first to use ids in the insert/update query.
        $mapping = static::getDataMapping();
        foreach ($this->data as $key => $data) {
            if (
                $mapping[$key]["type"] !== "belongs_to"
                || !array_key_exists("value", $data)
                || $data["value"]->isLoaded()
            ) {
                continue;
            }

            $data["value"]->save();
            $this->data[$key]["database_value"] = $data["value"]->getId();
        }

        if ($this->isLoaded()) {
            if ($this->isDeleted()) {
                return false;
            }

            $rowsAffected = static::newQuery()
                ->where("id", "=", $this)
                ->update($this->getValuesToSave());
            $saved = $rowsAffected > 0;
        }
        else {
            $newId = static::newQuery()->insert($this->getValuesToSave());
            $this->setId($newId);

            $saved = $this->isLoaded();

            if ($newId) {
                static::$registry[static::class . $newId] = $this;
            }
        }

        if ($this->isLoaded()) {
            $this->cascadeSave();
        }

        return $saved;
    }

    public static function insert(array $data): static {
        $entity = static::factory($data);
        $entity->save();

        return $entity;
    }

    protected function cascadeDelete(): void {
        $mappings = static::getDataMapping();
        foreach ($mappings as $key => $mapping) {
            if (!($mapping["cascade_delete"] ?? false) || !$this->{$key}) {
                continue;
            }

            $type = $mapping["type"];

            if ($type === "has_one") {
                $this->{$key}->delete();
            }
            else if ($type === "has_many") {
                foreach ($this->{$key} as $linkedEntity) {
                    $linkedEntity->delete();
                }
            }
        }
    }

    public function delete(): bool {
        if (!$this->isLoaded() || $this->isDeleted()) {
            return false;
        }

        $rowsAffected = static::newQuery()
            ->where("id", "=", $this)
            ->delete();
        $this->deleted = $rowsAffected > 0;

        if ($this->isDeleted()) {
            $this->cascadeDelete();
        }

        return $this->deleted;
    }

    /** Only returns if value was loaded */
    public function toArray(int $depth = 1): array {
        $array = [
            "id" => $this->getId(),
        ];

        foreach ($this->data as $key => $data) {
            if (!array_key_exists("value", $data)) {
                continue;
            }

            $value = $data["value"];

            if ($value instanceof self || $value instanceof Collection) {
                if ($depth > 2) {
                    continue;
                }

                $value = $value->toArray($depth + 1);
            }

            $array[$key] = $value;
        }

        return $array;
    }

    /** Iterate over the data */
    public function getIterator(): ArrayIterator {
        return new ArrayIterator($this->toArray());
    }
}
