<?php
/**
 * Initialize Custom Report Builder object, and call method to delete a
 * template.
 */
$customReportBuilder = new \BCCHR\CustomTemplateEngine\CustomTemplateEngine();
$result = $customReportBuilder->deleteTemplate();

/**
 * If TRUE, then template was deleted successfullly and redirect to index with param deleted = 1.
 * Else, then template wasn't deleted successfully and redirect to index with param deleted = 0.
 */
if ($result === TRUE)
{
    header("Location:" . $customReportBuilder->getUrl("index.php") . "&deleted=1");
}
else
{
    header("Location:" . $customReportBuilder->getUrl("index.php") . "&deleted=0");
}