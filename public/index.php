<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/actions.php';
require dirname(__DIR__) . '/app/views.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        handle_action($pdo);
    } catch (AppException $error) {
        set_flash($error->getMessage());
        redirect_to('?');
    } catch (Throwable $error) {
        error_log((string) $error);
        set_flash('The request could not be completed. Check the form and try again.');
        redirect_to('?');
    }
}

if (isset($_GET['download'])) {
    download_submission($pdo, (int) $_GET['download']);
}

render_page($pdo);
