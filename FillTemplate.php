<?php

/**
 * Create instsance of Custom Template Engine, and display template 
 * filled with REDcap data on Fill Template page.
 */
$customTemplateEngine = new \BCCHR\CustomTemplateEngine\CustomTemplateEngine();
$customTemplateEngine->setPaths();

if ($_POST["participantID"] == null) { exit(1);}  // PHP8 compatability fix; can't pass a blank participantID scalar. Dan Evans 2023-06-09
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
