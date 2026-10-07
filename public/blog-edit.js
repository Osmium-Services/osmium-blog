(function () {
    'use strict';

    const config = window.BlogConfig || {};
    const actionUrl = config.basePath + 'blog/action/';
    const form = document.getElementById('blogForm');
    const result = document.getElementById('blogResult');
    const slugInput = document.getElementById('blogSlug');
    const titleInput = document.getElementById('blogTitle');
    const saveButton = document.getElementById('blogSaveButton');

    // No inline style or class: the theme styles the post body from its own CSS
    const purifyConfig = {
        ALLOWED_TAGS: [
            'p', 'br', 'strong', 'em', 'u', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'a', 'blockquote',
            'table', 'thead', 'tbody', 'tr', 'th', 'td', 'hr', 'img', 'sup', 'sub'
        ],
        ALLOWED_ATTR: ['href', 'target', 'rel', 'src', 'alt', 'width', 'height', 'loading', 'colspan', 'rowspan', 'scope']
    };

    // ---- Link picker: one searchable list of the site's pages, categories, products and posts ----

    const linkModalEl = document.getElementById('blogLinkModal');
    const linkSearch = document.getElementById('blogLinkSearch');
    const linkList = document.getElementById('blogLinkList');
    const typeLabels = { page: 'Page', category: 'Category', product: 'Product', post: 'Post' };
    let linkTargets = null;
    let linkModal = null;
    let editorContext = null;
    let selectedText = '';

    async function loadLinkTargets() {
        if (linkTargets) return;
        const response = await fetch(actionUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ csrf_token: window.csrfToken, action: 'link_targets' })
        });
        const data = await response.json();
        linkTargets = data.success ? data.targets : [];
    }

    function renderLinkTargets() {
        const needle = linkSearch.value.trim().toLowerCase();
        const matches = linkTargets.filter(function (target) {
            return needle === '' || (target.title + ' ' + target.url).toLowerCase().includes(needle);
        }).slice(0, 100);

        linkList.replaceChildren();
        if (matches.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'text-muted small p-2';
            empty.textContent = 'Nothing matches.';
            linkList.appendChild(empty);
            return;
        }

        matches.forEach(function (target) {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-2';

            const text = document.createElement('span');
            text.className = 'text-truncate';
            const title = document.createElement('strong');
            title.textContent = target.title;
            const url = document.createElement('small');
            url.className = 'd-block text-muted text-truncate';
            url.textContent = target.url;
            text.append(title, url);

            const badge = document.createElement('span');
            badge.className = 'badge bg-label-secondary flex-shrink-0';
            badge.textContent = typeLabels[target.type] || target.type;

            item.append(text, badge);
            item.addEventListener('click', function () { insertLink(target); });
            linkList.appendChild(item);
        });
    }

    function insertLink(target) {
        linkModal.hide();
        editorContext.invoke('editor.restoreRange');
        editorContext.invoke('editor.createLink', {
            text: selectedText || target.title,
            url: target.url,
            isNewWindow: false
        });
    }

    async function openLinkPicker(context) {
        editorContext = context;
        context.invoke('editor.saveRange');
        selectedText = context.invoke('editor.getSelectedText');

        linkModal = linkModal || new bootstrap.Modal(linkModalEl);
        linkSearch.value = '';
        linkModal.show();
        linkList.textContent = 'Loading...';
        await loadLinkTargets();
        renderLinkTargets();
    }

    // Bootstrap focuses the dialog itself once it has faded in, so the search box takes focus after that
    linkModalEl.addEventListener('shown.bs.modal', function () { linkSearch.focus(); });

    linkSearch.addEventListener('input', function () {
        if (linkTargets) renderLinkTargets();
    });

    // ---- Images: upload, then ask for alt text before inserting into the post ----

    const imageFile = document.getElementById('blogImageFile');
    const imageModalEl = document.getElementById('blogImageModal');
    const imagePreview = document.getElementById('blogImagePreview');
    const imageStatus = document.getElementById('blogImageStatus');
    const imageAlt = document.getElementById('blogImageAlt');
    const imageInsert = document.getElementById('blogImageInsert');
    let imageModal = null;
    let uploaded = null;
    let onFileChosen = null;

    async function uploadImage(file) {
        const formData = new FormData();
        formData.append('csrf_token', window.csrfToken);
        formData.append('action', 'upload_image');
        formData.append('image', file);

        const response = await fetch(actionUrl, { method: 'POST', body: formData });
        const data = await response.json();
        if (!data.success) throw new Error(data.error || data.message || 'Upload failed');

        return data;
    }

    function chooseImage(callback) {
        onFileChosen = callback;
        imageFile.value = '';
        imageFile.click();
    }

    imageFile.addEventListener('change', function () {
        const file = imageFile.files[0];
        if (file && onFileChosen) onFileChosen(file);
    });

    function pictureButton(context) {
        return $.summernote.ui.button({
            contents: '<span title="Add an image"><i class="note-icon-picture"></i> Image</span>',
            click: function () {
                context.invoke('editor.saveRange');
                chooseImage(function (file) { startBodyImage(context, file); });
            }
        }).render();
    }

    async function startBodyImage(context, file) {
        uploaded = null;
        imageInsert.disabled = true;
        imagePreview.classList.add('d-none');
        imageAlt.value = '';
        imageStatus.textContent = 'Uploading...';
        imageModal = imageModal || new bootstrap.Modal(imageModalEl);
        imageModal.show();

        try {
            uploaded = await uploadImage(file);
        } catch (error) {
            imageStatus.textContent = error.message;
            return;
        }

        imagePreview.src = uploaded.url;
        imagePreview.classList.remove('d-none');
        imageStatus.textContent = uploaded.width + ' x ' + uploaded.height + ' px';
        imageInsert.disabled = false;
        imageInsert.onclick = function () { insertBodyImage(context); };
    }

    function insertBodyImage(context) {
        imageModal.hide();
        const img = document.createElement('img');
        img.src = uploaded.url;
        img.alt = imageAlt.value.trim();
        img.width = uploaded.width;
        img.height = uploaded.height;
        img.loading = 'lazy';

        context.invoke('editor.restoreRange');
        context.invoke('editor.insertNode', img);
    }

    imageAlt.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && !imageInsert.disabled) {
            event.preventDefault();
            imageInsert.click();
        }
    });
    imageModalEl.addEventListener('shown.bs.modal', function () { imageAlt.focus(); });

    // ---- Hero and social image slots ----

    function setupSlot(slotEl) {
        const key = slotEl.dataset.imageSlot;
        const value = document.getElementById(key + 'Value');
        const preview = document.getElementById(key + 'Preview');
        const empty = document.getElementById(key + 'Empty');
        const choose = slotEl.querySelector('[data-image-choose]');
        const remove = slotEl.querySelector('[data-image-remove]');

        function show() {
            const has = value.value !== '';
            preview.src = has ? value.value : '';
            preview.classList.toggle('d-none', !has);
            empty.classList.toggle('d-none', has);
            remove.classList.toggle('d-none', !has);
        }

        choose.addEventListener('click', function () {
            chooseImage(async function (file) {
                choose.disabled = true;
                try {
                    const data = await uploadImage(file);
                    value.value = data.url;
                    show();
                } catch (error) {
                    showResult(error.message, 'danger');
                }
                choose.disabled = false;
            });
        });
        remove.addEventListener('click', function () {
            value.value = '';
            show();
        });
    }

    document.querySelectorAll('[data-image-slot]').forEach(setupSlot);

    function pageLinkButton(context) {
        return $.summernote.ui.button({
            // Native title, not Summernote's tooltip option: that one throws on hover for a custom button
            contents: '<span title="Link to a page, category, product or post"><i class="note-icon-link"></i> Page</span>',
            click: function () { openLinkPicker(context); }
        }).render();
    }

    $('#blogBody').summernote({
        height: 480,
        toolbar: [
            ['style', ['style', 'bold', 'italic', 'underline']],
            ['para', ['ul', 'ol']],
            ['insert', ['pageLink', 'link', 'blogImage', 'table', 'hr']],
            ['view', ['codeview']]
        ],
        buttons: { pageLink: pageLinkButton, blogImage: pictureButton },
        styleTags: ['p', 'h2', 'h3', 'h4', 'blockquote']
    });

    function sanitize(html) {
        return DOMPurify.sanitize(html, purifyConfig)
            .replace(/<b>/g, '<strong>').replace(/<\/b>/g, '</strong>')
            .replace(/<i>/g, '<em>').replace(/<\/i>/g, '</em>');
    }

    function slugify(text) {
        return text.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
    }

    function showResult(message, type) {
        result.className = 'alert alert-' + type;
        result.textContent = message;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // A new post's address follows its title until someone edits the address by hand
    let slugTouched = slugInput.value !== '';
    slugInput.addEventListener('input', function () { slugTouched = true; });
    titleInput.addEventListener('input', function () {
        if (!slugTouched) slugInput.value = slugify(titleInput.value);
    });

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        saveButton.disabled = true;

        const body = sanitize($('#blogBody').summernote('code'));
        const payload = {
            csrf_token: window.csrfToken,
            action: 'save',
            id: Number(document.getElementById('blogId').value) || 0,
            title: titleInput.value,
            slug: slugInput.value,
            body: body,
            description: document.getElementById('blogDescription').value,
            excerpt: document.getElementById('blogExcerpt').value,
            hero_image: document.getElementById('blogHeroValue').value,
            social_image: document.getElementById('blogSocialValue').value,
            author_user_id: document.getElementById('blogAuthor').value,
            is_published: document.getElementById('blogPublished').checked,
            published_at: document.getElementById('blogPublishedAt').value
        };

        try {
            const response = await fetch(actionUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await response.json();

            if (!data.success) {
                showResult(data.error || data.message || 'Could not save the post.', 'danger');
                saveButton.disabled = false;
                return;
            }

            const isNew = !payload.id;
            if (isNew) {
                window.location.href = config.editUrl + '?id=' + data.id;
                return;
            }
            showResult('Saved.', 'success');
        } catch (error) {
            showResult('Could not save the post.', 'danger');
        }
        saveButton.disabled = false;
    });
})();
