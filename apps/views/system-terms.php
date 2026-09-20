<div class="modal fade vd-terms-modal" id="systemTermsModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="systemTermsModalLabel" aria-describedby="systemTermsModalDescription" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <div class="vd-terms-eyebrow">Online Platform</div>
          <h2 class="modal-title vd-terms-title" id="systemTermsModalLabel" tabindex="-1">System Terms and Conditions</h2>
          <p class="vd-terms-modal-description" id="systemTermsModalDescription">Review the complete terms. The agreement button becomes available when you reach the end.</p>
        </div>
        <button type="button" class="vd-terms-close" data-bs-dismiss="modal" aria-label="Close Terms and Conditions dialog">Close terms</button>
      </div>
      <div class="modal-body vd-terms-body" id="systemTermsScrollRegion" tabindex="0">
        <?php require __DIR__ . '/system-terms-content.php'; ?>
      </div>
      <div class="modal-footer">
        <span class="vd-terms-scroll-status" id="systemTermsScrollStatus" role="status" aria-live="polite">Scroll to the end to continue.</span>
        <a class="vd-terms-page-link" href="terms.php?from=register" target="_blank" rel="noopener">Open full page <span class="visually-hidden">(opens in a new tab)</span></a>
        <button type="button" class="vd-btn-gold vd-terms-agree-btn" id="systemTermsAgreeButton" disabled>Agree to Terms and close</button>
      </div>
    </div>
  </div>
</div>
