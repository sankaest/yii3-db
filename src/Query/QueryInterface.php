<?php

declare(strict_types=1);

namespace Yiisoft\Db\Query;

use Yiisoft\Db\Expression\ExpressionInterface;

/**
 * The QueryInterface defines the minimum set of methods to be implemented by a database query.
 *
 * The default implementation of this interface is provided by {@see QueryTrait}.
 *
 * It has support for getting {@see one} instance or {@see all}.
 * Allows pagination via {@see limit} and {@see offset}.
 * Sorting is supported via {@see orderBy} and items can be limited to match some conditions using {@see where}.
 */
interface QueryInterface
{
    /**
     * Executes the query and returns all results as an array.
     *
     * @return array the query results. If the query results in nothing, an empty array will be returned.
     */
    public function all(): array;

    /**
     * Executes the query and returns a single row of result.
     *
     * @return array|bool the first row (in terms of an array) of the query result. False is returned if the query
     * results in nothing.
     */
    public function one();

    /**
     * Returns the number of records.
     *
     * @param string $q the COUNT expression. Defaults to '*'.
     *
     * @return bool|int|string number of records.
     */
    public function count(string $q = '*');

    /**
     * Returns a value indicating whether the query result contains any row of data.
     *
     * @return bool whether the query result contains any row of data.
     */
    public function exists(): bool;

    /**
     * Sets the {@see indexBy} property.
     *
     * @param callable|string $column the name of the column by which the query results should be indexed by.
     * This can also be a callable (e.g. anonymous function) that returns the index value based on the given row data.
     * The signature of the callable should be:
     *
     * ```php
     * function ($row)
     * {
     *     // return the index value corresponding to $row
     * }
     * ```
     *
     * @return QueryInterface the query object itself.
     */
    public function indexBy($column): self;

