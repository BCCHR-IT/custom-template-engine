<?php
/**
 * Include REDCap header.
 */
require_once APP_PATH_DOCROOT . "ProjectGeneral/header.php";

/**
 * Create instsance of Custom Report Builder, and display template 
 * filled with REDcap data on Fill Template page.
 */
$customReportBuilder = new \BCCHR\CustomReportBuilder\CustomReportBuilder();
$customReportBuilder->generateFillTemplatePage();

/**
 * Include REDCap footer.
 */
require_once APP_PATH_DOCROOT . "ProjectGeneral/footer.php";