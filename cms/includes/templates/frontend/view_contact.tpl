<!-- Contact Form Fragment -->
<section class="page-header py-5 bg-light mb-0">
    <div class="container text-center">
        <h1 class="page-title h2 fw-bold text-dark"><?= htmlspecialchars($info['d_title'] ?? '{Name}') ?></h1>
    </div>
</section>

<div class="container py-5">
    <div class="row mb-5 justify-content-center">
        <div class="col-lg-5">
            <div class="pe-lg-5">
                <h2 class="font-weight-bold text-7 mb-3">我們很樂意聽取您的意見</h2>
                <p class="text-secondary mb-4">如果您有任何問題、建議或商務洽談需求，請隨時填寫表單與我們聯絡。</p>
                
                <?php if(!empty($info['d_content'])): ?>
                <div class="contact-info-block mb-4 p-4 border rounded bg-white shadow-sm">
                    <?= $info['d_content'] ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="bg-white p-4 p-md-5 border rounded shadow-sm">
                <form id="contactForm" class="contact-form" action="<?= $baseurl ?>/{Slug}/send" method="POST">
                    <div class="contact-form-success alert alert-success d-none mb-4">
                        <strong>成功！</strong> 您的訊息已成功送出，我們會儘快與您聯繫。
                    </div>

                    <div class="contact-form-error alert alert-danger d-none mb-4">
                        <strong>錯誤！</strong> 發送訊息時出錯。
                        <span class="mail-error-message text-1 d-block"></span>
                    </div>
                    
                    <div class="row">
                        <div class="form-group col-lg-6 mb-3">
                            <label class="form-label fw-bold small">您的姓名</label>
                            <input type="text" value="" placeholder="請輸入姓名" data-msg-required="請輸入您的姓名" maxlength="100" class="form-control" name="name" required>
                        </div>
                        <div class="form-group col-lg-6 mb-3">
                            <label class="form-label fw-bold small">電子郵件</label>
                            <input type="email" value="" placeholder="請輸入 Email" data-msg-required="請輸入電子郵件" data-msg-email="請輸入有效的電子郵件" maxlength="100" class="form-control" name="email" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col mb-3">
                            <label class="form-label fw-bold small">連絡電話</label>
                            <input type="text" value="" placeholder="請輸入電話" maxlength="100" class="form-control" name="phone">
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col mb-3">
                            <label class="form-label fw-bold small">主旨</label>
                            <input type="text" value="" placeholder="請輸入訊息主旨" data-msg-required="請輸入主題" maxlength="100" class="form-control" name="subject" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col mb-4">
                            <label class="form-label fw-bold small">訊息內容</label>
                            <textarea maxlength="5000" placeholder="請輸入您想對我們說的話..." data-msg-required="請輸入內容" rows="5" class="form-control" name="message" required></textarea>
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col">
                            <button type="submit" class="btn btn-primary px-5 py-2 fw-bold" data-loading-text="傳送中...">送出表單</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('contactForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const btn = form.querySelector('button[type="submit"]');
            const successMsg = form.querySelector('.contact-form-success');
            const errorMsg = form.querySelector('.contact-form-error');
            const loadingText = btn.getAttribute('data-loading-text');
            const originalText = btn.innerHTML;
            
            btn.innerHTML = loadingText;
            btn.disabled = true;
            successMsg.classList.add('d-none');
            errorMsg.classList.add('d-none');
            
            const formData = new FormData(form);
            
            fetch(form.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                btn.innerHTML = originalText;
                btn.disabled = false;
                
                if (data.status === 'success') {
                    successMsg.classList.remove('d-none');
                    form.reset();
                } else {
                    errorMsg.classList.remove('d-none');
                    errorMsg.querySelector('.mail-error-message').innerText = data.message;
                }
            })
            .catch(error => {
                btn.innerHTML = originalText;
                btn.disabled = false;
                errorMsg.classList.remove('d-none');
                errorMsg.querySelector('.mail-error-message').innerText = '網路連線異常，請稍後再試。';
            });
        });
    }
});
</script>
