<?php
/**
 * Include REDCap header.
 */
require_once APP_PATH_DOCROOT . "ProjectGeneral/header.php";

/**
 * Inititalize Custom Report Builder object and display Edit Template
 * page.
 */
$customReportBuilder = new \BCCHR\CustomTemplateEngine\CustomTemplateEngine();
$customReportBuilder->generateEditTemplatePage();

/**
 * Include REDCap footer.
 */
require_once APP_PATH_DOCROOT . "ProjectGeneral/footer.php";