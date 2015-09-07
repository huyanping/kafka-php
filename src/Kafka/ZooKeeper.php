<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker: */
// +---------------------------------------------------------------------------
// | SWAN [ $_SWANBR_SLOGAN_$ ]
// +---------------------------------------------------------------------------
// | Copyright $_SWANBR_COPYRIGHT_$
// +---------------------------------------------------------------------------
// | Version  $_SWANBR_VERSION_$
// +---------------------------------------------------------------------------
// | Licensed ( $_SWANBR_LICENSED_URL_$ )
// +---------------------------------------------------------------------------
// | $_SWANBR_WEB_DOMAIN_$
// +---------------------------------------------------------------------------

namespace Kafka;

/**
 * +------------------------------------------------------------------------------
 * Kafka protocol since Kafka v0.8
 * +------------------------------------------------------------------------------
 *
 * @package
 * @version $_SWANBR_VERSION_$
 * @copyright Copyleft
 * @author $_SWANBR_AUTHOR_$
 * +------------------------------------------------------------------------------
 */

class ZooKeeper
{
    /**
     * get all broker
     */
    const BROKER_PATH = '/brokers/ids';

    /**
     * get broker detail
     */
    const BROKER_DETAIL_PATH = '/brokers/ids/%d';

    /**
     * get topic detail
     */
    const TOPIC_PATCH = '/brokers/topics/%s';

    /**
     * get partition state
     */
    const PARTITION_STATE = '/brokers/topics/%s/partitions/%d/state';

    /**
     * register consumer
     */
    const REG_CONSUMER = '/consumers/%s/ids/%s';

    /**
     * list consumer
     */
    const LIST_CONSUMER = '/consumers/%s/ids';

    /**
     * partition owner
     */
    const PARTITION_OWNER = '/consumers/%s/owners/%s/%d';

    /**
     * zookeeper
     *
     * @var mixed
     * @access private
     */
    private $zookeeper = null;

    /**
     * zookeeper base path
     *
     * @var string
     */
    private $base_path = '';

    /**
     * __construct
     *
     * @access public
     * @param $hostList
     * @param null $timeout
     * @param string $base_path
     */
    public function __construct($hostList, $timeout = null, $base_path = '')
    {
        if (!is_null($timeout) && is_numeric($timeout)) {
            $this->zookeeper = new \ZooKeeper($hostList, null, $timeout);
        } else {
            $this->zookeeper = new \Zookeeper($hostList);
        }
        $this->base_path = $base_path;
    }

    /**
     * get broker list using zookeeper
     *
     * @access public
     * @return array
     */
    public function listBrokers()
    {
        $result = array();
        $broker_path = $this->base_path . DIRECTORY_SEPARATOR .self::BROKER_PATH;
        $lists = $this->zookeeper->getChildren($broker_path);
        if (!empty($lists)) {
            foreach ($lists as $brokerId) {
                $brokerDetail = $this->getBrokerDetail($brokerId);
                if (!$brokerDetail) {
                    continue;
                }
                $result[$brokerId] = $brokerDetail;
            }
        }

        return $result;
    }

    /**
     * get broker detail
     *
     * @param integer $brokerId
     * @access public
     * @return array|boolean
     */
    public function getBrokerDetail($brokerId)
    {
        $result = array();
        $broker_detail_path = $this->base_path . DIRECTORY_SEPARATOR .self::BROKER_DETAIL_PATH;
        $path = sprintf($broker_detail_path, (int)$brokerId);
        if ($this->zookeeper->exists($path)) {
            $result = $this->zookeeper->get($path);
            if (!$result) {
                return false;
            }

            $result = json_decode($result, true);
        }

        return $result;
    }

    /**
     * get topic detail
     *
     * @param string $topicName
     * @access public
     * @return array|boolean
     */
    public function getTopicDetail($topicName)
    {
        $result = array();
        $topic_patch = $this->base_path . DIRECTORY_SEPARATOR . self::TOPIC_PATCH;
        $path = sprintf($topic_patch, (string)$topicName);
        if ($this->zookeeper->exists($path)) {
            $result = $this->zookeeper->get($path);
            if (!$result) {
                return false;
            }
            $result = json_decode($result, true);
        }

        return $result;
    }

