<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Comment Room</title><link rel="stylesheet" href="/assets/style.css"></head>
<body><header><a href="<?php echo $this->createUrl('site/index'); ?>">Comment Room</a><nav>
<?php if (Yii::app()->user->isGuest): ?><a href="<?php echo $this->createUrl('site/login'); ?>">Admin login</a>
<?php else: ?><a href="<?php echo $this->createUrl('admin/index'); ?>">Moderate</a>
<?php echo CHtml::beginForm($this->createUrl('site/logout'),'post'); ?><button>Log out</button><?php echo CHtml::endForm(); ?>
<?php endif; ?></nav></header><main><?php echo $content; ?></main></body></html>
