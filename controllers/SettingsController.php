<?php

declare(strict_types=1);

namespace Osmium\Services\Blog\Controllers;

use Osmium\Modules\Admin\Core\AdminController;
use Osmium\Services\Blog\Models\BlogPost;
use Osmium\Services\Blog\Models\BlogSettings;

/**
 * Blog settings - who a new post is credited to by default.
 *
 * Routes:
 *   - index() → /admin/settings/blog/  (GET shows the form, POST saves it)
 */
class SettingsController extends AdminController
{
    public function index(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') $this->handleSubmit();

        $this->data['admin']['settings'] = BlogSettings::get();
        $this->data['admin']['authors'] = (new BlogPost($this->osmium->dataSource))->authors();
        $this->data['admin']['settingsSaved'] = $_SESSION['blog_settings_saved'] ?? false;
        $this->data['admin']['settingsError'] = $_SESSION['blog_settings_error'] ?? false;
        unset($_SESSION['blog_settings_saved'], $_SESSION['blog_settings_error']);

        $this->setView('settings/index.phtml');
    }

    private function handleSubmit(): void
    {
        if (!$this->admin->auth->validateCsrf()) {
            $_SESSION['blog_settings_error'] = 'Invalid form submission. Please try again.';
            $this->redirect('settings/blog/');
        }

        $authorId = (int) ($_POST['default_author_id'] ?? 0);
        $knownIds = \array_map('intval', \array_column((new BlogPost($this->osmium->dataSource))->authors(), 'id'));
        if (!\in_array($authorId, $knownIds, true)) $authorId = 0;

        BlogSettings::save(defaultAuthorId: $authorId);

        $this->admin->model->changelog->log(
            description: 'Updated Blog settings',
            recordType: 'settings',
        );

        $_SESSION['blog_settings_saved'] = true;
        $this->redirect('settings/blog/');
    }
}
