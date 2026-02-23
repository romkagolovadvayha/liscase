/**
 * Clans JavaScript functionality
 */

(function() {
    'use strict';

    // Tab switching
    const tabLinks = document.querySelectorAll('.clan-view__tab');
    const tabContents = document.querySelectorAll('.clan-view__tab-content');

    if (tabLinks.length > 0) {
        tabLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const targetTab = this.getAttribute('data-tab');

                // Remove active class from all tabs and contents
                tabLinks.forEach(tab => tab.classList.remove('active'));
                tabContents.forEach(content => content.classList.remove('active'));

                // Add active class to clicked tab and corresponding content
                this.classList.add('active');
                const targetContent = document.getElementById(targetTab);
                if (targetContent) {
                    targetContent.classList.add('active');
                }

                // Load content via AJAX if needed
                loadTabContent(targetTab);
            });
        });
    }

    /**
     * Load tab content via AJAX
     */
    function loadTabContent(tab) {
        const contentElement = document.getElementById(tab);
        if (!contentElement) {
            return;
        }

        // Check if already loaded
        if (contentElement.dataset.loaded === 'true') {
            return;
        }

        // Get URL from data attribute
        const url = contentElement.getAttribute('data-load-url');
        if (!url) {
            return;
        }

        fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.text())
        .then(html => {
            contentElement.innerHTML = html;
            contentElement.dataset.loaded = 'true';
        })
        .catch(error => {
            console.error('Error loading tab content:', error);
            contentElement.innerHTML = '<p class="error">Ошибка загрузки данных</p>';
        });
    }

    /**
     * Get clan ID from URL
     */
    function getClanIdFromUrl() {
        const match = window.location.pathname.match(/\/clans\/[^\/]+\/(\d+)/);
        return match ? match[1] : null;
    }

    // Logo preview
    const logoInput = document.querySelector('input[type="file"][name*="logoFile"]');
    if (logoInput) {
        logoInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Validate file size (2MB)
                if (file.size > 2 * 1024 * 1024) {
                    alert('Размер файла не должен превышать 2MB');
                    this.value = '';
                    return;
                }

                // Validate file type
                const allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/svg+xml', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Разрешены только файлы PNG, JPG, JPEG, SVG, WEBP');
                    this.value = '';
                    return;
                }

                // Show preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.querySelector('.clans-update__current-logo, .clan-form__logo-preview');
                    if (preview) {
                        preview.innerHTML = `<img src="${e.target.result}" alt="Preview" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover;" />`;
                    } else {
                        // Create preview if doesn't exist
                        const form = logoInput.closest('form');
                        if (form) {
                            const previewDiv = document.createElement('div');
                            previewDiv.className = 'clan-form__logo-preview';
                            previewDiv.innerHTML = `<img src="${e.target.result}" alt="Preview" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; margin-bottom: 1rem;" />`;
                            logoInput.parentNode.insertBefore(previewDiv, logoInput);
                        }
                    }
                };
                reader.readAsDataURL(file);
            }
        });
    }

    // Confirm actions
    document.addEventListener('click', function(e) {
        const confirmLink = e.target.closest('[data-confirm]');
        if (confirmLink) {
            const message = confirmLink.getAttribute('data-confirm');
            if (!confirm(message)) {
                e.preventDefault();
            }
        }
    });

    // AJAX form submissions
    const inviteForm = document.querySelector('.invite-form');
    if (inviteForm) {
        inviteForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitFormAjax(this);
        });
    }

    const permissionsForm = document.querySelector('.permissions-form');
    if (permissionsForm) {
        permissionsForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitFormAjax(this);
        });
    }

    /**
     * Submit form via AJAX
     */
    function submitFormAjax(form) {
        const formData = new FormData(form);
        const url = form.getAttribute('action') || form.action;

        fetch(url, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.redirect) {
                    window.location.href = data.redirect;
                } else {
                    location.reload();
                }
            } else {
                if (data.message) {
                    alert(data.message);
                }
            }
        })
        .catch(error => {
            console.error('Error submitting form:', error);
            alert('Произошла ошибка при отправке формы');
        });
    }
})();

