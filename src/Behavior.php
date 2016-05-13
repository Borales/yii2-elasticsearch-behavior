<?php

namespace borales\behaviors\elasticsearch;

use yii\base\Event;
use yii\base\InvalidConfigException;
use yii\base\InvalidParamException;
use yii\db\BaseActiveRecord;
use yii\db\Expression;
use yii\elasticsearch\ActiveRecord;
use yii\elasticsearch\Connection;

/**
 * Yii2 behavior to support Elasticsearch indexing.
 * To use \borales\behaviors\elasticsearch\Behavior, insert the following code to your ActiveRecord class:
 * ```
 *  public function behaviors()
 *  {
 *      return [
 *          ...
 *          'elasticsearch' => [
 *              'class' => '\borales\behaviors\elasticsearch\Behavior',
 *              'mode' => 'command',
 *              'elasticIndex' => 'project-index',
 *              'elasticType' => 'posts',
 *              'dataMap' => [
 *                  'id' => 'id',
 *                  'title' => 'name',
 *                  'body' => function() {
 *                      return strip_tags($this->body);
 *                  },
 *                  'date_publish' => function() {
 *                      return date('U', strtotime($this->date_create));
 *                  },
 *                  'author' => function() {
 *                      return ucfirst($this->author->name);
 *                  }
 *              ]
 *          ],
 *          ...
 *      ];
 *  }
 * ```
 *
 * @link https://github.com/Borales/yii2-elasticsearch-behavior
 * @author Borales <bordun.alexandr@gmail.com>
 * @version 0.0.1
 */
class Behavior extends \yii\base\Behavior
{
    const MODE_COMMAND = 'command';
    const MODE_MODEL = 'model';

    /**
     * @var string Behavior mode
     */
    public $mode = self::MODE_COMMAND;
    /**
     * @var string Elasticsearch App Component
     */
    public $esComponent = 'elasticsearch';
    /**
     * @var string Class name (extended from \yii\elasticsearch\ActiveRecord)
     */
    public $elasticClass;
    /**
     * @var string Elasticsearch Index
     */
    public $elasticIndex;
    /**
     * @var string Elasticsearch Type
     */
    public $elasticType;
    /**
     * @var array Model's data
     */
    public $dataMap;

    /**
     * @throws InvalidConfigException
     */
    public function init()
    {
        parent::init();
        if ($this->mode == self::MODE_COMMAND) {
            if (!$this->elasticType || !$this->elasticIndex) {
                throw new InvalidConfigException("You must set 'elasticIndex' and 'elasticType' attributes while working in MODE_COMMAND");
            }
        } else {
            if (!$this->elasticClass) {
                throw new InvalidConfigException("You must set 'elasticClass' attribute (extended from \\yii\\elasticsearch\\ActiveRecord) while working in MODE_MODEL");
            }
        }
    }

    /**
     * @return array
     */
    public function events()
    {
        return [
            BaseActiveRecord::EVENT_AFTER_INSERT => 'insert',
            BaseActiveRecord::EVENT_AFTER_UPDATE => 'update',
            BaseActiveRecord::EVENT_AFTER_DELETE => 'delete',
        ];
    }

    /**
     * Inserting Elasticsearch index record
     * @param Event $event
     * @param null $data
     */
    public function insert(Event $event, $data = null)
    {
        $data = $data ? $data : $this->getProcessedData();
        if ($this->mode == self::MODE_COMMAND) {
            $this->db()->createCommand()
                ->insert($this->elasticIndex, $this->elasticType, $data, $this->getPK());
        } else {
            /** @var ActiveRecord $model */
            $model = \Yii::createObject($this->elasticClass, $data);
            $model->save();
        }
    }

    /**
     * Updating Elasticsearch index record.
     * If indexed record was not found - insert operation will be executed.
     * @param Event $event
     */
    public function update(Event $event)
    {
        $data = $this->getProcessedData();
        if ($this->mode == self::MODE_COMMAND) {
            if (!$this->db()->createCommand()->update($this->elasticIndex, $this->elasticType, $this->getPK(), $data)) {
                $this->db()->createCommand()->insert($this->elasticIndex, $this->elasticType, $data, $this->getPK());
            }
        } else {
            /** @var ActiveRecord $class */
            $class = $this->elasticClass;
            if (!$class::updateAll($data, ['_id' => $this->getPK()])) {
                $this->insert($event, $data);
            }
        }
    }

    /**
     * Deleting record from Elasticsearch index
     * @param Event $event
     */
    public function delete(Event $event)
    {
        if ($this->mode == self::MODE_COMMAND) {
            $this->db()->createCommand()
                ->delete($this->elasticIndex, $this->elasticType, $this->getPK());
        } else {
            /** @var ActiveRecord $class */
            $class = $this->elasticClass;
            $class::deleteAll(['_id' => $this->getPK()]);
        }
    }

    /**
     * Retrieve owner's attribute values
     * @return array
     */
    protected function getProcessedData()
    {
        $data = [];
        if(!$this->dataMap) {
            return $this->owner->attributes;
        }

        foreach ($this->dataMap as $elasticField => $attribute) {
            if (is_callable($attribute)) {
                $data[$elasticField] = call_user_func($attribute);
                if($data[$elasticField] instanceof Expression) {
                    if(trim($data[$elasticField]->expression) == 'NOW()') {
                        $data[$elasticField] = date("Y-m-d H:i:s");
                    } else {
                        throw new InvalidParamException('Attribute value of "'.$elasticField.'" can not be instance of \yii\db\Expression');
                    }
                } elseif(!is_string($data[$elasticField])) {
                    throw new InvalidParamException("Unknown value format for the attribute \"{$elasticField}\"!");
                }
            } elseif(is_string($attribute)) {
                $data[$elasticField] = $this->owner->{$attribute};
            } else {
                throw new InvalidParamException("Unknown value format for the attribute \"{$elasticField}\"!");
            }
        }
        return $data;
    }

    /**
     * @return mixed
     */
    protected function getPK()
    {
        /** @var \yii\db\ActiveRecord $owner */
        $owner = $this->owner;
        return $owner->primaryKey;
    }

    /**
     * @return null|Connection
     * @throws \yii\base\InvalidConfigException
     */
    protected function db()
    {
        return \Yii::$app->get($this->esComponent);
    }
}