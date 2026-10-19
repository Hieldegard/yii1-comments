<?php
class Controller extends CController {
    public $layout='//layouts/main';
    protected function requireAdmin() {
        if (Yii::app()->user->isGuest) throw new CHttpException(403,'Please log in as administrator.');
    }
    protected function requirePost() {
        if (!Yii::app()->request->isPostRequest) throw new CHttpException(405,'POST required.');
    }
    protected function json($value) {
        header('Content-Type: application/json'); header('Cache-Control: no-store');
        echo CJSON::encode($value); Yii::app()->end();
    }
    protected function comments() { return Comment::model()->findAll(['order'=>'id DESC', 'limit'=>200]); }
}
