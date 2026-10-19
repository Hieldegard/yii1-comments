<?php
class Database {
    public static function initialize() {
        $db=Yii::app()->db;
        $db->createCommand('PRAGMA busy_timeout=5000')->execute();
        $db->createCommand('PRAGMA journal_mode=WAL')->queryScalar();
        $db->createCommand('CREATE TABLE IF NOT EXISTS comment (id INTEGER PRIMARY KEY AUTOINCREMENT, body TEXT NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)')->execute();
        $db->createCommand('CREATE TABLE IF NOT EXISTS event (id INTEGER PRIMARY KEY AUTOINCREMENT, kind TEXT NOT NULL)')->execute();
    }
    public static function event($kind) {
        Yii::app()->db->createCommand()->insert('event', ['kind'=>$kind]);
    }
}
