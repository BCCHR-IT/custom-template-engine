<?php
/**
 * Initialize Custom Template Engine object, and call method to delete a
 * template.
 */
$customTemplateEngine = new \BCCHR\CustomTemplateEngine\CustomTemplateEngine();
$result = $customTemplateEngine->deleteTemplate();

/**
 * If TRUE, then template was deleted successfullly and redirect to index with param deleted = 1.
 * Else, then template wasn't deleted successfully and redirect to index with param deleted = 0.
 */
if ($result === TRUE)
{
    header("Location:" . $customTemplateEngine->getUrl("index.php") . "&deleted=1");
}
else
{
    header("Location:" . $customTemplateEngine->getUrl("index.php") . "&deleted=0");
}