    /**
     * Sets the WHERE part of the query.
     *
     * The `$condition` specified as an array can be in one of the following two formats:
     *
     * - hash format: `['column1' => value1, 'column2' => value2, ...]`
     * - operator format: `[operator, operand1, operand2, ...]`
     *
     * A condition in hash format represents the following SQL expression in general:
     * `column1=value1 AND column2=value2 AND ...`. In case when a value is an array,
     * an `IN` expression will be generated. And if a value is `null`, `IS NULL` will be used in the generated
     * expression. Below are some examples:
     *
     * - `['type' => 1, 'status' => 2]` generates `(type = 1) AND (status = 2)`.
     * - `['id' => [1, 2, 3], 'status' => 2]` generates `(id IN (1, 2, 3)) AND (status = 2)`.
     * - `['status' => null]` generates `status IS NULL`.
     *
     * A condition in operator format generates the SQL expression according to the specified operator, which can be one
     * of the following:
     *
     * - **and**: the operands should be concatenated together using `AND`. For example,
     *   `['and', 'id=1', 'id=2']` will generate `id=1 AND id=2`. If an operand is an array,
     *   it will be converted into a string using the rules described here. For example,
     *   `['and', 'type=1', ['or', 'id=1', 'id=2']]` will generate `type=1 AND (id=1 OR id=2)`.
     *   The method will *not* do any quoting or escaping.
     *
     * - **or**: similar to the `and` operator except that the operands are concatenated using `OR`. For example,
     *   `['or', ['type' => [7, 8, 9]], ['id' => [1, 2, 3]]]` will generate `(type IN (7, 8, 9) OR (id IN (1, 2, 3)))`.
     *
     * - **not**: this will take only one operand and build the negation of it by prefixing the query string with `NOT`.
     *   For example `['not', ['attribute' => null]]` will result in the condition `NOT (attribute IS NULL)`.
     *
     * - **between**: operand 1 should be the column name, and operand 2 and 3 should be the
     *   starting and ending values of the range that the column is in.
     *   For example, `['between', 'id', 1, 10]` will generate `id BETWEEN 1 AND 10`.
     *
     * - **not between**: similar to `between` except the `BETWEEN` is replaced with `NOT BETWEEN`
     *   in the generated condition.
     *
     * - **in**: operand 1 should be a column or DB expression, and operand 2 be an array representing
     *   the range of the values that the column or DB expression should be in. For example,
     *   `['in', 'id', [1, 2, 3]]` will generate `id IN (1, 2, 3)`.
     *   The method will properly quote the column name and escape values in the range.
     *
     *   To create a composite `IN` condition you can use and array for the column name and value, where the values are
     *   indexed by the column name:
     *   `['in', ['id', 'name'], [['id' => 1, 'name' => 'foo'], ['id' => 2, 'name' => 'bar']] ]`.
     *
     *   You may also specify a sub-query that is used to get the values for the `IN`-condition:
     *   `['in', 'user_id', (new Query())
     *        ->select('id')
     *        ->from('users')
     *        ->where(['active' => 1])]`
     *
     * - **not in**: similar to the `in` operator except that `IN` is replaced with `NOT IN` in the generated condition.
     *
     * - **like**: operand 1 should be a column or DB expression, and operand 2 be a string or an array representing
     *   the values that the column or DB expression should be like.
     *   For example, `['like', 'name', 'tester']` will generate `name LIKE '%tester%'`.
     *   When the value range is given as an array, multiple `LIKE` predicates will be generated and concatenated
     *   using `AND`. For example, `['like', 'name', ['test', 'sample']]` will generate
     *   `name LIKE '%test%' AND name LIKE '%sample%'`.
     *   The method will properly quote the column name and escape special characters in the values.
     *   Sometimes, you may want to add the percentage characters to the matching value by yourself, you may supply
     *   a third operand `false` to do so. For example, `['like', 'name', '%tester', false]` will generate
     *   `name LIKE '%tester'`.
     *
     * - **or like**: similar to the `like` operator except that `OR` is used to concatenate the `LIKE` predicates when
     *   operand 2 is an array.
     *
     * - **not like**: similar to the `like` operator except that `LIKE` is replaced with `NOT LIKE` in the generated
     *   condition.
     *
     * - **or not like**: similar to the `not like` operator except that `OR` is used to concatenate the `NOT LIKE`
     *   predicates.
     *
     * - **exists**: operand 1 is a query object that used to build an `EXISTS` condition. For example
     *   `['exists', (new Query())
     *        ->select('id')
     *        ->from('users')
     *        ->where(['active' => 1])]` will result in the following
     *   SQL expression:
     *   `EXISTS (SELECT "id" FROM "users" WHERE "active"=1)`.
     *
     * - **not exists**: similar to the `exists` operator except that `EXISTS` is replaced with `NOT EXISTS` in the
     *   generated condition.
     *
     * - Additionally you can specify arbitrary operators as follows: A condition of `['>=', 'id', 10]` will result
     *   in the following SQL expression: `id >= 10`.
     *
     * **Note that this method will override any existing WHERE condition. You might want to use {@see andWhere()}
     * or {@see orWhere()} instead.**
     *
     * @param array|ExpressionInterface|string $condition the conditions that should be put in the WHERE part.
     * @param array $params the parameters (name => value) to be bound to the query.
     *
     * @return QueryInterface the query object itself.
     *
     * {@see andWhere()}
     * {@see orWhere()}
     */
    public function where($condition, array $params = []): self;

    /**
     * Adds an additional WHERE condition to the existing one.
     *
     * The new condition and the existing one will be joined using the 'AND' operator.
     *
     * @param array $condition the new WHERE condition. Please refer to {@see where()} on how to specify this parameter.
     *
     * @return QueryInterface the query object itself.
     *
     * {@see where()}
     * {@see orWhere()}
     */
    public function andWhere(array $condition): self;

    /**
     * Adds an additional WHERE condition to the existing one.
     *
     * The new condition and the existing one will be joined using the 'OR' operator.
     *
     * @param array $condition the new WHERE condition. Please refer to {@see where()} on how to specify this parameter.
     *
     * @return QueryInterface the query object itself.
     *
     * {@see where()}
     * {@see andWhere()}
     */
    public function orWhere(array $condition): self;

    /**
     * Sets the WHERE part of the query ignoring empty parameters.
     *
     * @param array $condition the conditions that should be put in the WHERE part. Please refer to {@see where()} on
     * how to specify this parameter.
     *
     * @return QueryInterface the query object itself.
     *
     * {@see andFilterWhere()}
     * {@see orFilterWhere()}
     */
    public function filterWhere(array $condition): self;

