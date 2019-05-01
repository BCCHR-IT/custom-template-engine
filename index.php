<?php
require_once APP_PATH_DOCROOT . "ProjectGeneral/header.php"; 
$customReportBuilder = new \BCCHR\CustomReportBuilder\CustomReportBuilder();
$customReportBuilder->generateIndexPage();
require_once APP_PATH_DOCROOT . "ProjectGeneral/footer.php";