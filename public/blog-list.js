/**
 * Blog post list: the Delete menu item opens a modal with an optional redirect target
 * (list:action is fired by the shared list view; config comes from window.BlogList)
 */
(function () {
    const modalEl = document.getElementById('deletePostModal');
    if (!modalEl) return;

    const idInput = document.getElementById('deletePostId');
    const redirectInput = document.getElementById('deleteRedirectTarget');
    const confirmBtn = document.getElementById('confirmDeletePostBtn');
    const confirmHtml = confirmBtn.innerHTML;

    document.addEventListener('list:action', (event) => {
        const { action, id } = event.detail;
        if (action !== 'delete') return;

        const post = window.BlogList.posts[id];
        if (!post) return;

        idInput.value = id;
        redirectInput.value = '';
        document.getElementById('deletePostTitle').textContent = post.title || 'Untitled';
        document.getElementById('deletePostUrl').textContent = post.url;
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    });

    confirmBtn.addEventListener('click', async () => {
        confirmBtn.disabled = true;
        confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Deleting...';

        try {
            const response = await fetch(window.BlogList.endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'delete',
                    id: Number(idInput.value),
                    redirect_target: redirectInput.value.trim() || null,
                    csrf_token: window.csrfToken
                })
            });
            const result = await response.json();

            if (result.success) {
                window.location.reload();
                return;
            }
            adminAlert(result.error || 'Delete failed');
        } catch (error) {
            console.error('Delete error:', error);
            adminAlert('An error occurred');
        }

        confirmBtn.disabled = false;
        confirmBtn.innerHTML = confirmHtml;
    });
})();
