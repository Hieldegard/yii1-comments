<?php
class Comment extends CActiveRecord {
    public static function model($className=__CLASS__) { return parent::model($className); }
    public function tableName() { return 'comment'; }
    public function rules() { return [ ['body', 'filter', 'filter'=>'trim'], ['body', 'required'], ['body', 'length', 'max'=>2000] ]; }
    public function attributeLabels() { return ['body'=>'Comment']; }
    protected function beforeSave() {
        if (!parent::beforeSave()) return false;
        $this->updated_at=gmdate('Y-m-d H:i:s');
        if ($this->isNewRecord) $this->created_at=$this->updated_at;
        return true;
    }
}
