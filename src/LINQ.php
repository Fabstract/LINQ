<?php

namespace Fabstract\Component\LINQ;

use Fabstract\Component\LINQ\Exception\ArgumentOutOfRangeException;
use Fabstract\Component\LINQ\Exception\InvalidOperationException;
use InvalidArgumentException;

class LINQ
{
    private $data = [];

    private function __construct($data)
    {
        if (!is_array($data)) {
            throw new InvalidArgumentException('data must be an array');
        }

        $this->data = $data;
    }

    /**
     * @param array $data
     * @return LINQ
     */
    public static function from($data)
    {
        return new LINQ($data);
    }

    #region Queries

    /**
     * @param callable $callable
     * @return LINQ
     */
    public function where($callable)
    {
        if (is_callable($callable)) {
            foreach ($this->data as $key => $value) {
                $response = call_user_func($callable, $value);
                if ($response !== true) {
                    unset($this->data[$key]);
                }
            }
        }

        return $this;
    }

    /**
     * @param callable $callable
     * @return LINQ
     */
    public function select($callable)
    {
        $new_data = [];

        foreach ($this->data as $key => $value) {
            $response = call_user_func($callable, $value);
            $new_data[$key] = $response;
        }

        $this->data = $new_data;

        return $this;
    }

    /**
     * @param callable $key_selector
     * @param callable $value_selector
     * @return LINQ
     */
    public function map($key_selector, $value_selector)
    {
        $new_data = [];

        foreach ($this->data as $value) {
            $selected_key = call_user_func($key_selector, $value);
            $selected_value = call_user_func($value_selector, $value);
            $new_data[$selected_key] = $selected_value;
        }

        $this->data = $new_data;

        return $this;
    }

    /**
     * @param callable $collection_selector
     * @param callable|null $value_selector
     * @return LINQ
     */
    public function selectMany($collection_selector, $value_selector = null)
    {
        $collection_counter = 0;
        $item_counter = 0;
        $new_data = [];
        foreach ($this->data as $element) {
            $items = call_user_func_array($collection_selector, [$element, $collection_counter]);
            foreach ($items as $item) {
                if (is_callable($value_selector)) {
                    $item = call_user_func_array($value_selector, [$item, $item_counter]);
                }
                $new_data[] = $item;
                $item_counter++;
            }
            $collection_counter++;

        }

        $this->data = $new_data;

        return $this;
    }

    /**
     * @param int $sort_flags
     * @return LINQ
     */
    public function distinct($sort_flags = SORT_REGULAR)
    {
        $this->data = array_unique($this->data, $sort_flags);
        return $this;
    }

    /**
     * @param callable $callable
     * @param int $sort
     * @return LINQ
     */
    public function orderBy($callable = null, $sort = SORT_ASC)
    {
        $ordered_list = [];
        foreach ($this->data as $key => $value) {
            $response = $value;
            if ($callable != null) {
                $response = call_user_func($callable, $value);
            }

            $ordered_list[$key] = $response;
        }

        switch ($sort) {
            case SORT_ASC:
                asort($ordered_list);
                break;
            case SORT_DESC:
                arsort($ordered_list);
                break;
        }

        $new_data = [];
        foreach ($ordered_list as $key => $value) {
            $new_data[$key] = $this->data[$key];
        }

        $this->data = $new_data;
        return $this;
    }

    /**
     * @param callable $callable
     * @return LINQ
     */
    public function orderByDescending($callable = null)
    {
        return $this->orderBy($callable, SORT_DESC);
    }

    /**
     * @param callable $key_selector
     * @param callable $value_selector
     * @return LINQ
     */
    public function groupBy($key_selector, $value_selector = null)
    {
        $new_data = [];

        foreach ($this->data as $key => $value) {
            $new_key = call_user_func($key_selector, $value);
            if (!array_key_exists($new_key, $new_data)) {
                $new_data[$new_key] = [];
            }

            if (is_callable($value_selector)) {
                $value = $value_selector($value);
            }

            $new_data[$new_key][] = $value;
        }

        $this->data = $new_data;
        return $this;
    }

    /**
     * @return LINQ
     */
    public function reverse()
    {
        $this->data = array_reverse($this->data);
        return $this;
    }

    /**
     * @param int $count
     * @return LINQ
     */
    public function skip($count)
    {
        $new_data = [];
        foreach ($this->data as $key => $value) {
            if ($count <= 0) {
                $new_data[$key] = $value;
            }
            $count--;
        }
        $this->data = $new_data;
        return $this;
    }

    /**
     * @param int $count
     * @return LINQ
     */
    public function take($count)
    {
        $new_data = [];
        foreach ($this->data as $key => $value) {
            if ($count > 0) {
                $new_data[$key] = $value;
            } else {
                break;
            }
            $count--;
        }
        $this->data = $new_data;
        return $this;
    }

    /**
     * @param array $new_values
     * @return LINQ
     */
    public function concat($new_values)
    {
        $this->data = array_merge($this->data, $new_values);
        return $this;
    }

    /**
     * @param array $new_values
     * @return LINQ
     */
    public function union($new_values)
    {
        $this->concat($new_values);
        return $this->distinct();
    }

    /**
     * @return LINQ
     */
    public function reIndex()
    {
        $this->data = array_values($this->data);
        return $this;
    }

    #region Finishers

