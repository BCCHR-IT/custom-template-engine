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
$customTemplateEngine->generateCreateEditTemplatePage($_POST["template"]);

/**
 * Include REDCap footer.
 */
require_once APP_PATH_DOCROOT . "ProjectGeneral/footer.php";