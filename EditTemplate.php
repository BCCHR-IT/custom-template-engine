<?php
require_once APP_PATH_DOCROOT . "ProjectGeneral/header.php"; 
$customReportBuilder = new \BCCHR\CustomReportBuilder\CustomReportBuilder();
$customReportBuilder->generateEditTemplatePage();
require_once APP_PATH_DOCROOT . "ProjectGeneral/footer.php";