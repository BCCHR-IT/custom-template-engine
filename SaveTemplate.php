<?php
/**
 * Create an instance of the Custom Report Builder class,
 * and save template.
 */
$customReportBuilder = new \BCCHR\CustomReportBuilder\CustomReportBuilder();
$result = $customReportBuilder->saveTemplate();

/**
 * If TRUE, then redirect to index and set param created = 1.
 * Else, then redirect to Edit Template page, and pass $result.
 */
if ($result === TRUE)
{
    header("Location:" . $customReportBuilder->getUrl("index.php") . "&created=1");
}
else
{
    /**
     * Include REDCap header.
     */
    require_once APP_PATH_DOCROOT . "ProjectGeneral/header.php";

    $customReportBuilder->generateEditTemplatePage($result);
    
    /**
     * Include REDCap footer.
     */
    require_once APP_PATH_DOCROOT . "ProjectGeneral/footer.php";
}