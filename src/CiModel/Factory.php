<?php

namespace CiModel;

use Logger\LoggerInterface as Logger;

use CI_DB_driver;

use CI_Cache;

use RuntimeException;

class Factory
{

    const NamespaceSeparator = "\\";

    /**
     * @var CI_DB_driver
     */
    protected $db;

    /**
     * @var CI_Cache
     */
    protected $cache;
    
    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var string
     */
    protected $namespace = '';
    
    /**
     * @param CI_DB_driver $db
     * @param CI_Cache $cache
     */
    public function __construct(CI_DB_driver $db, CI_Cache $cache)
    {
        $this->db = $db;
        $this->cache = $cache;
    }
    
    /**
     * @param Logger $logger
     */
    public function setLogger(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param string $ns
     */
    public function setNamespace($ns)
    {
        $this->namespace = (string)$ns;
    }
    
    /**
     * @param string $class
     * @return Model
     */
    public function getModel($class)
    {
        $model = $this->getNewModel((string)$class);
        $model->setFactory($this);
        $model->setDb($this->db);
        $model->setCache($this->cache);
        $this->logger && $model->setLogger($this->logger);
        return $model;
    }
    
    /**
     * @TODO ...
     * @param string $class
     * @param array $data
     * @return Model
     */
    public function getModelWithData($class, array $data)
    {
        $model = $this->getNewModel((string)$class, $data);
        $model->setFactory($this);
        $model->setDb($this->db);
        $model->setCache($this->cache);
        $this->logger && $model->setLogger($this->logger);
        return $model;
    }
    
    /**
     * @param string $class
     * @param int $id
     * @return Model
     */
    public function getModelById($class, $id)
    {
        $model = $this->getModel($class);
        $model->loadById($id);
        return $model;
    }
    
    /**
     * @param array $indexFields
     * @return Collection
     */
    public function getNewCollection(array $indexFields = array())
    {
        return new Collection($indexFields);
    }
    
    /**
     * @param string $class
     * @param array $data
     * @return Model
     * @throws RuntimeException
     */
    protected function getNewModel($class, array $data = null)
    {
        $classWithNS = $this->getClassWithNamespce($class);
        return new $classWithNS($data);
    }

    /**
     * @param string $class
     * @return string
     */
    protected function getClassWithNamespce($class)
    {
        return !strpos($class, self::NamespaceSeparator)
                && $this->namespace != ""
            ? $this->namespace . self::NamespaceSeparator . $class
            : $class;
    }
    
}