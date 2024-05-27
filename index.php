<?php
/**
 * Include REDCap header.
 */
require_once APP_PATH_DOCROOT . "ProjectGeneral/header.php"; 

/**
 * Initialize Custom Report Builder object, and call method
 * to generate Index page.
 */
$customReportBuilder = new \BCCHR\CustomTemplateEngine\CustomTemplateEngine();
$customReportBuilder->setPaths();
$customReportBuilder->generateIndexPage();

/**
 * Include REDCap footer.
 */
require_once APP_PATH_DOCROOT . "ProjectGeneral/footer.php";
