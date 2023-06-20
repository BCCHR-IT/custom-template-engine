<?php
/**
 * Include REDCap header.
 */
require_once APP_PATH_DOCROOT . "ProjectGeneral/header.php";

/**
 * Inititalize Custom Template Engine object and display Edit Template
 * page.
 */
$customTemplateEngine = new \BCCHR\CustomTemplateEngine\CustomTemplateEngine();
$template_filtered = filter_input(INPUT_POST, 'template', FILTER_SANITIZE_SPECIAL_CHARS);
$customTemplateEngine->generateCreateEditTemplatePage($template_filtered);

/**
 * Include REDCap footer.
 */
require_once APP_PATH_DOCROOT . "ProjectGeneral/footer.php";
