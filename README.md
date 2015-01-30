Elasticsearch Behavior for Yii 2
================================

[![Latest Stable Version](https://poser.pugx.org/borales/yii2-elasticsearch-behavior/v/stable.svg)](https://packagist.org/packages/borales/yii2-elasticsearch-behavior)
[![Total Downloads](https://poser.pugx.org/borales/yii2-elasticsearch-behavior/downloads.svg)](https://packagist.org/packages/borales/yii2-elasticsearch-behavior) 
[![Latest Unstable Version](https://poser.pugx.org/borales/yii2-elasticsearch-behavior/v/unstable.svg)](https://packagist.org/packages/borales/yii2-elasticsearch-behavior) 
[![License](https://poser.pugx.org/borales/yii2-elasticsearch-behavior/license.svg)](https://packagist.org/packages/borales/yii2-elasticsearch-behavior)

Yii2 AR behavior to support [Elasticsearch](http://www.elasticsearch.org/) auto-indexing.

![image](https://cloud.githubusercontent.com/assets/1118933/5841840/63c39bb0-a1a0-11e4-9a6b-df0911203ba5.png)

## Installation

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```bash
$ php composer.phar require "borales/yii2-elasticsearch-behavior" "*"
```

or add

```
"borales/yii2-elasticsearch-behavior": "*"
```

to the `require` section of your `composer.json` file.

## Configuring

Configure model as follows (for "command" `mode`):

```php
class Post extends \yii\db\ActiveRecord
{
    public function behaviors()
    {
        return [
            ...
            'elasticsearch' => [
                'class' => \borales\behaviors\elasticsearch\Behavior::className(),
                'mode' => 'command',
                'elasticIndex' => 'project-index',
                'elasticType' => 'posts',
                'dataMap' => [
                    'id' => 'id',
                    'title' => 'name',
                    'body' => function() {
                        return strip_tags($this->body);
                    },
                    'date_publish' => function() {
                        return date('U', strtotime($this->date_create));
                    },
                    'author' => function() {
                        return ucfirst($this->author->name);
                    }
                ]
            ],
        ];
    }
    
    ...
}
```

Configuration values of the behavior:
- `mode` (possible values: `command` or `model`. Default is `command`) - it is the option, which controls how to interact with Elasticsearch:
 - in case of `command` - the behavior use `\Yii::$app->elasticsearch->createCommand()` way to execute commands. In this mode - it is required to set up `elasticIndex` and `elasticType` params.
 - in case of `model` - it is required to set up `elasticClass` parameter with the value of model class name (specified class must extend the `\yii\elasticsearch\ActiveRecord` model class). In this case behavior will communicate with Elasticsearch through the specified model class.
- `dataMap` - this is an optional parameter. By default - the behavior will use `$this->owner->attributes` dynamic property of the `\yii\db\ActiveRecord` class (you can learn more how to set up this property [here](https://github.com/yiisoft/yii2/blob/master/docs/guide/structure-models.md#data-exporting-)). Otherwise - this is a key-value array, where the keys are the field names for the Elasticsearch entry and the values are the field names of the current `\yii\db\ActiveRecord` model or anonymous functions (callbacks).

### Example of using "model" `mode`

```php
class Post extends \yii\db\ActiveRecord
{
    public function behaviors()
    {
        return [
            ...
            'elasticsearch' => [
                'class' => \borales\behaviors\elasticsearch\Behavior::className(),
                'mode' => 'model',
                'elasticClass' => \common\models\elastic\PostElastic::className(),
                'dataMap' => [
                    'id' => 'id',
                    'title' => 'name',
                    'body' => function() {
                        return strip_tags($this->body);
                    },
                    'date_publish' => function() {
                        return date('U', strtotime($this->date_create));
                    },
                    'author' => function() {
                        return ucfirst($this->author->name);
                    }
                ]
            ],
        ];
    }
    
    ...
}

...

class PostElastic extends \yii\elasticsearch\ActiveRecord
{
    /**
     * @return array the list of attributes for this record
     */
    public function attributes()
    {
        // path mapping for '_id' is setup to field 'id'
        return ['id', 'title', 'body', 'date_publish', 'author'];
    }
}

```

More details and features about Elasticsearch ActiveRecord you will find [here](https://github.com/yiisoft/yii2-elasticsearch#using-the-activerecord).