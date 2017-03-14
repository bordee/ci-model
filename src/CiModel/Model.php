<?php

namespace CiModel;

use Logger\LoggerInterface as Logger;

use CI_DB_driver;
use CI_DB_query_builder;
use CI_Cache;

use RuntimeException;

abstract class Model
{
    /**
     * @var string
     */
    protected $tableName = '';

    /**
     * @var string
     */
    protected $idField = 'id';
    
    /**
     * @var boolean
     */
    protected $loaded = false;
    
    /**
     * @var boolean
     */
    protected $modified = false;
    
    /**
     * @var array
     */
    protected $data = array();
    
    /**
     * @var CI_DB_query_builder
     */
    protected $db;

    /**
     * @var CI_Cache
     */
    protected $cache;
    
    /**
     * @var Factory
     */
    protected $factory;
    
    /**
     * @var Logger
     */
    protected $logger;
    
    /**
     * @var array
     */
    protected $modified_keys = array();

    /**
     * @var array
     */
    public function __construct(array $data = null)
    {
        if (null !== $data) {
            /**
             * @TODO validation?
             */
            $this->data = $data;
            $this->loaded = true;
            $this->modified = false;
        }
    }

    /**
     * @return bool
     */
    public function trans_begin()
    {
        return $this->db->trans_begin();
    }
    
    /**
     * @return bool $b
     */
    public function hasError()
    {
        return $this->db->trans_status() === false;
    }

    /**
     * @return array
     */
    public function getError()
    {
        return $this->db->error();
    }
    
    /**
     * @return bool
     */
    public function trans_commit()
    {
        return $this->db->trans_commit();
    }
    
    /**
     * @return bool
     */
    public function trans_rollback()
    {
        $this->db->set_trans_status(true);
        return $this->db->trans_rollback();
    }
    
    public function close_db()
    {
        $this->db->close();
    }
    
    /**
     * @param CI_DB_driver $db
     */
    public function setDb(CI_DB_driver $db)
    {
        $this->db = $db;
    }

    /**
     * @param CI_Cache $cache
     */
    public function setCache(CI_Cache $cache)
    {
        $this->cache = $cache;
    }
    
    /**
     * @param Factory $factory
     */
    public function setFactory(Factory $factory)
    {
        $this->factory = $factory;
    }
    
    /**
     * @param Logger $logger
     */
    public function setLogger(Logger $logger)
    {
        $this->logger = $logger;
    }
    
    /**
     * @param mixed $key
     * @param mixed $value
     * @return Model
     */
    public function set($key, $value)
    {
        if (
            !array_key_exists($key, $this->data) // isset() returns false if value is set to null!
            || $this->data[$key] != $value
        ) {
            $this->modified = true;
            if($this->loaded) {
                $this->modified_keys[$key] = 1;
            }
        }
        $this->data[$key] = $value;
        return $this;
    }
    
    /**
     * @param string $key
     * @return mixed|null
     */
    public function get($key)
    {
        $_key = (string)$key;
        return isset($this->data[$_key])
            ? $this->data[$_key]
            : null;
    }
    
    /**
     * @return string[]
     */
    public function getFieldNames()
    {
        return array_keys($this->data);
    }
    
    /**
     * @return boolean
     */
    public function exists()
    {
        return $this->loaded;
    }
    
    /**
     * @return boolean
     */
    public function isModified()
    {
        return $this->modified;
    }
    
    /**
     * @desc Get value of the field with name set in $idField.
     * @return int
     */
    public function getId()
    {
        return isset($this->id)
            ? $this->id
            : $this->id = (isset($this->data[$this->idField]) ? $this->data[$this->idField] : null);
    }
    
    /**
     * @param int $id
     * @return Model
     */
    public function loadById($id)
    {
        $this->load(array($this->idField => (int)$id));
        if (!$this->loaded) {
            $this->set($this->idField, (int)$id);
        }
        return $this;
    }
    
    /**
     * @desc Find the FIRST record that matches $condition.
     * @param array $condition
     * @return Model
     */
    public function find(array $condition)
    {
        return $this->load($condition);
    }
    
    /**
     * @param array $condition
     * @return int[]
     */
    public function findAllIds($condition)
    {
        $ids = array();
        $result = $this->db->select($this->idField)->from($this->getTableName())->where($condition)->get();
        if (
            !$result
            || !$result->num_rows()
        ) {
            return $ids;
        }
        foreach ($result->result_array() as $row) {
            $ids[] = $row[$this->idField];
        }
        return $ids;
    }
    
