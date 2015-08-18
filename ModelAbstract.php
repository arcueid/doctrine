<?php

namespace app\Model;

abstract class ModelAbstract
{
    protected static $entity;
    public $cache,
           $cache_settings,
           $em;

    /**
     * Set cache.
     *
     * @param [string] $function_name
     * @param [array]  $function_args
     * @param [mixed]  $data
     */
    public function setCache($function_name, $function_args, $data)
    {
        $lifespan = !empty($this->cache_settings[$function_name]) ? $this->cache_settings[$function_name] : 1800;
        $this->cache->save($this->getCacheKey($function_name, $function_args), $data, $lifespan);
    }
    /**
     * Retrieve cache data.
     *
     * @param [string] $function_name
     * @param [array]  $function_args
     *
     * @return [mixed]
     */
    public function getCache($function_name, $function_args)
    {
        if ($this->cache->contains($this->getCacheKey($function_name, $function_args))) {
            return $this->cache->fetch($this->getCacheKey($function_name, $function_args));
        }

        return false;
    }
    /**
     * Retrieve cache key.
     *
     * @param [string] $function_name
     * @param [array]  $function_args
     *
     * @return [string] $key
     */
    public function getCacheKey($function_name, $function_args)
    {
        if (!empty($function_args)) {
            foreach ($function_args as &$arg) {
                $arg = \cw\Service\Helper::objectToArray($arg);
                $arg = is_array($arg) ? serialize($arg) : $arg;
            }
        }

        $key = get_called_class().'_'.$function_name.'_'.implode('_', $function_args);
        $key = md5($key);

        return $key;
    }

    /**
     * Remove cache.
     *
     * @param [string] $function_name
     * @param [array]  $function_args
     *
     * @return [void]
     */
    public function removeCache($function_name, $function_args)
    {
        $this->cache->delete($this->getCacheKey($function_name, $function_args));
    }

    /**
     * Basic equal.
     *
     * @param [array] $data
     *
     * @return [mixed]
     */
    public function select($data)
    {
        return $this->em->getRepository(static::$entity)->findOneBy($data);
    }

    /**
     * Selecting by custom params.
     *
     * @param [array] $params
     *
     * @return [mixed]
     */
    public function getByParams($params = array(), $raw = 0)
    {
        $cache = $this->getCache(get_class($this).__FUNCTION__, func_get_args());
        if ($cache) {
            return $cache;
        }

        $qb = $this->em->createQueryBuilder();
        $qb->select('e')
           ->from(static::$entity, 'e');

        if (!empty($params)) {
            foreach ($params as $key => $param) {
                switch ($key) {
                    case 'limit':
                        $qb->setMaxResults($param);
                        break;
                    case 'offset':
                        $qb->setFirstResult($param);
                        break;
                    case 'order':
                        $qb->orderBy('e.'.$param['column'], $param['orientation']);
                        break;
                    default:
                        $operator = $param['operator'];
                        $value    = $param['value1'];
                        $qb->andWhere($qb->expr()->$operator("e.$key", ':'.$key))
                           ->setParameter($key, $value);
                        break;
                }
            }
        }

        $query = $qb->getQuery();
        if ($raw) {
            $result = $query->getResult();
        }
        $result = $query->getArrayResult();
        if (!empty($result)) {
            $this->setCache(get_class($this).__FUNCTION__, func_get_args(), $result);

            return $result;
        }

        return false;
    }

    /**
     * Basic count.
     *
     * @return [int]
     */
    public function getCount()
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('COUNT(e.id)')
           ->from(static::$entity, 'e');
        $query = $qb->getQuery();

        return $query->getSingleScalarResult();
    }

    /**
     * Updating by id.
     *
     * @param [array] $data
     * @param [int]   $id
     *
     * @return [mixed]
     */
    public function update($id = 0, $data = array())
    {
        if (!empty($id)) {
            $entity = $this->em->find(static::$entity, $id);
            if (!empty($entity)) {
                $entity->hydrate($data);
                $this->em->flush();

                return $entity->id;
            }
        }

        return false;
    }

    /**
     * Updating by params.
     *
     * @param [array] $params filter
     * @param [array] $data   updating data
     *
     * @return [bool]
     */
    public function updateByParams($params = array(), $data = array())
    {
        if (empty($data)) {
            return false;
        }

        $qb = $this->em->createQueryBuilder();
        $qb->update(static::$entity, 'e');
        foreach ($data as $dKey => $dValue) {
            $qb->set("e.$dKey", ':upd_'.$dKey)
               ->setParameter("upd_$dKey", "$dValue");
        }
        if (!empty($params)) {
            foreach ($params as $key => $param) {
                $operator = $param['operator'];
                $value    = $param['value1'];
                $qb->andWhere($qb->expr()->$operator("e.$key", ':'.$key))
                   ->setParameter("$key", "$value");
            }
        }
        $query  = $qb->getQuery();
        $result = $query->execute();

        return true;
    }

    /**
     * Create new row in db.
     *
     * @param [array] $data
     *
     * @return [int]
     */
    public function create($data)
    {
        $entity = new static::$entity();
        $entity->hydrate($data);
        $this->em->persist($entity);
        $this->em->flush();

        return (!empty($entity->id) ? $entity->id : 0);
    }

    /**
     * Remove by id.
     *
     * @param [int] $id
     *
     * @return [bool]
     */
    public function delete($id = 0)
    {
        if (!empty($id)) {
            $entity = $this->em->find(static::$entity, $id);
            if (!empty($entity)) {
                $this->em->remove($entity);
                $this->em->flush();

                return true;
            }
        }

        return false;
    }
}