    /**
     * Adds an additional WHERE condition to the existing one ignoring empty parameters.
     * The new condition and the existing one will be joined using the 'AND' operator.
     *
     * @param array $condition the new WHERE condition. Please refer to {@see where()} on how to specify this parameter.
     *
     * @return QueryInterface the query object itself.
     *
     * {@see filterWhere()}
     * {@see orFilterWhere()}
     */
    public function andFilterWhere(array $condition): self;

    /**
     * Adds an additional WHERE condition to the existing one ignoring empty parameters.
     * The new condition and the existing one will be joined using the 'OR' operator.
     *
     * @param array $condition the new WHERE condition. Please refer to {@see where()} on how to specify this parameter.
     *
     * @return QueryInterface the query object itself.
     *
     * {@see filterWhere()}
     * {@see andFilterWhere()}
     */
    public function orFilterWhere(array $condition): self;

    /**
     * Sets the ORDER BY part of the query.
     *
     * @param array|string $columns the columns (and the directions) to be ordered by. Columns can be specified in
     * either a string (e.g. "id ASC, name DESC") or an array (e.g. `['id' => SORT_ASC, 'name' => SORT_DESC]`).
     * The method will automatically quote the column names unless a column contains some parenthesis (which means the
     * column contains a DB expression).
     *
     * @return QueryInterface the query object itself.
     *
     * {@see addOrderBy()}
     */
    public function orderBy($columns): self;

    /**
     * Adds additional ORDER BY columns to the query.
     *
     * @param array|string $columns the columns (and the directions) to be ordered by. Columns can be specified in
     * either a string (e.g. "id ASC, name DESC") or an array (e.g. `['id' => SORT_ASC, 'name' => SORT_DESC]`).
     * The method will automatically quote the column names unless a column contains some parenthesis (which means the
     * column contains a DB expression).
     *
     * @return QueryInterface the query object itself.
     *
     * {@see orderBy()}
     */
    public function addOrderBy($columns): self;

    /**
     * Sets the LIMIT part of the query.
     *
     * @param int|null $limit the limit. Use null or negative value to disable limit.
     *
     * @return QueryInterface the query object itself
     */
    public function limit(?int $limit): self;

    /**
     * Sets the OFFSET part of the query.
     *
     * @param ExpressionInterface|int|null $offset $offset the offset. Use null or negative value to disable offset.
     *
     * @return QueryInterface the query object itself
     */
    public function offset($offset): self;

    /**
     * Sets whether to emulate query execution, preventing any interaction with data storage.
     * After this mode is enabled, methods, returning query results like {@see one()}, {@see all()}, {@see exists()}
     * and so on, will return empty or false values.
     * You should use this method in case your program logic indicates query should not return any results, like in case
     * you set false where condition like `0=1`.
     *
     * @param bool $value whether to prevent query execution.
     *
     * @return QueryInterface the query object itself.
     */
    public function emulateExecution(bool $value = true): self;

    /**
     * Sets the SELECT part of the query.
     *
     * @param array|ExpressionInterface|string $columns the columns to be selected.
     * Columns can be specified in either a string (e.g. "id, name") or an array (e.g. ['id', 'name']).
     * Columns can be prefixed with table names (e.g. "user.id") and/or contain column aliases
     * (e.g. "user.id AS user_id").
     *
     * The method will automatically quote the column names unless a column contains some parenthesis (which means the
     * column contains a DB expression). A DB expression may also be passed in form of an {@see ExpressionInterface}
     * object.
     *
     * Note that if you are selecting an expression like `CONCAT(first_name, ' ', last_name)`, you should use an array
     * to specify the columns. Otherwise, the expression may be incorrectly split into several parts.
     *
     * When the columns are specified as an array, you may also use array keys as the column aliases (if a column does
     * not need alias, do not use a string key).
     * @param string|null $option additional option that should be appended to the 'SELECT' keyword. For example,
     * in MySQL, the option 'SQL_CALC_FOUND_ROWS' can be used.
     *
     * @return $this the query object itself.
     */
    public function select($columns, ?string $option = null): self;
}
