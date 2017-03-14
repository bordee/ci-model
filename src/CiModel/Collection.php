<?php

namespace CiModel;

use Utils\Collection as GeneralCollection;

class Collection extends GeneralCollection
{
    /**
     * @var string[]
     */
    protected $indexFields = array();
    
    /**
     * @param array $indexFields
     */
    public function __construct(array $indexFields = array())
    {
        $this->indexFields = $indexFields;
        sort($this->indexFields);
    }
    
    /**
     * @param Model $elem
     */
    public function add($elem)
    {
        $this->elements[$this->getIndex($elem)] = $elem;
    }

    /**
     * @return Model
     */
    public function current()
    {
        return parent::current();
    }

    /**
     * @return Model
     */
    public function next()
    {
        return parent::next();
    }

    /**
     * @return Model
     */
    public function rewind()
    {
        return parent::rewind();
    }
    
    /**
     * @return int[]
     */
    public function getIds()
    {
        $ids = array();
        foreach ($this->elements as $model) {
            $ids[] = $model->getId();
        }
        return $ids;
    }
    
    /**
     * @param array $data
     * @return array
     */
    public function update(array $data)
    {
        $result = array();
        foreach ($this->elements as $model) {
            foreach ($data as $key => $value) {
                $model->set($key, $value);
            }
        }
        return $result;
    }
    
    /**
     * @param array $condition
     * @return Collection
     */
    public function findAll(array $condition)
    {
        $collection = new self();
        $sortedCondition = $condition;
        ksort($sortedCondition);
        if (
            array() !== $this->indexFields
            && array_keys($sortedCondition) === $this->indexFields
            && (($model = $this->findByIndex($sortedCondition)) instanceof Model)
        ) {
            $collection->add($model);
            return $collection;
        }
        foreach ($this as $model) {
            if (!$model->matchesCondition($condition)) {
                continue;
            }
            $collection->add($model);
        }
        return $collection;
    }
    
    /**
     * @param array $condition
     * @return Model
     */
    protected function findByIndex(array $condition)
    {
        $index = $this->valuesToHash($condition);
        return isset($this->elements[$index])
            ? $this->elements[$index]
            : null;
    }
    
    /**
     * @param Model $elem
     * @return string
     */
    protected function getIndex(Model $elem)
    {
        if (array() === $this->indexFields) {
            return count($this->elements);
        }
        $values = array();
        foreach($this->indexFields as $field) {
            $values[$field] = $elem->get($field);
        }
        return $this->valuesToHash($values);
    }
    
    /**
     * @param array $values
     * @return string
     */
    protected function valuesToHash(array $values)
    {
        return md5(json_encode($values));
    }
}