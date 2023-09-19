<?php

/**
 * Create instsance of Custom Template Engine, and display template 
 * filled with REDcap data on Fill Template page.
 */
$customTemplateEngine = new \MCRI\CustomTemplateEngine\CustomTemplateEngine();

if (sizeof($_POST["participantID"]) == 1)
{
    /**
     * Include REDCap header.
     */
    require_once APP_PATH_DOCROOT . "ProjectGeneral/header.php";

    $customTemplateEngine->generateFillTemplatePage();

    /**
     * Include REDCap footer.
     */
    require_once APP_PATH_DOCROOT . "ProjectGeneral/footer.php";
}
else
{
    $customTemplateEngine->batchFillReports();
}