    /**
     * @param callable $callable
     */
    public function each($callable)
    {
        foreach ($this->data as $key => $value) {
            $response = call_user_func_array($callable, [$key, $value]);
            if ($response === false) {
                break;
            }
        }
    }

    /**
     * @param callable $callable
     * @param bool $throw_if_not_found
     * @param mixed $default
     * @return mixed
     * @throws InvalidOperationException
     */
    public function single($callable = null, $throw_if_not_found = true, $default = null)
    {
        $this->where($callable);
        if ($this->count() > 1) {
            throw  new InvalidOperationException();
        } else if ($this->count() === 1) {
            return $this->first();
        }

        if ($throw_if_not_found) {
            throw new InvalidOperationException();
        } else {
            return $default;
        }
    }

    /**
     * @param callable $callable
     * @param mixed $default
     * @return mixed
     */
    public function singleOrDefault($callable = null, $default = null)
    {
        return $this->single($callable, false, $default);
    }

    /**
     * @param int $index
     * @param bool $throw_if_not_found
     * @param mixed $default
     * @return mixed
     * @throws ArgumentOutOfRangeException
     */
    public function elementAt($index, $throw_if_not_found = true, $default = null)
    {
        $counter = 0;
        foreach ($this->data as $key => $value) {
            if ($index === $counter) {
                return $value;
            }
            $counter++;
        }

        if ($throw_if_not_found) {
            throw  new ArgumentOutOfRangeException();
        } else {
            return $default;
        }
    }

    /**
     * @param int $index
     * @param mixed $default
     * @return mixed
     */
    public function elementAtOrDefault($index, $default = null)
    {
        return $this->elementAt($index, false, $default);
    }

    /**
     * @param mixed $value
     * @return bool
     */
    public function contains($value)
    {
        return in_array($value, $this->data, true);
    }

    /**
     * @param callable $callable
     * @return int
     */
    public function count($callable = null)
    {
        $this->where($callable);
        return count($this->data);
    }

    /**
     * @param callable $callable
     * @return float|int
     */
    public function average($callable = null)
    {
        $count = $this->count();
        $sum = $this->sum($callable);

        if ($count === 0) {
            return 0;
        }

        return $sum / $count;
    }

    /**
     * @param callable $callable
     * @return float|int
     */
    public function sum($callable = null)
    {
        $sum = 0;
        foreach ($this->data as $key => $value) {
            $response = $value;
            if ($callable != null) {
                $response = call_user_func($callable, $value);
            }

            $sum += $response;
        }
        return $sum;
    }

    /**
     * @param callable $callable
     * @return float|int
     * @throws InvalidArgumentException
     */
    public function max($callable = null)
    {
        if ($this->count() === 0) {
            throw new InvalidArgumentException();
        }
        $max = -INF;
        foreach ($this->data as $key => $value) {
            $response = $value;
            if ($callable != null) {
                $response = call_user_func($callable, $value);
            }

            if ($response > $max) {
                $max = $response;
            }
        }
        return $max;
    }


    /**
     * @param callable $callable
     * @return float|int
     * @throws InvalidArgumentException
     */
    public function min($callable = null)
    {
        if ($this->count() === 0) {
            throw new InvalidArgumentException();
        }
        $min = INF;
        foreach ($this->data as $key => $value) {
            $response = $value;
            if ($callable != null) {
                $response = call_user_func($callable, $value);
            }

            if ($response < $min) {
                $min = $response;
            }
        }
        return $min;
    }

    /**
     * @param callable $callable
     * @return bool
     */
    public function any($callable = null)
    {
        $this->where($callable);
        if (count($this->data) > 0) {
            return true;
        }
        return false;
    }

    /**
     * @param callable $callable
     * @param bool $throw_if_not_found
     * @param mixed $default
     * @return mixed
     * @throws InvalidOperationException
     */
    public function first($callable = null, $throw_if_not_found = true, $default = null)
    {
        $this->where($callable);

        if (count($this->data) > 0) {
            $values = array_values($this->data);
            return $values[0];
        }
        if ($throw_if_not_found) {
            throw  new InvalidOperationException();
        } else {
            return $default;
        }
    }

    /**
     * @param callable $callable
     * @param mixed $default
     * @return mixed
     */
    public function firstOrDefault($callable = null, $default = null)
    {
        return $this->first($callable, false, $default);
    }


    /**
     * @param callable $callable
     * @param bool $throw_if_not_found
     * @param mixed $default
     * @return mixed
     * @throws InvalidOperationException
     */
    public function last($callable = null, $throw_if_not_found = true, $default = null)
    {
        $this->where($callable);

        if (count($this->data) > 0) {
            $values = array_values($this->data);
            return $values[count($values) - 1];
        }

        if ($throw_if_not_found) {
            throw  new InvalidOperationException();
        } else {
            return $default;
        }
    }

    /**
     * @param callable $callable
     * @param mixed $default
     * @return mixed
     */
    public function lastOrDefault($callable = null, $default = null)
    {
        return $this->last($callable, false, $default);
    }

    /**
     * @param callable $callable
     * @param mixed $seed
     * @return mixed
     * @author ahmetturk <ahmetturk93@gmail.com>
     */
    public function aggregate($callable, $seed)
    {
        $accumulator = $seed;
        foreach ($this->data as $value) {
            $accumulator = $callable($accumulator, $value);
        }

        return $accumulator;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->data;
    }

#endregion

#endregion
}
