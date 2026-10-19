<?php
// Private service-to-service endpoint. No comment content is returned.
class EventsController extends Controller {
    public function actionIndex() {
        $secret=getenv('WS_SECRET');
        if (!$secret || !hash_equals($secret, $_SERVER['HTTP_X_EVENTS_SECRET'] ?? '')) throw new CHttpException(403,'Forbidden');
        $after=Yii::app()->request->getQuery('after');
        $max=(int)Yii::app()->db->createCommand('SELECT COALESCE(MAX(id),0) FROM event')->queryScalar();
        if ($after===null) $this->json(['cursor'=>$max,'events'=>[]]);
        $events=Yii::app()->db->createCommand('SELECT id,kind FROM event WHERE id>:id ORDER BY id LIMIT 100')->queryAll(true,[':id'=>(int)$after]);
        $this->json(['cursor'=>$events ? (int)end($events)['id'] : (int)$after, 'events'=>$events]);
    }
}
