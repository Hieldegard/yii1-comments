<?php
class SiteController extends Controller {
    public function actionIndex() {
        $model=new Comment;
        if (Yii::app()->request->isPostRequest) {
            $model->body=Yii::app()->request->getPost('body','');
            $tx=Yii::app()->db->beginTransaction();
            try {
                if ($model->save()) {
                    Database::event('created'); $tx->commit();
                    $this->redirect(['site/index']);
                }
                $tx->rollback();
            } catch (Throwable $e) { if ($tx->active) $tx->rollback(); throw $e; }
        }
        $this->render('index',['model'=>$model,'comments'=>$this->comments()]);
    }
    public function actionLogin() {
        if (!Yii::app()->user->isGuest) $this->redirect(['admin/index']);
        $error='';
        if (Yii::app()->request->isPostRequest) {
            $session=Yii::app()->session;
            $attempts=$session->get('attempts',[]);
            $attempts=array_values(array_filter($attempts,function($t){ return $t>time()-60; }));
            if (count($attempts)>=5) throw new CHttpException(429,'Too many attempts. Try again in one minute.');
            $attempts[]=time(); $session['attempts']=$attempts;
            $name=(string)Yii::app()->request->getPost('username','');
            $password=(string)Yii::app()->request->getPost('password','');
            $hash=getenv('ADMIN_PASSWORD_HASH');
            if ($hash && hash_equals(getenv('ADMIN_USERNAME') ?: 'admin',$name) && password_verify($password,$hash)) {
                $identity=new CUserIdentity($name,'');
                $identity->errorCode=CUserIdentity::ERROR_NONE;
                Yii::app()->user->login($identity,0);
                $session->regenerateID(true); unset($session['attempts']);
                $this->redirect(['admin/index']);
            }
            $error='Invalid username or password.';
        }
        $this->render('login',['error'=>$error]);
    }
    public function actionLogout() { $this->requirePost(); Yii::app()->user->logout(); $this->redirect(['site/index']); }
    public function actionError() {
        if ($error=Yii::app()->errorHandler->error) {
            $this->render('error',['message'=>$error['message']]);
        }
    }
}
