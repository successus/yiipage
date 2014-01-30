<?php

class Page extends CActiveRecord {

    public $_parent_id;
    public $_slug;

    public static function model($className = __CLASS__) {
        return parent::model($className);
    }

    public function tableName() {
        return 'pages';
    }

    public function rules() {
        return array(
            array('slug, page_title', 'required'),
            array('content, meta_description, meta_keywords, parent_id, template', 'safe'),
            array('parent_id', 'compare', 'operator' => '!=', 'compareAttribute' => 'id', 'allowEmpty' => true, 'message' => 'Узел не может быть сам себе родителем.'),
            array('slug', 'match', 'pattern' => '/^[\w][\w\-]*+$/', 'message' => 'Разрешённые символы: строчные буквы латинского алфавита, цифры, дефис.'),
            array('page_title', 'match', 'pattern' => '/^\d+$/', 'not' => true, 'message' => 'Заголовок страницы не может состоять из одного числа.'), // иначе будут проблемы при генерации хлебных крошек
            array('template', 'default', 'setOnEmpty' => true, 'value' => null),
            array('is_published', 'boolean'),
            array('id, slug, page_title, is_published', 'safe', 'on' => 'search'),
        );
    }

    public function attributeLabels() {
        return array(
            'id' => 'ID',
            'lft' => 'Левый ключ',
            'rgt' => 'Правый ключ',
            'level' => 'Уровень',
            'parent_id' => 'Родитель',
            'slug' => 'Текстовый идентификатор',
            'template' => 'Шаблон',
            'is_published' => 'Опубликована',
            'page_title' => 'Заголовк',
            'content' => 'Содержимое страницы',
            'meta_title' => 'Мета-заголовок',
            'meta_description' => 'Описание страницы',
            'meta_keywords' => 'Ключевые слова',
        );
    }

    public function defaultScope() {
        return array(
            'order' => 'root, lft',
        );
    }

    public function scopes() {
        return array(
            'published' => array(
                'condition' => 'is_published = 1',
            ),
        );
    }

    public function behaviors() {
        return array(
            'nestedSetBehavior' => array(
                'class' => 'core.extensions.nested-set.NestedSetBehavior',
                'leftAttribute' => 'lft',
                'rightAttribute' => 'rgt',
                'levelAttribute' => 'level',
                'rootAttribute' => 'root',
                'hasManyRoots' => true,
            ),
            'CTimestampBehavior' => array(
                'class' => 'zii.behaviors.CTimestampBehavior',
                'createAttribute' => 'created',
                'updateAttribute' => 'updated',
            )
        );
    }

    public function getPreview($param = null) {
        if (is_int($param))
            return substr($this->content, $param);

        $pos = strpos($this->content, '<p class="break"></p>');
        if ($pos > 1)
            return substr($this->content, $pos);
        else
            return $this->content;
    }

    protected function beforeSave() {
        if (parent::beforeSave()) {
            $this->slug = str_replace('.', '', $this->slug);
            $this->slug = preg_replace('/[\_]+/', '_', $this->slug);
            $this->slug = strtolower($this->slug);
            return true;
        }
        else
            return false;
    }

    protected function afterFind() {
        if (parent::afterFind()) {
            $this->_parent_id = $this->parent_id;
            $this->_slug = $this->slug;
        }else
            return false;
    }

    protected function afterSave() {
        if (parent::afterSave()) {
            if ($this->parent_id !== $this->_parent_id || $this->slug !== $this->_slug)
            //   Yii::app()->getModule('pages')->updatePathsMap();
                Yii::app()->getModule('pages')->clearPathMap();
            return true;
        }
        else
            return false;
    }

    protected function afterDelete() {
        if (parent::afterDelete()) {
        //Yii::app()->getModule('pages')->updatePathsMap();
            Yii::app()->getModule('pages')->clearPathMap();
            return true;
        }
        else
            return false;
        //return true;
    }

    public function search() {
        $criteria = new CDbCriteria;

        $criteria->compare('id', $this->id, true);
        $criteria->compare('slug', $this->slug, true);
        $criteria->compare('page_title', $this->page_title, true);
        $criteria->compare('is_published', $this->is_published);

        return new CActiveDataProvider(__CLASS__, array(
                    'criteria' => $criteria,
                ));
    }

    public function searchChildren($level = false) {
        $criteria = new CDbCriteria;

        $criteria->addCondition('rgt < ' . $this->rgt);
        $criteria->addCondition('lft > ' . $this->lft);
        if ($level)
            $criteria->addCondition('level = ' . (int) $this->level + $level);

        
        $criteria->addCondition('is_published = 1');
        $criteria->order = "created DESC";
        return new CActiveDataProvider('Page', array(
                    'criteria' => $criteria,
                ));
    }

    public function getBreadcrumbs() {
        $ancestors = $this->ancestors()->findAll();
        $output = array();
        foreach ($ancestors as $ancestor)
            $output[$ancestor->page_title] = Yii::app()->urlManager->createUrl('/pages/default/view', array('id' => $ancestor->id));
        array_push($output, $this->page_title);
        return $output;
    }

    /**
     * Формирует массив из страниц для использования в выпадающем меню, например, при выборе родителя узла.
     */
    public function selectList() {
        $output = array();
        $nodes = $this->findAll();
        foreach ($nodes as $node)
            $output[$node->id] = str_repeat('  ', $node->level - 1) . $node->page_title;
        return $output;
    }

}