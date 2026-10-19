<h1>Join the conversation</h1><p>Leave a comment. No account needed.</p>
<?php echo CHtml::beginForm(); echo CHtml::errorSummary($model); ?>
<label for="body">Your comment</label><textarea id="body" name="body" required maxlength="2000" rows="4"><?php echo CHtml::encode($model->body); ?></textarea><button>Post comment</button><?php echo CHtml::endForm(); ?>
<h2>Recent comments</h2><p class="muted">Showing up to 200 newest comments.</p>
<?php if (!$comments): ?><p>No comments yet. Start the conversation!</p><?php endif; ?>
<?php foreach ($comments as $comment): ?><article><small>#<?php echo (int)$comment->id; ?> · <?php echo CHtml::encode($comment->created_at); ?> UTC</small><p class="body"><?php echo CHtml::encode($comment->body); ?></p></article><?php endforeach; ?>
