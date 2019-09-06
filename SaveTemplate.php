<?php
/**
 * Create an instance of the Custom Template Engine class,
 * and save template.
 */
$customTemplateEngine = new \BCCHR\CustomTemplateEngine\CustomTemplateEngine();
$result = $customTemplateEngine->saveTemplate();

/**
 * If TRUE, then redirect to index and set param created = 1.
 * Else, then redirect to Edit Template page, and pass $result.
 */
if ($result === TRUE)
{
    header("Location:" . $customTemplateEngine->getUrl("index.php") . "&created=1");
}
else
{
    /**
     * Include REDCap header.
     */
    require_once APP_PATH_DOCROOT . "ProjectGeneral/header.php";

    $customTemplateEngine->generateEditTemplatePage($result);
    
    /**
     * Include REDCap footer.
     */
    require_once APP_PATH_DOCROOT . "ProjectGeneral/footer.php";
}