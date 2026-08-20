<?php if (!empty($scripts)): ?>
    <?php foreach ($scripts as $script): ?>
        <script src="<?= $script; ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>
<script src="<?= base_url('assets/js/script.js'); ?>"></script>
</body>
</html>
