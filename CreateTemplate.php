<?php
/**
 * Include REDCap header.
 */
require_once APP_PATH_DOCROOT . "ProjectGeneral/header.php"; 

/**
 * Initialize Custom Report Builder object, and call method
 * to generate Create Template page.
 */
$customReportBuilder = new \BCCHR\CustomTemplateEngine\CustomTemplateEngine();
$customReportBuilder->generateCreateTemplatePage();

/**
 * Include REDCap footer.
 */
require_once APP_PATH_DOCROOT . "ProjectGeneral/footer.php";