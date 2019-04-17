<?php
$customReportBuilder = new \BCCHR\CustomReportBuilder\CustomReportBuilder();
$result = $customReportBuilder->saveTemplate();

if ($result === TRUE)
{
    header("Location:" . $customReportBuilder->getUrl("index.php") . "&created=1");
}
else
{
    require_once APP_PATH_DOCROOT . "ProjectGeneral/header.php"; 
    $customReportBuilder->generateEditTemplatePage($result);
    require_once APP_PATH_DOCROOT . "ProjectGeneral/footer.php";
}