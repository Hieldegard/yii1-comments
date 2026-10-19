<?php
class AdminController extends Controller {
    public function beforeAction($action) { $this->requireAdmin(); header('Cache-Control: no-store'); return parent::beforeAction($action); }
    public function actionIndex() { $this->render('index',['comments'=>$this->comments()]); }
    public function actionFeed() { $this->renderPartial('_list',['comments'=>$this->comments()]); }
    public function actionToken() {
        $payload=base64_encode(CJSON::encode(['exp'=>time()+300,'role'=>'admin']));
        $signature=hash_hmac('sha256',$payload,getenv('WS_SECRET'));
        $this->json(['token'=>$payload.'.'.$signature]);
    }
    public function actionEdit($id) {
        $model=$this->find($id);
        if (Yii::app()->request->isPostRequest) {
            $model->body=Yii::app()->request->getPost('body','');
            $tx=Yii::app()->db->beginTransaction();
            try {
                if ($model->save()) { Database::event('updated'); $tx->commit(); $this->redirect(['admin/index']); }
                $tx->rollback();
            } catch (Throwable $e) { if ($tx->active) $tx->rollback(); throw $e; }
        }
        $this->render('edit',['model'=>$model]);
    }
    public function actionDelete($id) {
        $this->requirePost(); $model=$this->find($id);
        $tx=Yii::app()->db->beginTransaction();
        try { $model->delete(); Database::event('deleted'); $tx->commit(); }
        catch (Throwable $e) { if ($tx->active) $tx->rollback(); throw $e; }
        $this->redirect(['admin/index']);
    }
    private function find($id) {
        $model=Comment::model()->findByPk((int)$id);
        if (!$model) throw new CHttpException(404,'Comment not found.');
        return $model;
    }
}
