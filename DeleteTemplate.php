<?php
$customReportBuilder = new \BCCHR\CustomReportBuilder\CustomReportBuilder();
$result = $customReportBuilder->deleteTemplate();

if ($result === TRUE)
{
    header("Location:" . $customReportBuilder->getUrl("index.php") . "&deleted=1");
}
else
{
    header("Location:" . $customReportBuilder->getUrl("index.php") . "&deleted=0");
}