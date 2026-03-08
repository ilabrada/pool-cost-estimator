<?php
/**
 * Common Footer Template
 */
?>

<?php if (isLoggedIn()): ?>
</main><!-- /.main-content -->
<?php endif; ?>

<!-- Toast notification container -->
<div id="toast-container"></div>

<!-- Confirm dialog -->
<div class="modal-overlay" id="confirm-modal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 id="confirm-title" data-i18n="confirm_delete_title">Confirm</h3>
        </div>
        <div class="modal-body">
            <p id="confirm-message">Are you sure?</p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeConfirm()" data-i18n="btn_cancel">Cancel</button>
            <button class="btn btn-danger" id="confirm-action" data-i18n="btn_confirm_delete">Delete</button>
        </div>
    </div>
</div>

<script src="assets/js/i18n/en.js"></script>
<script src="assets/js/i18n/es.js"></script>
<script src="assets/js/i18n/i18n.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