    /**
     * @param array $condition
     * @param int $limit
     * @param int $offset
     * @return Collection
     */
    public function findAll($condition, $limit = null, $offset = null, array $indexFields = array())
    {
        $collection = $this->factory->getNewCollection($indexFields);
        $result = $this->db->select()->from($this->getTableName())->where($condition)->limit($limit, $offset)->get();
        if (
            !$result
            || !$result->num_rows()
        ) {
            return $collection;
        }
        $class = $this->getClass();
        foreach($result->result_array() as $data) {
            $collection->add($this->factory->getModelWithData($class, $data));
        }
        return $collection;
    }
    
    /**
     * @param mixed $condition
     * @return int
     */
    public function countAll($condition)
    {
        return $this->db->from($this->getTableName())->where($condition)->count_all_results();
    }
    
    /**
     * @param array $condition
     * @return mixed
     */
    public function deleteAll(array $condition)
    {
        return $this->db->delete($this->getTableName(), $condition);
    }
    
    /**
     * @param array $data
     * @param array $condition
     * @return object
     */
    public function updateAll($data, $condition)
    {
        return $this->db->update($this->getTableName(), $data, $condition);
    }
    
    /**
     * @param array $condition
     * @return boolean
     */
    public function matchesCondition(array $condition)
    {
        foreach($condition as $key => $value) {
            $_op = null;
            $_key = null;
            if (false !== stripos($key, ' ')) {
                $keyAndOp = explode(' ', $key);
                $_key = $keyAndOp[0];
                $_op = $keyAndOp[1];
            } else {
                $_key = $key;
            }
            switch($_op) {
                case "<>":
                    if ($this->get($_key) == $value) {
                        return false;
                    }
                case "=":
                default:
                    if ($this->get($_key) != $value) {
                        return false;
                    }
            }
        }
        return true;
    }
    
    /**
     * @return mixed
     */
    public function delete()
    {
        if (!$this->loaded) {
            return true;
        }
        /**
         * @TODO ID would be sufficient, if it's available...
         */
        return $this->db->delete($this->getTableName(), $this->data);
    }
    
    /**
     * @param array $condition
     * @return Model
     */
    protected function load(array $condition)
    {
        /**
         * @TODO
         *  - caching???
         *  - '*'  (lazy load?) ???
         *  - error handling
         */
        $result = $this->db->select()->from($this->getTableName())->where($condition)->limit(1)->get();
 
        if (
            !$result
            || !$result->num_rows()
        ) {
            return $this;
        }

        $this->data = $result->row_array();
        $this->loaded = true;
        $this->modified = false;
        
        return $this;
    }
    
    /**
     * @return object
     */
    public function save()
    {
        return $this->exists()
            ? $this->update()
            : $this->insert();
    }
    
    /**
     * @return object
     */
    protected function update()
    {
        /**
         * @TODO ...
         */
        if (!$this->modified) {
            return true;
        }
        $this->modified = false;
        
        $result = $this->db->update(
            $this->getTableName(),
            array_intersect_key($this->data, $this->modified_keys),
            $this->getUpdateCondition()
        );
        
        $this->modified_keys = array();
        
        return false !== $result;
    }
    
    /**
     * @return object
     */
    protected function insert()
    {

        $result = $this->db->insert($this->getTableName(), $this->data);

        $query = $this->db->last_query();
        
        $id = $this->db->insert_id();
        
        if (
            false === $result
            || (!$id && $this->getTableName() !== 'import_status')
        ) {
            /** @TODO ... */
            if (
                null === $this->data
                || empty($this->data)
            ) {
                var_dump('Data is empty!!!');
            }
            throw new RuntimeException('ERROR saving to database!!! Last statement: ' . $query);
        }

        $this->data[$this->idField] = $id;

        $this->loaded = true;
        $this->modified = false;
        
        return false !== $result;
    }
    
    /**
     * @return string
     */
    protected function getTableName()
    {
        return ('' != $this->tableName)
            ? $this->tableName
            : $this->tableName = $this->getTableNameFromClass();
    }

    /**
     * @return string
     */
    protected function getTableNameFromClass()
    {
        return join(
            '_',
            array_map(
                function($x) { return lcfirst($x); },
                preg_split('/(?=[A-Z])/', $this->getClass(), -1, PREG_SPLIT_NO_EMPTY)
            )
        );
    }
    
    /**
     * @return string
     */
    protected function getClass()
    {
        if (isset($this->class))
        {
            return $this->class;
        }
        $class = get_class($this);
        $pos = strrpos($class, Factory::NamespaceSeparator);
        return $this->class =
            ($pos
                ? substr($class, $pos + 1)
                : $class
            );
    }
    
    /**
     * @return string
     * @throws RuntimeException
     */
    protected function getUpdateCondition()
    {
        if (isset($this->data[$this->idField])) {
            return array($this->idField => $this->getId());
        } else {
            throw new RuntimeException("Missing update condition for table '" . $this->getTableName() . "'");
        }
    }
}