    /**
     * get partition state
     *
     * @param string $topicName
     * @param integer $partitionId
     * @access public
     * @return array|boolean
     */
    public function getPartitionState($topicName, $partitionId = 0)
    {
        $result = array();
        $partition_state = $this->base_path . DIRECTORY_SEPARATOR . self::PARTITION_STATE;
        $path = sprintf($partition_state, (string)$topicName, (int)$partitionId);
        if ($this->zookeeper->exists($path)) {
            $result = $this->zookeeper->get($path);
            if (!$result) {
                return false;
            }
            $result = json_decode($result, true);
        }

        return $result;
    }

    /**
     * register consumer
     *
     * @param $groupId
     * @param $consumerId
     * @param array $topics
     * @return array|bool
     * @access public
     */
    public function registerConsumer($groupId, $consumerId, $topics = array())
    {
        if (empty($topics)) {
            return true;
        }

        $reg_consumer = $this->base_path . DIRECTORY_SEPARATOR . self::REG_CONSUMER;
        $path = sprintf($reg_consumer, (string)$groupId, (string)$consumerId);
        $subData = array();
        foreach ($topics as $topic) {
            $subData[$topic] = 1;
        }
        $data = array(
            'version' => '1',
            'pattern' => 'white_list',
            'subscription' => $subData,
        );
        if (!$this->zookeeper->exists($path)) {
            $this->makeZkPath($path);
            $this->makeZkNode($path, json_encode($data));
        } else {
            $this->zookeeper->set($path, json_encode($data));
        }
    }

    /**
     * list consumer
     *
     * @param string $groupId
     * @access public
     * @return array|boolean
     */
    public function listConsumer($groupId)
    {
        $list_consumer = $this->base_path . DIRECTORY_SEPARATOR . self::LIST_CONSUMER;
        $path = sprintf($list_consumer, (string)$groupId);
        if (!$this->zookeeper->exists($path)) {
            return array();
        } else {
            return $this->zookeeper->getChildren($path);
        }
    }

    /**
     * get consumer per topic
     *
     * @param string $groupId
     * @access public
     * @return array
     */
    public function getConsumersPerTopic($groupId)
    {
        $consumers = $this->listConsumer($groupId);
        if (empty($consumers)) {
            return array();
        }

        $topics = array();
        foreach ($consumers as $consumerId) {
            $reg_consumer = $this->base_path . DIRECTORY_SEPARATOR . self::REG_CONSUMER;
            $path = sprintf($reg_consumer, (string)$groupId, (string)$consumerId);
            if (!$this->zookeeper->exists($path)) {
                continue;
            }

            $info = $this->zookeeper->get($path);
            $info = json_decode($info, true);
            $subTopic = isset($info['subscription']) ? $info['subscription'] : array();
            foreach ($subTopic as $topic => $num) {
                $topics[$topic] = $consumerId;
            }
        }

        return $topics;
    }

    /**
     * add partition owner
     *
     * @param string $groupId
     * @param string $topicName
     * @param integer $partitionId
     * @param string $consumerId
     * @access public
     * @return void
     */
    public function addPartitionOwner($groupId, $topicName, $partitionId, $consumerId)
    {
        $partition_owner = $this->base_path . DIRECTORY_SEPARATOR . self::PARTITION_OWNER;
        $path = sprintf($partition_owner, (string)$groupId, $topicName, (string)$partitionId);
        if (!$this->zookeeper->exists($path)) {
            $this->makeZkPath($path);
            $this->makeZkNode($path, $consumerId);
        } else {
            $this->zookeeper->set($path, $consumerId);
        }
    }

    /**
     * Equivalent of "mkdir -p" on ZooKeeper
     *
     * @param string $path The path to the node
     * @param mixed $value The value to assign to each new node along the path
     *
     * @return bool
     */
    protected function makeZkPath($path, $value = 0)
    {
        $parts = explode('/', $path);
        $parts = array_filter($parts);
        $subpath = '';
        while (count($parts) > 1) {
            $subpath .= '/' . array_shift($parts);
            if (!$this->zookeeper->exists($subpath)) {
                $this->makeZkNode($subpath, $value);
            }
        }
    }

    /**
     * Create a node on ZooKeeper at the given path
     *
     * @param string $path The path to the node
     * @param mixed $value The value to assign to the new node
     *
     * @return bool
     */
    protected function makeZkNode($path, $value)
    {
        $params = array(
            array(
                'perms' => \Zookeeper::PERM_ALL,
                'scheme' => 'world',
                'id' => 'anyone',
            )
        );

        return $this->zookeeper->create($path, $value, $params);
    }
}
